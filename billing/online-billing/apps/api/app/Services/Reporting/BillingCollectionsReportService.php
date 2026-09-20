<?php

namespace App\Services\Reporting;

use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Read-only operational register for issued invoices and posted collections.
 *
 * Issuance and collection are intentionally reported as different fact streams. The report
 * never treats their period difference as a customer balance and does not replace fiscal
 * books, BIR exports, or the immutable document snapshots/artifacts.
 */
class BillingCollectionsReportService
{
    public const TIMEZONE = 'Asia/Manila';

    public const ROW_LIMIT = 10000;

    /** @var array<int, string> */
    public const INVOICE_STATUSES = ['POSTED', 'CANCELLED'];

    /** @var array<int, string> */
    public const RECEIPT_STATUSES = ['POSTED', 'REVERSED', 'VOID'];

    /** @param array<string, mixed> $filters */
    public function build(User $actor, array $filters): array
    {
        $resolved = $this->resolveFilters($actor, $filters);
        $invoices = $this->invoiceQuery($actor, $resolved)->with('customer:id,account_number,name')->get();
        $receipts = $this->receiptQuery($actor, $resolved)->with('customer:id,account_number,name')->get();
        $rowCount = $invoices->count() + $receipts->count();

        if ($rowCount > self::ROW_LIMIT) {
            throw ValidationException::withMessages([
                'date_from' => ["This report has {$rowCount} rows. Narrow the date, location, customer, or status filters before exporting more than ".self::ROW_LIMIT.' rows.'],
            ]);
        }

        $rows = $this->buildRows($invoices, $receipts);
        $page = (int) $resolved['page'];
        $perPage = (int) $resolved['per_page'];

        return [
            'report' => [
                'code' => 'BILLING_COLLECTIONS_REGISTER',
                'title' => 'Billing & Collections Register',
                'generated_at' => Carbon::now(self::TIMEZONE)->toIso8601String(),
                'timezone' => self::TIMEZONE,
                'filters' => $this->publicFilters($resolved),
                'scope_notice' => 'Only documents inside the caller organization and authorized location scope are included.',
                'interpretation_notice' => 'Invoice issuance and posted collections are separate activity streams. Their difference for this period is not an account balance, aging result, or fiscal reconciliation.',
                'row_limit' => self::ROW_LIMIT,
            ],
            'totals_by_currency' => $this->aggregateRows($rows),
            'rows' => array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($rows),
                'last_page' => max(1, (int) ceil(count($rows) / $perPage)),
            ],
            // Used internally for the matching CSV only; the controller does not expose this key.
            '_all_rows' => $rows,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportCsv(User $actor, array $filters): array
    {
        $report = $this->build($actor, $filters);
        $csv = $this->csvForRows($report['_all_rows']);
        unset($report['_all_rows']);

        $this->recordExportAudit($actor, $report, $csv);

        return [
            'content' => $csv,
            'filename' => sprintf(
                'billing-collections-%s-to-%s.csv',
                $report['report']['filters']['date_from'],
                $report['report']['filters']['date_to'],
            ),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function resolveFilters(User $actor, array $filters): array
    {
        $today = Carbon::now(self::TIMEZONE)->startOfDay();
        $from = isset($filters['date_from'])
            ? Carbon::createFromFormat('Y-m-d', (string) $filters['date_from'], self::TIMEZONE)->startOfDay()
            : $today->copy()->startOfMonth();
        $to = isset($filters['date_to'])
            ? Carbon::createFromFormat('Y-m-d', (string) $filters['date_to'], self::TIMEZONE)->startOfDay()
            : $today;
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['date_to' => ['The end date must not be before the start date.']]);
        }

        $locationId = isset($filters['location_id']) ? (int) $filters['location_id'] : null;
        if ($locationId !== null && ! $actor->canAccessLocation($locationId)) {
            abort(403, 'You cannot report on this location.');
        }

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'location_id' => $locationId,
            'customer_id' => isset($filters['customer_id']) ? (int) $filters['customer_id'] : null,
            'invoice_statuses' => $filters['invoice_statuses'] ?? ['POSTED'],
            'receipt_statuses' => $filters['receipt_statuses'] ?? ['POSTED'],
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($filters['per_page'] ?? 50))),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function invoiceQuery(User $actor, array $filters): Builder
    {
        $query = Invoice::query()
            ->where('organization_id', $actor->organization_id)
            ->whereBetween('business_date', [$filters['date_from'], $filters['date_to']])
            ->whereIn('status', $filters['invoice_statuses']);

        return $this->applyScope($query, $actor, $filters);
    }

    /** @param array<string, mixed> $filters */
    private function receiptQuery(User $actor, array $filters): Builder
    {
        $query = Receipt::query()
            ->where('organization_id', $actor->organization_id)
            ->whereBetween('business_date', [$filters['date_from'], $filters['date_to']])
            ->whereIn('status', $filters['receipt_statuses']);

        return $this->applyScope($query, $actor, $filters);
    }

