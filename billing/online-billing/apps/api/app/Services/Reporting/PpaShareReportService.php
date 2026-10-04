<?php

namespace App\Services\Reporting;

use App\Models\Invoice;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read-only PPA share register for fully paid posted invoices.
 * Amounts come from the issued invoice and posted allocations. This report does not
 * recalculate tariffs or change settlement.
 */
class PpaShareReportService
{
    public const TIMEZONE = 'Asia/Manila';

    public const ROW_LIMIT = 10000;

    /** @param array<string, mixed> $filters */
    public function build(User $actor, array $filters): array
    {
        $resolved = $this->resolveFilters($filters);
        $query = Invoice::query()
            ->where('organization_id', $actor->organization_id)
            ->where('status', 'POSTED')
            ->whereBetween('business_date', [$resolved['date_from'], $resolved['date_to']]);

        if (! $actor->hasRole('Administrator')) {
            $locationIds = $actor->locations()->pluck('locations.id')->all();
            $query->whereIn('location_id', $locationIds === [] ? [-1] : $locationIds);
        }

        $candidateCount = (clone $query)->count();
        if ($candidateCount > self::ROW_LIMIT) {
            throw ValidationException::withMessages([
                'date_from' => ["This report has {$candidateCount} bills. Narrow the date range before it can list more than ".self::ROW_LIMIT.' bills.'],
            ]);
        }

        $invoices = $query->with('customer:id,account_number,name')->orderByDesc('business_date')->orderByDesc('id')->get();
        $applied = $this->appliedByInvoice($invoices->pluck('id')->all());
        $receipts = $this->receiptsByInvoice($invoices->pluck('id')->all());
        $paid = $invoices->filter(function (Invoice $invoice) use ($applied): bool {
            $settled = $applied[$invoice->id] ?? '0.00';
            $outstanding = bcsub((string) $invoice->total_charge_amount, $settled, 2);

            return bccomp($settled, '0.00', 2) > 0 && bccomp($outstanding, '0.00', 2) <= 0;
        })->values();

        $rows = $paid->map(function (Invoice $invoice) use ($applied, $receipts): array {
            return [
                'invoice_number' => $invoice->invoice_number,
                'business_date' => $invoice->business_date?->toDateString(),
                'customer_name' => $invoice->customer?->name,
                'customer_account_number' => $invoice->customer?->account_number,
                'currency' => $invoice->currency,
                'invoice_total' => $this->money($invoice->total_charge_amount),
                'ppa_share' => $this->money($invoice->ppa_amount),
                'applied_amount' => $applied[$invoice->id] ?? '0.00',
                'receipts' => $receipts[$invoice->id] ?? [],
            ];
        })->all();

        $page = $resolved['page'];
        $perPage = $resolved['per_page'];

        return [
            'report' => [
                'code' => 'PPA_SHARE_PAID_BILLS',
                'title' => 'PPA Share of Paid Bills',
                'generated_at' => Carbon::now(self::TIMEZONE)->toIso8601String(),
                'timezone' => self::TIMEZONE,
                'filters' => [
                    'date_from' => $resolved['date_from'],
                    'date_to' => $resolved['date_to'],
                ],
                'notice' => 'Fully paid posted bills in your location. PPA share is the amount stored on the issued bill. Reversed receipts are not treated as payment, and this report does not post or release anything.',
            ],
            'totals_by_currency' => $this->totals($rows),
            'rows' => array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($rows),
                'last_page' => max(1, (int) ceil(count($rows) / $perPage)),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function resolveFilters(array $filters): array
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

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($filters['per_page'] ?? 25))),
        ];
    }

    /** @param array<int, int> $invoiceIds
     * @return array<int, string>
     */
    private function appliedByInvoice(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return ReceiptAllocation::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
            ->select('invoice_id', DB::raw('SUM(applied_amount) as amount'))
            ->groupBy('invoice_id')
            ->pluck('amount', 'invoice_id')
            ->map(fn ($amount) => $this->money($amount))
            ->all();
    }

    /** @param array<int, int> $invoiceIds
     * @return array<int, array<int, array<string, string|null>>>
     */
    private function receiptsByInvoice(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $grouped = [];
        $allocations = ReceiptAllocation::query()
            ->select('receipt_allocations.invoice_id', 'receipt_allocations.applied_amount', 'receipts.receipt_number', 'receipts.business_date')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->whereIn('receipt_allocations.invoice_id', $invoiceIds)
            ->where('receipts.status', 'POSTED')
            ->orderByDesc('receipts.business_date')
            ->orderByDesc('receipts.id')
            ->get();

        foreach ($allocations as $allocation) {
            $grouped[(int) $allocation->invoice_id][] = [
                'receipt_number' => $allocation->receipt_number,
                'business_date' => $allocation->business_date ? Carbon::parse($allocation->business_date)->toDateString() : null,
                'applied_amount' => $this->money($allocation->applied_amount),
            ];
        }

        return $grouped;
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, int|string>>
     */
    private function totals(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $currency = (string) $row['currency'];
            if (! isset($totals[$currency])) {
                $totals[$currency] = [
                    'currency' => $currency,
                    'paid_bill_count' => 0,
                    'invoice_total' => '0.00',
                    'ppa_share' => '0.00',
                ];
            }
            $totals[$currency]['paid_bill_count']++;
            $totals[$currency]['invoice_total'] = bcadd($totals[$currency]['invoice_total'], (string) $row['invoice_total'], 2);
            $totals[$currency]['ppa_share'] = bcadd($totals[$currency]['ppa_share'], (string) $row['ppa_share'], 2);
        }

        return array_values($totals);
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }
}
