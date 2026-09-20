<?php

namespace App\Services\Reporting;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Billing\VipCreditAgingService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Produces a read-only, organization-scoped export of the P3-09 VIP principal-aging projection.
 * Late charges are intentionally excluded until P3-11 has an approved accounting/fiscal mapping.
 */
class VipPrincipalAgingReportService
{
    public const REPORT_CODE = 'VIP_PRINCIPAL_AGING';

    public const EVENT_EXPORTED = 'VIP_PRINCIPAL_AGING_EXPORTED';

    public function __construct(protected VipCreditAgingService $aging) {}

    /**
     * @return array{content:string,filename:string,row_count:int,account_count:int}
     */
    public function exportCsv(User $actor, Carbon $asOf): array
    {
        // Credit accounts are organization-level in the current model; until an authoritative
        // location mapping exists, this organization-wide export is Administrator-only.
        if (! $actor->hasRole('Administrator')) {
            throw new AuthorizationException('VIP principal aging export is restricted to Administrators.');
        }

        $accounts = $this->aging->staffAgingIndex($actor, $asOf);
        $rows = $this->rowsForAccounts($accounts);
        $content = $this->csvForRows($rows);

        $this->recordExportAudit($actor, $asOf, $content, count($rows), $accounts->count());

        return [
            'content' => $content,
            'filename' => 'vip-principal-aging-as-of-'.$asOf->toDateString().'.csv',
            'row_count' => count($rows),
            'account_count' => $accounts->count(),
        ];
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $accounts
     * @return array<int,array<string,string|int|null>>
     */
    private function rowsForAccounts(Collection $accounts): array
    {
        return $accounts->flatMap(function (array $aging): array {
            $account = $aging['account'] ?? [];

            return collect($aging['currencies'] ?? [])->flatMap(function (array $currency) use ($account): array {
                return collect($currency['items'] ?? [])->map(function (array $item) use ($account, $currency): array {
                    return [
                        'account_number' => $account['account_number'] ?? null,
                        'customer_name' => $account['customer_name'] ?? null,
                        'invoice_number' => $item['invoice_number'] ?? null,
                        'currency' => $currency['currency'] ?? null,
                        'charged_business_date' => $item['charged_business_date'] ?? null,
                        'due_date' => $item['due_date'] ?? null,
                        'payment_terms_days' => $item['payment_terms_days'] ?? null,
                        'due_date_basis' => $item['due_date_basis'] ?? null,
                        'days_past_due' => (int) ($item['days_past_due'] ?? 0),
                        'bucket' => $item['bucket'] ?? null,
                        'charged_principal_amount' => $item['charged_amount'] ?? '0.00',
                        'applied_as_of_amount' => $item['applied_as_of_amount'] ?? '0.00',
                        'outstanding_principal_as_of_amount' => $item['outstanding_as_of_amount'] ?? '0.00',
                    ];
                })->all();
            })->all();
        })->sortBy([
            ['account_number', 'asc'],
            ['currency', 'asc'],
            ['due_date', 'asc'],
            ['invoice_number', 'asc'],
        ])->values()->all();
    }

    /** @param array<int,array<string,string|int|null>> $rows */
    private function csvForRows(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Customer account',
            'Customer',
            'Invoice number',
            'Currency',
            'Charged business date',
            'Due date',
            'Payment terms days',
            'Due date basis',
            'Days past due',
            'Aging bucket',
            'Charged principal',
            'Applied as of cutoff',
            'Outstanding principal as of cutoff',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($value) => $this->safeCsvValue($value), [
                $row['account_number'],
                $row['customer_name'],
                $row['invoice_number'],
                $row['currency'],
                $row['charged_business_date'],
                $row['due_date'],
                $row['payment_terms_days'],
                $row['due_date_basis'],
                $row['days_past_due'],
                $row['bucket'],
                $row['charged_principal_amount'],
                $row['applied_as_of_amount'],
                $row['outstanding_principal_as_of_amount'],
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

    private function recordExportAudit(User $actor, Carbon $asOf, string $content, int $rowCount, int $accountCount): void
    {
        AuditEvent::create([
            'organization_id' => $actor->organization_id,
            'location_id' => null,
            'event_type' => self::EVENT_EXPORTED,
            'aggregate_type' => 'REPORT_EXPORT',
            'aggregate_id' => 0,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => 'credit_aging:export',
            'occurred_at' => Carbon::now(VipCreditAgingService::TIMEZONE),
            'business_date' => $asOf->toDateString(),
            'reason' => 'Organization-scoped VIP principal aging CSV export',
            'metadata' => [
                'report_code' => self::REPORT_CODE,
                'as_of_date' => $asOf->toDateString(),
                'timezone' => VipCreditAgingService::TIMEZONE,
                'scope' => 'organization_wide_administrator_only',
                'account_count' => $accountCount,
                'row_count' => $rowCount,
                'late_charges_included' => false,
                'content_sha256' => hash('sha256', $content),
            ],
        ]);
    }
}
