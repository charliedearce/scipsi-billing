<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\CustomerPaymentCredit;
use App\Models\CustomerPaymentCreditMovement;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WalkInCustomer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPaymentCreditService
{
    public function createFromReceipt(Receipt $receipt, User $actor): ?CustomerPaymentCredit
    {
        return DB::transaction(function () use ($receipt, $actor): ?CustomerPaymentCredit {
            $locked = Receipt::where('organization_id', $actor->organization_id)
                ->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'POSTED' || $locked->postingSource?->source_type !== 'MANUAL_PAYMENT_PROOF') {
                return null;
            }
            $cashApplied = (string) $locked->allocations()->sum('cash_applied_amount');
            $excess = bcsub((string) $locked->cash_received_amount, $cashApplied, 2);
            if (bccomp($excess, '0.00', 2) <= 0) {
                return null;
            }
            if (bccomp($excess, (string) $locked->unapplied_amount, 2) > 0) {
                throw ValidationException::withMessages(['receipt' => ['Receipt cash excess does not reconcile with its unapplied amount.']]);
            }
            $existing = CustomerPaymentCredit::where('source_receipt_id', $locked->id)->lockForUpdate()->first();
            if ($existing) {
                if (bccomp((string) $existing->original_amount, $excess, 2) !== 0) {
                    throw ValidationException::withMessages(['receipt' => ['This receipt already has a different credit amount.']]);
                }

                return $existing;
            }
            $credit = CustomerPaymentCredit::create([
                'organization_id' => $locked->organization_id,
                'customer_id' => $locked->customer_id,
                'source_receipt_id' => $locked->id,
                'currency' => strtoupper((string) $locked->currency),
                'original_amount' => $excess,
            ]);
            CustomerPaymentCreditMovement::create([
                'credit_id' => $credit->id,
                'type' => 'CREATED',
                'amount' => $excess,
                'source_key' => 'receipt:'.$locked->id,
                'actor_user_id' => $actor->id,
            ]);

            return $credit;
        });
    }

    public function availableForCustomer(int $organizationId, int $customerId, string $currency): string
    {
        $total = '0.00';
        $lots = CustomerPaymentCredit::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->where('currency', strtoupper($currency))
            ->whereHas('sourceReceipt', fn ($query) => $query->where('status', 'POSTED'))
            ->orderBy('id')->get();
        foreach ($lots as $lot) {
            $total = bcadd($total, $this->availableForLot($lot), 2);
        }

        return $total;
    }

    /** @return Collection<int, CustomerPaymentCredit> */
    public function lockAvailableLots(Customer $customer, string $currency): Collection
    {
        return CustomerPaymentCredit::where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('currency', strtoupper($currency))
            ->whereHas('sourceReceipt', fn ($query) => $query->where('status', 'POSTED'))
            ->orderBy('id')->lockForUpdate()->get()
            ->filter(fn (CustomerPaymentCredit $lot): bool => bccomp($this->availableForLot($lot), '0.00', 2) > 0)
            ->values();
    }

    /**
     * Caller locks the customer, then credit lots, then invoices in that order.
     *
     * @param  Collection<int, CustomerPaymentCredit>  $lots
     * @param  Collection<int, Invoice>  $invoices
     * @return array{total:string,by_invoice:array<int,string>}
     */
    public function applyToLockedInvoices(User $actor, Customer $customer, Collection $lots, Collection $invoices, string $sourceKey): array
    {
        if ($actor->organization_id !== $customer->organization_id) {
            throw ValidationException::withMessages(['customer_id' => ['Customer is outside this organization.']]);
        }
        $existing = CustomerPaymentCreditMovement::where('checkout_source_key', $sourceKey)->where('type', 'APPLIED')
            ->whereHas('credit', fn ($query) => $query->where('organization_id', $customer->organization_id))->get();
        if ($existing->isNotEmpty()) {
            $selectedInvoiceIds = $invoices->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($existing as $movement) {
                $credit = $movement->credit;
                if ($credit->organization_id !== $customer->organization_id
                    || $credit->customer_id !== $customer->id
                    || ! in_array((int) $movement->invoice_id, $selectedInvoiceIds, true)) {
                    throw ValidationException::withMessages(['credit' => ['Checkout key belongs to another customer or bill selection.']]);
                }
            }
            $byInvoice = [];
            foreach ($existing as $movement) {
                $invoiceId = (int) $movement->invoice_id;
                $byInvoice[$invoiceId] = bcadd($byInvoice[$invoiceId] ?? '0.00', (string) $movement->amount, 2);
            }

            return ['total' => $existing->reduce(fn (string $sum, CustomerPaymentCreditMovement $row): string => bcadd($sum, (string) $row->amount, 2), '0.00'), 'by_invoice' => $byInvoice];
        }
        $currency = $lots->first()?->currency;
        foreach ($lots as $lot) {
            if ($lot->organization_id !== $customer->organization_id || $lot->customer_id !== $customer->id
                || $lot->currency !== $currency || $lot->sourceReceipt?->status !== 'POSTED') {
                throw ValidationException::withMessages(['credit' => ['Payment credit does not belong to this customer or is unavailable.']]);
            }
        }
        foreach ($invoices as $invoice) {
            $owned = (int) $invoice->customer_id === (int) $customer->id
                || ($invoice->walk_in_customer_id && WalkInCustomer::whereKey($invoice->walk_in_customer_id)->where('customer_id', $customer->id)->exists());
            if ($invoice->organization_id !== $customer->organization_id || ! $owned || $invoice->status !== 'POSTED' || ($currency && $invoice->currency !== $currency)) {
                throw ValidationException::withMessages(['invoice' => ['Payment credit can only settle this customer’s posted bills in the same currency.']]);
            }
        }

        $byInvoice = [];
        $total = '0.00';
        $settlement = app(InvoiceSettlementService::class);
        foreach ($invoices->sortBy('id') as $invoice) {
            $remaining = $settlement->outstanding($invoice);
            foreach ($lots as $lot) {
                if (bccomp($remaining, '0.00', 2) <= 0) {
                    break;
                }
                $available = $this->availableForLot($lot);
                if (bccomp($available, '0.00', 2) <= 0) {
                    continue;
                }
                $amount = bccomp($available, $remaining, 2) < 0 ? $available : $remaining;
                CustomerPaymentCreditMovement::create([
                    'credit_id' => $lot->id,
                    'invoice_id' => $invoice->id,
                    'type' => 'APPLIED',
                    'amount' => $amount,
                    'source_key' => hash('sha256', $sourceKey.':'.$lot->id.':'.$invoice->id),
                    'checkout_source_key' => $sourceKey,
                    'actor_user_id' => $actor->id,
                ]);
                $remaining = bcsub($remaining, $amount, 2);
                $total = bcadd($total, $amount, 2);
                $byInvoice[$invoice->id] = bcadd($byInvoice[$invoice->id] ?? '0.00', $amount, 2);
            }
        }

        return ['total' => $total, 'by_invoice' => $byInvoice];
    }

    public function reverseUnusedForReceipt(Receipt $receipt, User $actor): void
    {
        $lot = CustomerPaymentCredit::where('source_receipt_id', $receipt->id)->lockForUpdate()->first();
        if (! $lot) {
            return;
        }
        if ($lot->organization_id !== $actor->organization_id || $lot->movements()->where('type', 'APPLIED')->exists()) {
            throw ValidationException::withMessages(['receipt' => ['This receipt has payment credit already applied to a later bill. Reconcile it before reversal.']]);
        }
        $available = $this->availableForLot($lot);
        if (bccomp($available, '0.00', 2) > 0) {
            CustomerPaymentCreditMovement::create([
                'credit_id' => $lot->id,
                'type' => 'REVERSED',
                'amount' => $available,
                'source_key' => 'reversal:'.$receipt->id,
                'actor_user_id' => $actor->id,
            ]);
        }
    }

    /** @param array<int, int> $invoiceIds @return array<int, string> */
    public function applicationsForInvoices(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }
        $totals = [];
        $rows = CustomerPaymentCreditMovement::where('type', 'APPLIED')->whereIn('invoice_id', $invoiceIds)
            ->whereHas('credit.sourceReceipt', fn ($query) => $query->where('status', 'POSTED'))
            ->get();
        foreach ($rows as $row) {
            $invoiceId = (int) $row->invoice_id;
            $totals[$invoiceId] = bcadd($totals[$invoiceId] ?? '0.00', (string) $row->amount, 2);
        }

        return $totals;
    }

    public function availableForLot(CustomerPaymentCredit $lot): string
    {
        $balance = '0.00';
        foreach ($lot->movements()->get() as $movement) {
            $balance = $movement->type === 'CREATED'
                ? bcadd($balance, (string) $movement->amount, 2)
                : bcsub($balance, (string) $movement->amount, 2);
        }

        return $balance;
    }
}
