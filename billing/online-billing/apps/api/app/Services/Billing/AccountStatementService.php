<?php

namespace App\Services\Billing;

use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerPaymentCreditMovement;
use App\Models\Invoice;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountStatementService
{
    public function generate(User $actor, int $customerId, Carbon|string $asOfDate, ?int $locationId = null): AccountStatement
    {
        $asOf = $asOfDate instanceof Carbon ? $asOfDate->toDateString() : $asOfDate;

        return DB::transaction(function () use ($actor, $customerId, $asOf, $locationId): AccountStatement {
            $customer = Customer::where('organization_id', $actor->organization_id)->whereKey($customerId)->lockForUpdate()->firstOrFail();
            if (! $customer->isActive()) {
                throw ValidationException::withMessages(['customer_id' => ['An account statement requires an active customer.']]);
            }

            $invoices = Invoice::where('organization_id', $actor->organization_id)->where('customer_id', $customer->id)
                ->where('status', 'POSTED')->whereDate('business_date', '<=', $asOf)
                ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
                ->orderBy('id')->lockForUpdate()->get();
            $allocations = ReceiptAllocation::query()->with('receipt:id,receipt_number,business_date,status')
                ->whereIn('invoice_id', $invoices->pluck('id'))
                ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED')->whereDate('business_date', '<=', $asOf))
                ->get()->groupBy('invoice_id');
            $asOfEndUtc = Carbon::parse($asOf, 'Asia/Manila')->endOfDay()->utc();
            $creditApplications = CustomerPaymentCreditMovement::where('type', 'APPLIED')
                ->whereIn('invoice_id', $invoices->pluck('id'))
                ->where('created_at', '<=', $asOfEndUtc)
                ->whereHas('credit.sourceReceipt', fn ($query) => $query->where('status', 'POSTED'))
                ->get()->groupBy('invoice_id');

            $items = [];
            $invoiceTotal = '0.00';
            $paymentTotal = '0.00';
            $cashTotal = '0.00';
            $withholdingTotal = '0.00';
            $outstandingTotal = '0.00';
            foreach ($invoices as $invoice) {
                $paid = '0.00';
                $cash = '0.00';
                $withholding = '0.00';
                $receipts = [];
                foreach ($allocations->get($invoice->id, collect()) as $allocation) {
                    $applied = $this->money($allocation->applied_amount);
                    $appliedCash = $this->money($allocation->cash_applied_amount);
                    $appliedWithholding = $this->money($allocation->withholding_applied_amount);
                    $paid = bcadd($paid, $applied, 2);
                    $cash = bcadd($cash, $appliedCash, 2);
                    $withholding = bcadd($withholding, $appliedWithholding, 2);
                    $receipts[] = [
                        'receipt_number' => $allocation->receipt?->receipt_number,
                        'business_date' => $allocation->receipt?->business_date?->toDateString(),
                        'cash_applied_amount' => $appliedCash,
                        'withholding_applied_amount' => $appliedWithholding,
                        'applied_amount' => $applied,
                    ];
                }
                $creditApplied = '0.00';
                foreach ($creditApplications->get($invoice->id, collect()) as $application) {
                    $creditApplied = bcadd($creditApplied, (string) $application->amount, 2);
                }
                $paid = bcadd($paid, $creditApplied, 2);
                $amount = $this->money($invoice->total_charge_amount);
                $outstanding = bcsub($amount, $paid, 2);
                if (bccomp($outstanding, '0.00', 2) <= 0) {
                    continue;
                }
                $items[] = [
                    'invoice' => $invoice,
                    'amount' => $amount,
                    'paid' => $paid,
                    'cash' => $cash,
                    'withholding' => $withholding,
                    'credit' => $creditApplied,
                    'outstanding' => $outstanding,
                    'receipts' => $receipts,
                ];
                $invoiceTotal = bcadd($invoiceTotal, $amount, 2);
                $paymentTotal = bcadd($paymentTotal, $paid, 2);
                $cashTotal = bcadd($cashTotal, $cash, 2);
                $withholdingTotal = bcadd($withholdingTotal, $withholding, 2);
                $outstandingTotal = bcadd($outstandingTotal, $outstanding, 2);
            }
            if ($items === []) {
                throw ValidationException::withMessages(['customer_id' => ['This customer has no outstanding posted invoices as of the requested date.']]);
            }

            $statement = AccountStatement::create([
                'organization_id' => $actor->organization_id, 'location_id' => $locationId, 'customer_id' => $customer->id,
                'statement_number' => 'SOA-'.strtoupper(Str::uuid()), 'as_of_date' => $asOf, 'currency' => 'PHP',
                'invoice_total' => $invoiceTotal, 'payment_total' => $paymentTotal,
                'cash_applied_total' => $cashTotal, 'withholding_applied_total' => $withholdingTotal,
                'outstanding_total' => $outstandingTotal,
                'customer_snapshot' => ['account_number' => $customer->account_number, 'name' => $customer->name],
                'status' => 'GENERATED', 'generated_by_user_id' => $actor->id, 'generated_at' => now(),
            ]);
            foreach ($items as $item) {
                AccountStatementItem::create([
                    'statement_id' => $statement->id, 'invoice_id' => $item['invoice']->id, 'invoice_number' => $item['invoice']->invoice_number,
                    'business_date' => $item['invoice']->business_date, 'invoice_amount' => $item['amount'], 'payment_amount' => $item['paid'], 'outstanding_amount' => $item['outstanding'],
                    'snapshot' => [
                        'invoice_lock_version' => $item['invoice']->lock_version,
                        'currency' => $item['invoice']->currency,
                        'days_open' => $this->daysOpen($item['invoice']->business_date, $asOf),
                        'buyer_name' => $item['invoice']->buyer_snapshot_name,
                        'buyer_tin' => $item['invoice']->buyer_snapshot_tin,
                        'vessel_name' => $item['invoice']->vessel_name,
                        'voyage' => $item['invoice']->voyage,
                        'net_amount' => $this->money($item['invoice']->net_amount),
                        'fuel_surcharge_amount' => $this->money($item['invoice']->fuel_surcharge_amount),
                        'ppa_amount' => $this->money($item['invoice']->ppa_amount),
                        'discount_amount' => $this->money($item['invoice']->discount_amount),
                        'tax_amount' => $this->money($item['invoice']->tax_amount),
                        'cash_applied_amount' => $item['cash'],
                        'withholding_applied_amount' => $item['withholding'],
                        'customer_payment_credit_applied_amount' => $item['credit'],
                        'receipts' => $item['receipts'],
                    ],
                ]);
            }
            AuditEvent::create(['organization_id' => $actor->organization_id, 'location_id' => $locationId, 'event_type' => 'ACCOUNT_STATEMENT_GENERATED', 'aggregate_type' => 'ACCOUNT_STATEMENT', 'aggregate_id' => $statement->id, 'aggregate_version' => 1, 'actor_type' => 'user', 'actor_id' => $actor->id, 'permission_snapshot' => 'statements:generate', 'occurred_at' => now(), 'business_date' => $asOf, 'reason' => 'As-of account statement snapshot generated', 'metadata' => ['statement_number' => $statement->statement_number, 'customer_id' => $customer->id, 'outstanding_total' => $outstandingTotal]]);

            return $statement->load('items');
        });
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }

    private function daysOpen(mixed $businessDate, string $asOf): string
    {
        $from = Carbon::parse($businessDate)->startOfDay();
        $to = Carbon::parse($asOf)->startOfDay();

        return (string) (int) $from->diffInDays($to);
    }
}
