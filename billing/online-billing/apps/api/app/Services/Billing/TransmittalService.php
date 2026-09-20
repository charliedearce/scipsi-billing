<?php

namespace App\Services\Billing;

use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Transmittal;
use App\Models\User;
use App\Models\WhiteTransmittalItem;
use App\Models\YellowTransmittalItem;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransmittalService
{
    /**
     * Generate a non-fiscal, immutable source-membership snapshot.
     *
     * This intentionally does not allocate a fiscal document number, change an invoice or receipt,
     * create a payment allocation, or infer the legacy white-transmittal BIR 2307/income formula.
     * The latter needs an accountant-approved mapping to the current withholding model.
     */
    public function generate(User $actor, string $kind, array $sourceIds, Carbon|string $asOfDate, ?int $locationId): Transmittal
    {
        $this->assertKind($kind);
        $sourceIds = $this->normalizedSourceIds($sourceIds, $kind === Transmittal::KIND_YELLOW_INVOICE ? 'invoice_ids' : 'receipt_ids');
        $asOf = $asOfDate instanceof Carbon ? $asOfDate->toDateString() : $asOfDate;

        return DB::transaction(function () use ($actor, $kind, $sourceIds, $asOf, $locationId): Transmittal {
            if ($kind === Transmittal::KIND_YELLOW_INVOICE) {
                return $this->generateYellow($actor, $sourceIds, $asOf, $locationId);
            }

            return $this->generateWhite($actor, $sourceIds, $asOf, $locationId);
        });
    }

    public function eligibleSources(User $actor, string $kind, Carbon|string $asOfDate, int $locationId, int $perPage = 50): LengthAwarePaginator
    {
        $this->assertKind($kind);
        $asOf = $asOfDate instanceof Carbon ? $asOfDate->toDateString() : $asOfDate;
        $perPage = max(1, min($perPage, 100));

        if ($kind === Transmittal::KIND_YELLOW_INVOICE) {
            return $this->eligibleInvoiceQuery($actor, $asOf, $locationId)
                ->orderByDesc('business_date')->orderByDesc('id')->paginate($perPage)
                ->through(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'source_number' => $invoice->invoice_number,
                    'business_date' => $invoice->business_date?->toDateString(),
                    'currency' => $invoice->currency,
                    'party_name' => $invoice->buyer_snapshot_name,
                    'amount' => (string) $invoice->total_charge_amount,
                ]);
        }

        return $this->eligibleReceiptQuery($actor, $asOf, $locationId)
            ->orderByDesc('business_date')->orderByDesc('id')->paginate($perPage)
            ->through(fn (Receipt $receipt): array => [
                'id' => $receipt->id,
                'source_number' => $receipt->receipt_number,
                'business_date' => $receipt->business_date?->toDateString(),
                'currency' => $receipt->currency,
                'party_name' => $receipt->payer_snapshot['registered_name'] ?? $receipt->payer_snapshot['name'] ?? null,
                'amount' => (string) $receipt->applied_amount,
            ]);
    }

    private function generateYellow(User $actor, array $invoiceIds, string $asOf, ?int $locationId): Transmittal
    {
        $invoices = $this->eligibleInvoiceQuery($actor, $asOf, $locationId)
            ->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get();
        if ($invoices->count() !== count($invoiceIds)) {
            throw ValidationException::withMessages(['invoice_ids' => ['Each selected invoice must be posted, in your organization and location, and dated on or before the as-of date.']]);
        }

        $currency = $this->singleCurrency($invoices->pluck('currency')->all(), 'invoice_ids');
        $invoiceTotal = '0.00';
        foreach ($invoices as $invoice) {
            $invoiceTotal = bcadd($invoiceTotal, (string) $invoice->total_charge_amount, 2);
        }

        $transmittal = $this->createHeader($actor, Transmittal::KIND_YELLOW_INVOICE, $asOf, $locationId, $currency, count($invoiceIds), [
            'invoice_total' => $invoiceTotal,
            'meaning' => 'Sum of captured posted invoice total_charge_amount values; not a settlement or tax computation.',
        ]);
        foreach ($invoices as $invoice) {
            YellowTransmittalItem::create([
                'transmittal_id' => $transmittal->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'business_date' => $invoice->business_date,
                'currency' => $invoice->currency,
                'total_charge_amount' => $invoice->total_charge_amount,
                'buyer_snapshot' => $invoice->buyer_snapshot ?? [],
                'source_snapshot' => [
                    'source_status' => $invoice->status,
                    'invoice_lock_version' => $invoice->lock_version,
                    'accounting_period_id' => $invoice->accounting_period_id,
                    'sale_type' => $invoice->sale_type,
                ],
            ]);
        }

        $this->auditGenerated($transmittal, $actor, $asOf, ['invoice_total' => $invoiceTotal]);

        return $transmittal->load('yellowItems');
    }

    private function generateWhite(User $actor, array $receiptIds, string $asOf, ?int $locationId): Transmittal
    {
        $receipts = $this->eligibleReceiptQuery($actor, $asOf, $locationId)
            ->whereIn('id', $receiptIds)->orderBy('id')->lockForUpdate()->get();
        if ($receipts->count() !== count($receiptIds)) {
            throw ValidationException::withMessages(['receipt_ids' => ['Each selected receipt must be posted, in your organization and location, and dated on or before the as-of date.']]);
        }

        $currency = $this->singleCurrency($receipts->pluck('currency')->all(), 'receipt_ids');
        $cashTotal = '0.00';
        $withholdingTotal = '0.00';
        $appliedTotal = '0.00';
        $unappliedTotal = '0.00';
        foreach ($receipts as $receipt) {
            $cashTotal = bcadd($cashTotal, (string) $receipt->cash_received_amount, 2);
            $withholdingTotal = bcadd($withholdingTotal, (string) $receipt->withholding_received_amount, 2);
            $appliedTotal = bcadd($appliedTotal, (string) $receipt->applied_amount, 2);
            $unappliedTotal = bcadd($unappliedTotal, (string) $receipt->unapplied_amount, 2);
        }

        $transmittal = $this->createHeader($actor, Transmittal::KIND_WHITE_RECEIPT, $asOf, $locationId, $currency, count($receiptIds), [
            'cash_received_total' => $cashTotal,
            'withholding_received_total' => $withholdingTotal,
            'applied_total' => $appliedTotal,
            'unapplied_total' => $unappliedTotal,
            'meaning' => 'Captured collection-receipt settlement figures; no BIR 2307/income classification is inferred.',
        ]);
        foreach ($receipts as $receipt) {
            WhiteTransmittalItem::create([
                'transmittal_id' => $transmittal->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'business_date' => $receipt->business_date,
                'currency' => $receipt->currency,
                'cash_received_amount' => $receipt->cash_received_amount,
                'withholding_received_amount' => $receipt->withholding_received_amount,
                'applied_amount' => $receipt->applied_amount,
                'unapplied_amount' => $receipt->unapplied_amount,
                'payer_snapshot' => $receipt->payer_snapshot,
                'source_snapshot' => [
                    'source_status' => $receipt->status,
                    'receipt_lock_version' => $receipt->lock_version,
                    'accounting_period_id' => $receipt->accounting_period_id,
                    'receipt_location_id' => $receipt->location_id,
                    'resolved_transmittal_location_id' => $locationId,
                ],
            ]);
        }

        $this->auditGenerated($transmittal, $actor, $asOf, [
            'cash_received_total' => $cashTotal,
            'withholding_received_total' => $withholdingTotal,
            'applied_total' => $appliedTotal,
            'unapplied_total' => $unappliedTotal,
        ]);

        return $transmittal->load('whiteItems');
    }

    private function eligibleInvoiceQuery(User $actor, string $asOf, ?int $locationId): Builder
    {
        return Invoice::query()
            ->where('organization_id', $actor->organization_id)
            ->where('status', 'POSTED')
            ->whereDate('business_date', '<=', $asOf)
            ->when($locationId !== null, fn (Builder $query) => $query->where('location_id', $locationId));
    }

    private function eligibleReceiptQuery(User $actor, string $asOf, ?int $locationId): Builder
    {
        return Receipt::query()
            ->where('organization_id', $actor->organization_id)
            ->where('status', 'POSTED')
            ->whereDate('business_date', '<=', $asOf)
            ->when($locationId !== null, function (Builder $query) use ($locationId): void {
                // P3 receipts created before their own location is captured still have one or more
                // allocated invoices. Include a location-less receipt only when every allocated
                // invoice belongs to this location; mixed-location receipts never leak into a
                // branch transmittal. New receipt headers with a location use that captured scope.
                $query->where(function (Builder $scoped) use ($locationId): void {
                    $scoped->where('location_id', $locationId)
                        ->orWhere(function (Builder $derived) use ($locationId): void {
                            $derived->whereNull('location_id')
                                ->whereHas('allocations.invoice', fn (Builder $invoice) => $invoice->where('location_id', $locationId))
                                ->whereDoesntHave('allocations.invoice', function (Builder $invoice) use ($locationId): void {
                                    $invoice->where(function (Builder $differentLocation) use ($locationId): void {
                                        $differentLocation->where('location_id', '<>', $locationId)->orWhereNull('location_id');
                                    });
                                });
                        });
                });
            });
    }

    private function createHeader(User $actor, string $kind, string $asOf, ?int $locationId, string $currency, int $sourceCount, array $summary): Transmittal
    {
        $prefix = $kind === Transmittal::KIND_YELLOW_INVOICE ? 'YTR' : 'WTR';

        return Transmittal::create([
            'organization_id' => $actor->organization_id,
            'location_id' => $locationId,
            'transmittal_number' => $prefix.'-'.strtoupper((string) Str::ulid()),
            'kind' => $kind,
            'as_of_date' => $asOf,
            'currency' => $currency,
            'source_item_count' => $sourceCount,
            'summary' => $summary,
            'status' => 'GENERATED',
            'generated_by_user_id' => $actor->id,
            'generated_at' => now(),
        ]);
    }

    private function auditGenerated(Transmittal $transmittal, User $actor, string $asOf, array $totals): void
    {
        AuditEvent::create([
            'organization_id' => $actor->organization_id,
            'location_id' => $transmittal->location_id,
            'event_type' => 'TRANSMITTAL_GENERATED',
            'aggregate_type' => 'TRANSMITTAL',
            'aggregate_id' => $transmittal->id,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => 'transmittals:generate',
            'occurred_at' => now(),
            'business_date' => $asOf,
            'reason' => 'Immutable transmittal source-membership snapshot generated',
            'metadata' => array_merge([
                'transmittal_number' => $transmittal->transmittal_number,
                'kind' => $transmittal->kind,
                'source_item_count' => $transmittal->source_item_count,
                'currency' => $transmittal->currency,
            ], $totals),
        ]);
    }

    private function singleCurrency(array $currencies, string $field): string
    {
        $currencies = array_values(array_unique(array_map(fn ($currency) => strtoupper((string) $currency), $currencies)));
        if (count($currencies) !== 1) {
            throw ValidationException::withMessages([$field => ['A transmittal may contain source documents in exactly one currency.']]);
        }

        return $currencies[0];
    }

    private function normalizedSourceIds(array $sourceIds, string $field): array
    {
        $sourceIds = array_map(fn ($id) => (int) $id, $sourceIds);
        if ($sourceIds === [] || in_array(0, $sourceIds, true)) {
            throw ValidationException::withMessages([$field => ['Select at least one valid source document.']]);
        }
        if (count($sourceIds) !== count(array_unique($sourceIds))) {
            throw ValidationException::withMessages([$field => ['A source document may appear only once in a transmittal.']]);
        }

        return $sourceIds;
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, [Transmittal::KIND_YELLOW_INVOICE, Transmittal::KIND_WHITE_RECEIPT], true)) {
            throw ValidationException::withMessages(['kind' => ['The transmittal kind is not supported.']]);
        }
    }
}