    /** @param array<string, mixed> $filters */
    private function applyScope(Builder $query, User $actor, array $filters): Builder
    {
        if ($filters['customer_id'] !== null) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if ($filters['location_id'] !== null) {
            return $query->where('location_id', $filters['location_id']);
        }
        if (! $actor->hasRole('Administrator')) {
            $query->whereIn('location_id', $actor->locations()->pluck('locations.id')->all());
        }

        return $query;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildRows(Collection $invoices, Collection $receipts): array
    {
        $rows = $invoices->map(fn (Invoice $invoice): array => [
            'document_type' => 'INVOICE',
            'document_id' => $invoice->id,
            'document_number' => $invoice->invoice_number,
            'business_date' => $invoice->business_date?->toDateString(),
            'status' => $invoice->status,
            'customer_id' => $invoice->customer_id,
            'customer_account_number' => $invoice->customer?->account_number,
            'customer_name' => $invoice->buyer_snapshot_name ?? $invoice->customer?->name,
            'currency' => $invoice->currency,
            'billed_amount' => (string) $invoice->total_charge_amount,
            'gross_amount' => (string) $invoice->gross_amount,
            'ppa_amount' => (string) $invoice->ppa_amount,
            'discount_amount' => (string) $invoice->discount_amount,
            'tax_amount' => (string) $invoice->tax_amount,
            'cash_received_amount' => '0.00',
            'withholding_received_amount' => '0.00',
            'applied_amount' => '0.00',
            'unapplied_amount' => '0.00',
        ])->all();

        $rows = array_merge($rows, $receipts->map(fn (Receipt $receipt): array => [
            'document_type' => 'RECEIPT',
            'document_id' => $receipt->id,
            'document_number' => $receipt->receipt_number,
            'business_date' => $receipt->business_date?->toDateString(),
            'status' => $receipt->status,
            'customer_id' => $receipt->customer_id,
            'customer_account_number' => $receipt->customer?->account_number,
            'customer_name' => $receipt->payer_snapshot['registered_name'] ?? $receipt->payer_snapshot['name'] ?? $receipt->customer?->name,
            'currency' => $receipt->currency,
            'billed_amount' => '0.00',
            'gross_amount' => '0.00',
            'ppa_amount' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'cash_received_amount' => (string) $receipt->cash_received_amount,
            'withholding_received_amount' => (string) $receipt->withholding_received_amount,
            'applied_amount' => (string) $receipt->applied_amount,
            'unapplied_amount' => (string) $receipt->unapplied_amount,
        ])->all());

        usort($rows, static function (array $left, array $right): int {
            return [$right['business_date'], $right['document_type'], $right['document_id']] <=> [$left['business_date'], $left['document_type'], $left['document_id']];
        });

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function aggregateRows(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $currency = $row['currency'];
            $totals[$currency] ??= [
                'currency' => $currency,
                'invoices' => ['count' => 0, 'billed_amount' => '0.00', 'gross_amount' => '0.00', 'ppa_amount' => '0.00', 'discount_amount' => '0.00', 'tax_amount' => '0.00'],
                'receipts' => ['count' => 0, 'cash_received_amount' => '0.00', 'withholding_received_amount' => '0.00', 'applied_amount' => '0.00', 'unapplied_amount' => '0.00'],
            ];
            $bucket = $row['document_type'] === 'INVOICE' ? 'invoices' : 'receipts';
            $totals[$currency][$bucket]['count']++;
            foreach ($totals[$currency][$bucket] as $key => $value) {
                if ($key !== 'count') {
                    $totals[$currency][$bucket][$key] = bcadd($value, (string) $row[$key], 2);
                }
            }
        }

        return array_values($totals);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function csvForRows(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Document type', 'Business date', 'Document number', 'Status', 'Customer account', 'Customer / payer', 'Currency', 'Billed amount', 'Gross amount', 'PPA amount', 'Discount amount', 'Tax amount', 'Cash received', 'Withholding received', 'Applied amount', 'Unapplied amount'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($value) => $this->safeCsvValue($value), [
                $row['document_type'], $row['business_date'], $row['document_number'], $row['status'],
                $row['customer_account_number'], $row['customer_name'], $row['currency'], $row['billed_amount'],
                $row['gross_amount'], $row['ppa_amount'], $row['discount_amount'], $row['tax_amount'],
                $row['cash_received_amount'], $row['withholding_received_amount'], $row['applied_amount'], $row['unapplied_amount'],
            ]), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false ? '' : $content;
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'{$value}" : $value;
    }

    /** @param array<string, mixed> $resolved */
    private function publicFilters(array $resolved): array
    {
        return [
            'date_from' => $resolved['date_from'],
            'date_to' => $resolved['date_to'],
            'location_id' => $resolved['location_id'],
            'customer_id' => $resolved['customer_id'],
            'invoice_statuses' => array_values($resolved['invoice_statuses']),
            'receipt_statuses' => array_values($resolved['receipt_statuses']),
        ];
    }

    /** @param array<string, mixed> $report */
    private function recordExportAudit(User $actor, array $report, string $content): void
    {
        AuditEvent::create([
            'organization_id' => $actor->organization_id,
            'location_id' => $report['report']['filters']['location_id'],
            'event_type' => 'BILLING_COLLECTIONS_REPORT_EXPORTED',
            'aggregate_type' => 'REPORT_EXPORT',
            'aggregate_id' => 0,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => 'reports:export',
            'occurred_at' => Carbon::now(self::TIMEZONE),
            'business_date' => $report['report']['filters']['date_to'],
            'reason' => 'Scoped Billing & Collections Register CSV export',
            'metadata' => [
                'report_code' => $report['report']['code'],
                'filters' => $report['report']['filters'],
                'row_count' => $report['pagination']['total'],
                'content_sha256' => hash('sha256', $content),
            ],
        ]);
    }
}
