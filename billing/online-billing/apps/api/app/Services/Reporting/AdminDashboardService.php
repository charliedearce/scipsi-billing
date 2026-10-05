<?php

namespace App\Services\Reporting;

use App\Models\BillingRequest;
use App\Models\DocumentCorrectionRequest;
use App\Models\Invoice;
use App\Models\ManualPaymentSubmission;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only organization snapshot for the administrator overview.
 *
 * Today's bills and today's collections stay separate. Open bills are the current
 * unpaid posted balance, not a period profit, aging result, or fiscal book.
 */
class AdminDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    /** Number of Asia/Manila days in the activity series, ending on the business date. */
    public const SERIES_DAYS = 7;

    /** @return array<string, mixed> */
    public function build(User $actor): array
    {
        if (! $actor->hasRole('Administrator')) {
            abort(403, 'Only an administrator can view the organization dashboard.');
        }

        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $organizationId = (int) $actor->organization_id;
        $seriesDates = $this->seriesDates($today);

        $invoices = Invoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', Invoice::STATUS_POSTED)
            ->whereDate('business_date', $today)
            ->get(['id', 'currency', 'total_charge_amount', 'posted_by_user_id']);

        $receipts = Receipt::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'POSTED')
            ->whereDate('business_date', $today)
            ->get([
                'id',
                'currency',
                'receipt_kind',
                'counts_as_official_receipt',
                'cash_received_amount',
                'withholding_received_amount',
                'posted_by_user_id',
            ]);

        $seriesInvoices = Invoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', Invoice::STATUS_POSTED)
            ->whereBetween('business_date', [$seriesDates[0], $today])
            ->get(['id', 'currency', 'total_charge_amount', 'business_date']);

        $seriesReceipts = Receipt::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'POSTED')
            ->whereBetween('business_date', [$seriesDates[0], $today])
            ->get([
                'id',
                'currency',
                'receipt_kind',
                'counts_as_official_receipt',
                'cash_received_amount',
                'withholding_received_amount',
                'business_date',
            ]);

        return [
            'business_date' => $today,
            'timezone' => self::TIMEZONE,
            'notice' => 'Bills posted today and collections posted today are separate. Open bills are the current unpaid posted amount, not a period balance or fiscal reconciliation.',
            'currencies' => $this->currencyTotals(
                $invoices,
                $receipts,
                $this->openBills($organizationId),
                $this->dailySeries($seriesDates, $seriesInvoices, $seriesReceipts),
                $this->emptySeries($seriesDates)
            ),
            'work_waiting' => [
                'billing_requests_queued' => BillingRequest::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', BillingRequest::STATUS_QUEUED)
                    ->count(),
                'payment_proofs_waiting' => ManualPaymentSubmission::query()
                    ->where('organization_id', $organizationId)
                    ->whereIn('status', [ManualPaymentSubmission::STATUS_SUBMITTED, ManualPaymentSubmission::STATUS_IN_REVIEW])
                    ->count(),
                'document_corrections_pending' => DocumentCorrectionRequest::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', DocumentCorrectionRequest::STATUS_PENDING)
                    ->count(),
            ],
            'tellers' => $this->tellerRows($organizationId, $invoices, $receipts),
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Receipt>  $receipts
     * @param  array<string, array{count: int, amount: string}>  $openBills
     * @param  array<string, list<array<string, mixed>>>  $dailySeries
     * @param  list<array<string, mixed>>  $emptySeries
     * @return list<array<string, mixed>>
     */
    private function currencyTotals(Collection $invoices, Collection $receipts, array $openBills, array $dailySeries, array $emptySeries): array
    {
        /** @var array<string, array<string, mixed>> $currencies */
        $currencies = [];
        $ensure = function (string $currency) use (&$currencies, $openBills, $dailySeries, $emptySeries): void {
            if (isset($currencies[$currency])) {
                return;
            }
            $currencies[$currency] = [
                'currency' => $currency,
                'bills_posted_today' => ['count' => 0, 'amount' => '0.00'],
                'official_receipts_posted_today' => ['count' => 0, 'amount' => '0.00'],
                'acknowledgements_posted_today' => ['count' => 0, 'amount' => '0.00'],
                'open_bills' => $openBills[$currency] ?? ['count' => 0, 'amount' => '0.00'],
                'last_7_days' => $dailySeries[$currency] ?? $emptySeries,
            ];
        };

        $ensure('PHP');
        foreach (array_keys($openBills) as $currency) {
            $ensure($currency);
        }
        foreach (array_keys($dailySeries) as $currency) {
            $ensure($currency);
        }

        foreach ($invoices as $invoice) {
            $currency = (string) $invoice->currency;
            $ensure($currency);
            $currencies[$currency]['bills_posted_today']['count']++;
            $currencies[$currency]['bills_posted_today']['amount'] = bcadd(
                $currencies[$currency]['bills_posted_today']['amount'],
                (string) $invoice->total_charge_amount,
                2
            );
        }

        foreach ($receipts as $receipt) {
            $kind = $this->receiptBucket($receipt);
            if ($kind === null) {
                continue;
            }
            $currency = (string) $receipt->currency;
            $ensure($currency);
            $currencies[$currency][$kind]['count']++;
            $currencies[$currency][$kind]['amount'] = bcadd(
                $currencies[$currency][$kind]['amount'],
                $this->receiptAmount($receipt),
                2
            );
        }

        ksort($currencies);

        return array_values($currencies);
    }

    /**
     * Asia/Manila dates in the activity series, oldest first, ending on the business date.
     *
     * @return list<string>
     */
    private function seriesDates(string $today): array
    {
        $dates = [];
        for ($offset = self::SERIES_DAYS - 1; $offset >= 0; $offset--) {
            $dates[] = Carbon::parse($today, self::TIMEZONE)->subDays($offset)->toDateString();
        }

        return $dates;
    }

    /**
     * Posted bills and posted collections per day, kept separate and never netted.
     *
     * @param  list<string>  $dates
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Receipt>  $receipts
     * @return array<string, list<array<string, mixed>>>
     */
    private function dailySeries(array $dates, Collection $invoices, Collection $receipts): array
    {
        /** @var array<string, list<array<string, mixed>>> $series */
        $series = [];
        /** @var array<string, array<string, int>> $index */
        $index = [];

        $ensure = function (string $currency) use (&$series, &$index, $dates): void {
            if (isset($series[$currency])) {
                return;
            }
            $series[$currency] = $this->emptySeries($dates);
            $index[$currency] = array_flip($dates);
        };

        foreach ($invoices as $invoice) {
            $currency = (string) $invoice->currency;
            $date = $invoice->business_date->toDateString();
            $ensure($currency);
            if (! isset($index[$currency][$date])) {
                continue;
            }
            $position = $index[$currency][$date];
            $series[$currency][$position]['bills']['count']++;
            $series[$currency][$position]['bills']['amount'] = bcadd(
                $series[$currency][$position]['bills']['amount'],
                (string) $invoice->total_charge_amount,
                2
            );
        }

        foreach ($receipts as $receipt) {
            $bucket = match ($this->receiptBucket($receipt)) {
                'official_receipts_posted_today' => 'official_receipts',
                'acknowledgements_posted_today' => 'acknowledgements',
                default => null,
            };
            if ($bucket === null) {
                continue;
            }
            $currency = (string) $receipt->currency;
            $date = $receipt->business_date->toDateString();
            $ensure($currency);
            if (! isset($index[$currency][$date])) {
                continue;
            }
            $position = $index[$currency][$date];
            $series[$currency][$position][$bucket]['count']++;
            $series[$currency][$position][$bucket]['amount'] = bcadd(
                $series[$currency][$position][$bucket]['amount'],
                $this->receiptAmount($receipt),
                2
            );
        }

        return $series;
    }

    /**
     * @param  list<string>  $dates
     * @return list<array<string, mixed>>
     */
    private function emptySeries(array $dates): array
    {
        return array_map(fn (string $date): array => [
            'business_date' => $date,
            'bills' => $this->emptyMoney(),
            'official_receipts' => $this->emptyMoney(),
            'acknowledgements' => $this->emptyMoney(),
        ], $dates);
    }

    /**
     * @return array<string, array{count: int, amount: string}>
     */
    private function openBills(int $organizationId): array
    {
        $applied = DB::table('receipt_allocations as allocations')
            ->join('receipts', 'receipts.id', '=', 'allocations.receipt_id')
            ->where('receipts.status', 'POSTED')
            ->groupBy('allocations.invoice_id')
            ->selectRaw('allocations.invoice_id, SUM(allocations.applied_amount) as applied_amount');
        $creditApplied = DB::table('customer_payment_credit_movements as movements')
            ->join('customer_payment_credits as credits', 'credits.id', '=', 'movements.credit_id')
            ->join('receipts as source_receipts', 'source_receipts.id', '=', 'credits.source_receipt_id')
            ->where('movements.type', 'APPLIED')
            ->where('source_receipts.status', 'POSTED')
            ->groupBy('movements.invoice_id')
            ->selectRaw('movements.invoice_id, SUM(movements.amount) as applied_amount');

        $rows = DB::table('invoices')
            ->leftJoinSub($applied, 'applied', 'applied.invoice_id', '=', 'invoices.id')
            ->leftJoinSub($creditApplied, 'credit_applied', 'credit_applied.invoice_id', '=', 'invoices.id')
            ->where('invoices.organization_id', $organizationId)
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->whereRaw('(invoices.total_charge_amount - COALESCE(applied.applied_amount, 0) - COALESCE(credit_applied.applied_amount, 0)) > 0')
            ->groupBy('invoices.currency')
            ->selectRaw('invoices.currency, COUNT(*) as open_count, SUM(invoices.total_charge_amount - COALESCE(applied.applied_amount, 0) - COALESCE(credit_applied.applied_amount, 0)) as outstanding')
            ->get();

        $open = [];
        foreach ($rows as $row) {
            $open[(string) $row->currency] = [
                'count' => (int) $row->open_count,
                'amount' => bcadd((string) $row->outstanding, '0', 2),
            ];
        }

        return $open;
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Receipt>  $receipts
     * @return list<array<string, mixed>>
     */
    private function tellerRows(int $organizationId, Collection $invoices, Collection $receipts): array
    {
        $assignedRequests = BillingRequest::query()
            ->where('organization_id', $organizationId)
            ->where('status', BillingRequest::STATUS_IN_REVIEW)
            ->whereNotNull('assigned_to_user_id')
            ->selectRaw('assigned_to_user_id, COUNT(*) as assigned_count')
            ->groupBy('assigned_to_user_id')
            ->pluck('assigned_count', 'assigned_to_user_id');

        $assignedProofs = ManualPaymentSubmission::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [ManualPaymentSubmission::STATUS_SUBMITTED, ManualPaymentSubmission::STATUS_IN_REVIEW])
            ->whereNotNull('assigned_to_user_id')
            ->selectRaw('assigned_to_user_id, COUNT(*) as assigned_count')
            ->groupBy('assigned_to_user_id')
            ->pluck('assigned_count', 'assigned_to_user_id');

        $userIds = $invoices->pluck('posted_by_user_id')
            ->merge($receipts->pluck('posted_by_user_id'))
            ->merge($assignedRequests->keys())
            ->merge($assignedProofs->keys())
            ->filter()
            ->unique()
            ->values();

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return $users->map(function (User $user) use ($invoices, $receipts, $assignedRequests, $assignedProofs): array {
            $bills = $this->emptyMoney();
            foreach ($invoices->where('posted_by_user_id', $user->id) as $invoice) {
                $bills['count']++;
                $bills['amount'] = bcadd($bills['amount'], (string) $invoice->total_charge_amount, 2);
            }

            $official = $this->emptyMoney();
            $acknowledgements = $this->emptyMoney();
            foreach ($receipts->where('posted_by_user_id', $user->id) as $receipt) {
                $kind = $this->receiptBucket($receipt);
                if ($kind === 'official_receipts_posted_today') {
                    $official['count']++;
                    $official['amount'] = bcadd($official['amount'], $this->receiptAmount($receipt), 2);
                }
                if ($kind === 'acknowledgements_posted_today') {
                    $acknowledgements['count']++;
                    $acknowledgements['amount'] = bcadd($acknowledgements['amount'], $this->receiptAmount($receipt), 2);
                }
            }

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'bills_posted_today' => $bills,
                'official_receipts_posted_today' => $official,
                'acknowledgements_posted_today' => $acknowledgements,
                'billing_requests_in_review' => (int) ($assignedRequests[$user->id] ?? 0),
                'payment_proofs_assigned' => (int) ($assignedProofs[$user->id] ?? 0),
            ];
        })->filter(function (array $row): bool {
            return $row['bills_posted_today']['count'] > 0
                || $row['official_receipts_posted_today']['count'] > 0
                || $row['acknowledgements_posted_today']['count'] > 0
                || $row['billing_requests_in_review'] > 0
                || $row['payment_proofs_assigned'] > 0;
        })->values()->all();
    }

    private function receiptBucket(Receipt $receipt): ?string
    {
        if ($receipt->isAcknowledgement()) {
            return 'acknowledgements_posted_today';
        }
        if ($receipt->isOfficialReceipt()) {
            return 'official_receipts_posted_today';
        }

        return null;
    }

    private function receiptAmount(Receipt $receipt): string
    {
        return bcadd((string) $receipt->cash_received_amount, (string) $receipt->withholding_received_amount, 2);
    }

    /** @return array{count: int, amount: string} */
    private function emptyMoney(): array
    {
        return ['count' => 0, 'amount' => '0.00'];
    }
}
