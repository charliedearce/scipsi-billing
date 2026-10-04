<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\InvoiceCreditCharge;
use App\Models\PpaClearancePolicyVersion;
use App\Models\PpaVerificationEvent;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds credit aging from immutable charge terms and posted receipt allocations.
 * It does not maintain a second balance ledger and has no financial mutation path.
 */
class VipCreditAgingService
{
    public const TIMEZONE = 'Asia/Manila';

    /** @return array<string,mixed> */
    public function portalAging(User $actor, int $customerId, Carbon $asOf): array
    {
        $customer = $this->assertPortalAccess($actor, $customerId);
        $account = CustomerCreditAccount::where('organization_id', $actor->organization_id)
            ->where('customer_id', $customer->id)->first();
        if (! $account) {
            return $this->emptyAging($asOf, 'No VIP credit account has been configured for this customer.');
        }

        return $this->agingForAccount($account, $asOf);
    }

    /** @return array<string,mixed> */
    public function staffAging(User $actor, CustomerCreditAccount $account, Carbon $asOf): array
    {
        if ($account->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Credit account is outside your organization scope.');
        }

        return $this->agingForAccount($account, $asOf);
    }

    /** @return Collection<int,array<string,mixed>> */
    public function staffAgingIndex(User $actor, Carbon $asOf): Collection
    {
        return CustomerCreditAccount::where('organization_id', $actor->organization_id)
            ->with('customer:id,name,account_number,status')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomerCreditAccount $account) => $this->agingForAccount($account, $asOf));
    }

    /** @return Collection<int,PpaClearancePolicyVersion> */
    public function listPpaPolicies(User $actor): Collection
    {
        return PpaClearancePolicyVersion::where('organization_id', $actor->organization_id)
            ->orderByDesc('version_number')->get();
    }

    /** @param array<string,mixed> $data */
    public function createPpaPolicyDraft(User $actor, array $data): PpaClearancePolicyVersion
    {
        return DB::transaction(function () use ($actor, $data): PpaClearancePolicyVersion {
            $latest = PpaClearancePolicyVersion::where('organization_id', $actor->organization_id)
                ->orderByDesc('version_number')->lockForUpdate()->first();

            return PpaClearancePolicyVersion::create([
                'organization_id' => $actor->organization_id,
                'version_number' => ($latest?->version_number ?? 0) + 1,
                'accept_qualifying_vip_credit' => (bool) $data['accept_qualifying_vip_credit'],
                'status' => PpaClearancePolicyVersion::STATUS_DRAFT,
                'effective_from' => Carbon::parse($data['effective_from'], self::TIMEZONE),
                'effective_to' => empty($data['effective_to']) ? null : Carbon::parse($data['effective_to'], self::TIMEZONE),
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);
        });
    }

    public function publishPpaPolicy(PpaClearancePolicyVersion $policy, User $actor, int $expectedVersion, string $reason): PpaClearancePolicyVersion
    {
        return DB::transaction(function () use ($policy, $actor, $expectedVersion, $reason): PpaClearancePolicyVersion {
            $locked = PpaClearancePolicyVersion::where('organization_id', $actor->organization_id)->lockForUpdate()->findOrFail($policy->id);
            if ($locked->status !== PpaClearancePolicyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['policy' => ['Only a draft PPA clearance policy can be published.']]);
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['expected_lock_version' => ['PPA clearance policy changed. Refresh before publishing.']]);
            }
            $this->assertNoPublishedPolicyOverlap($locked);
            $now = Carbon::now(self::TIMEZONE);
            $locked->update([
                'status' => PpaClearancePolicyVersion::STATUS_PUBLISHED,
                'published_by_user_id' => $actor->id,
                'published_at' => $now,
                'publication_reason' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ]);

            return $locked->fresh();
        });
    }

    public function postedInvoiceForPpa(User $actor, string $invoiceNumber): Invoice
    {
        $invoice = Invoice::where('organization_id', $actor->organization_id)
            ->where('invoice_number', $invoiceNumber)
            ->where('status', 'POSTED')
            ->with('customer:id,name,account_number')
            ->firstOrFail();
        if (! $actor->hasRole('Administrator') && ($invoice->location_id === null || ! $actor->canAccessLocation($invoice->location_id))) {
            throw new AuthorizationException('Invoice is outside your assigned PPA location scope.');
        }

        return $invoice;
    }

    public function postedReceiptForPpa(User $actor, string $receiptNumber): Receipt
    {
        $receipt = Receipt::where('organization_id', $actor->organization_id)
            ->where('receipt_number', $receiptNumber)
            ->whereIn('status', ['POSTED', 'REVERSED'])
            ->with('allocations.invoice')
            ->firstOrFail();

        if ($actor->hasRole('Administrator')) {
            return $receipt;
        }

        $locationAllowed = $receipt->location_id !== null && $actor->canAccessLocation($receipt->location_id);
        $invoiceAllowed = $receipt->allocations->contains(function (ReceiptAllocation $allocation) use ($actor): bool {
            $invoice = $allocation->invoice;

            return $invoice !== null
                && $invoice->location_id !== null
                && $actor->canAccessLocation($invoice->location_id);
        });

        if (! $locationAllowed && ! $invoiceAllowed) {
            throw new AuthorizationException('Receipt is outside your assigned PPA location scope.');
        }

        return $receipt;
    }

    /** @return array<string,mixed> */
    public function resolveDocumentForPpa(User $actor, string $number): array
    {
        $number = trim($number);
        $invoiceExists = Invoice::where('organization_id', $actor->organization_id)
            ->where('invoice_number', $number)
            ->where('status', 'POSTED')
            ->exists();

        if ($invoiceExists) {
            $invoice = $this->postedInvoiceForPpa($actor, $number);

            return [
                'document_kind' => 'INVOICE',
                'invoice_number' => $invoice->invoice_number,
                'receipt_number' => null,
                'receipt_status' => null,
                'linked_invoices' => [],
            ];
        }

        $receipt = $this->postedReceiptForPpa($actor, $number);
        $linked = $receipt->allocations
            ->filter(function (ReceiptAllocation $allocation) use ($actor): bool {
                $invoice = $allocation->invoice;
                if ($invoice === null || $invoice->status !== 'POSTED') {
                    return false;
                }
                if ($actor->hasRole('Administrator')) {
                    return true;
                }

                return $invoice->location_id !== null && $actor->canAccessLocation($invoice->location_id);
            })
            ->map(fn (ReceiptAllocation $allocation): array => [
                'invoice_number' => $allocation->invoice?->invoice_number,
                'applied_amount' => (string) $allocation->applied_amount,
                'currency' => $allocation->invoice?->currency,
            ])
            ->values()
            ->all();

        return [
            'document_kind' => $receipt->isAcknowledgement() ? 'ACKNOWLEDGEMENT_RECEIPT' : 'OFFICIAL_RECEIPT',
            'invoice_number' => null,
            'receipt_number' => $receipt->receipt_number,
            'receipt_status' => $receipt->status,
            'linked_invoices' => $linked,
        ];
    }

    /** @return array<string,mixed> */
    public function verifyForPpa(User $actor, string $invoiceNumber): array
    {
        $invoice = $this->postedInvoiceForPpa($actor, $invoiceNumber);

        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->first();
        $receiptSummaries = $this->currentReceiptSummaries($invoice->id);
        $reversedSummaries = $this->reversedReceiptSummaries($invoice->id);
        $applied = $this->currentAppliedAmounts([$invoice->id])[$invoice->id] ?? '0.00';
        $outstanding = $this->positiveDifference((string) $invoice->total_charge_amount, $applied);
        $settlementStatus = bccomp($outstanding, '0.00', 2) === 0
            ? 'PAID'
            : (bccomp($applied, '0.00', 2) > 0 ? 'PARTIALLY_PAID' : 'UNPAID');
        $creditStatus = $charge && bccomp($outstanding, '0.00', 2) > 0 ? 'ON_CREDIT' : ($charge ? 'CREDIT_SETTLED' : 'NOT_ON_CREDIT');
        $policy = $this->effectivePpaPolicy($actor->organization_id, Carbon::now(self::TIMEZONE));
        $clearanceEligibility = $settlementStatus === 'PAID'
            ? 'FULLY_PAID'
            : ($creditStatus === 'ON_CREDIT' && $policy?->accept_qualifying_vip_credit ? 'ON_CREDIT_ACCEPTED' : 'NOT_ELIGIBLE');
        $checkedAt = Carbon::now(self::TIMEZONE);

        PpaVerificationEvent::create([
            'organization_id' => $actor->organization_id,
            'invoice_id' => $invoice->id,
            'invoice_credit_charge_id' => $charge?->id,
            'ppa_clearance_policy_version_id' => $policy?->id,
            'ppa_clearance_policy_version_number' => $policy?->version_number,
            'checked_by_user_id' => $actor->id,
            'settlement_status' => $settlementStatus,
            'credit_status' => $creditStatus,
            'clearance_eligibility' => $clearanceEligibility,
            'outstanding_amount' => $outstanding,
            'checked_at' => $checkedAt,
        ]);

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_business_date' => $invoice->business_date?->toDateString(),
            'customer' => ['account_number' => $invoice->customer?->account_number, 'name' => $invoice->customer?->name],
            'currency' => $invoice->currency,
            'invoice_total_amount' => (string) $invoice->total_charge_amount,
            'applied_amount' => $applied,
            'outstanding_amount' => $outstanding,
            'confirmed_receipt_count' => $receiptSummaries['count'],
            'receipt_history_truncated' => $receiptSummaries['truncated'],
            'receipts' => $receiptSummaries['items'],
            'reversed_receipt_count' => $reversedSummaries['count'],
            'reversed_receipt_history_truncated' => $reversedSummaries['truncated'],
            'reversed_receipts' => $reversedSummaries['items'],
            'settlement_status' => $settlementStatus,
            'credit_status' => $creditStatus,
            'credit_due_date' => $charge?->due_date?->toDateString(),
            'clearance_eligibility' => $clearanceEligibility,
            'policy' => $policy ? ['version' => $policy->version_number, 'accept_qualifying_vip_credit' => $policy->accept_qualifying_vip_credit] : null,
            'formal_clearance_issued' => false,
            'checked_at' => $checkedAt->toIso8601String(),
            'notice' => 'This is a read-only payment and credit validation. Reversed receipts are history only and never count as confirmed settlement. It does not issue a formal clearance, release or gate-pass action.',
        ];
    }

    public function asOf(?string $value): Carbon
    {
        return $value
            ? Carbon::createFromFormat('Y-m-d', $value, self::TIMEZONE)->startOfDay()
            : Carbon::now(self::TIMEZONE)->startOfDay();
    }

    /** @return array<string,mixed> */
    protected function agingForAccount(CustomerCreditAccount $account, Carbon $asOf): array
    {
        $account->loadMissing('customer');
        $charges = InvoiceCreditCharge::where('customer_credit_account_id', $account->id)
            ->where(function ($query) use ($asOf): void {
                $query->where('charged_business_date', '<=', $asOf->toDateString())
                    ->orWhere(function ($missingBusinessDate) use ($asOf): void {
                        $missingBusinessDate->whereNull('charged_business_date')->where('charged_at', '<=', $asOf->copy()->endOfDay());
                    });
            })
            ->with('invoice:id,invoice_number,currency')
            ->orderBy('currency')->orderBy('due_date')->orderBy('id')
            ->get();
        $applied = $this->effectiveAppliedAmounts($charges->pluck('invoice_id')->all(), $asOf);
        $currencies = $charges->groupBy('currency')->map(function (Collection $currencyCharges, string $currency) use ($applied, $asOf): array {
            $buckets = $this->emptyBuckets();
            $items = $currencyCharges->map(function (InvoiceCreditCharge $charge) use ($applied, $asOf, &$buckets): array {
                $outstanding = $this->positiveDifference((string) $charge->charged_amount, $applied[$charge->invoice_id] ?? '0.00');
                $classification = $charge->due_date ? 'CLASSIFIED' : 'UNCLASSIFIED_NEEDS_TERMS_REVIEW';
                $daysPastDue = $charge->due_date && $charge->due_date->lt($asOf) ? $charge->due_date->diffInDays($asOf) : 0;
                $bucket = $classification === 'CLASSIFIED' ? $this->bucketFor((int) $daysPastDue) : 'UNCLASSIFIED';
                $buckets[$bucket] = bcadd($buckets[$bucket], $outstanding, 2);

                return [
                    'credit_charge_id' => $charge->id,
                    'invoice_id' => $charge->invoice_id,
                    'invoice_number' => $charge->invoice?->invoice_number,
                    'charged_amount' => (string) $charge->charged_amount,
                    'applied_as_of_amount' => $applied[$charge->invoice_id] ?? '0.00',
                    'outstanding_as_of_amount' => $outstanding,
                    'charged_business_date' => $charge->charged_business_date?->toDateString(),
                    'charged_recorded_at' => $charge->charged_at?->toIso8601String(),
                    'due_date' => $charge->due_date?->toDateString(),
                    'due_date_classification' => $classification,
                    'days_past_due' => $daysPastDue,
                    'bucket' => $bucket,
                    'payment_terms_days' => $charge->payment_terms_days_snapshot,
                    'due_date_basis' => $charge->due_date_basis_snapshot,
                ];
            })->values();
            $total = collect($buckets)->reduce(fn (string $carry, string $amount) => bcadd($carry, $amount, 2), '0.00');

            return ['currency' => $currency, 'buckets' => $buckets, 'outstanding_amount' => $total, 'items' => $items];
        })->values();

        return [
            'as_of_date' => $asOf->toDateString(),
            'timezone' => self::TIMEZONE,
            'cutoff_semantics' => 'Credit charges are included by charged business date. Posted receipt allocations are effective when receipt.business_date is on or before the as-of date; receipt.posted_at remains the recorded timestamp.',
            'account' => ['id' => $account->id, 'customer_id' => $account->customer_id, 'customer_name' => $account->customer?->name, 'account_number' => $account->customer?->account_number],
            'currencies' => $currencies,
        ];
    }

    /** @return array<string,mixed> */
    protected function emptyAging(Carbon $asOf, string $reason): array
    {
        return [
            'as_of_date' => $asOf->toDateString(), 'timezone' => self::TIMEZONE, 'reason' => $reason,
            'cutoff_semantics' => 'Credit charges are included by charged business date. Posted receipt allocations are effective by receipt.business_date.',
            'currencies' => [],
        ];
    }

    /** @return array<int,string> */
    protected function effectiveAppliedAmounts(array $invoiceIds, Carbon $asOf): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return ReceiptAllocation::whereIn('invoice_id', $invoiceIds)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED')->whereDate('business_date', '<=', $asOf->toDateString()))
            ->select('invoice_id', DB::raw('SUM(applied_amount) as amount'))
            ->groupBy('invoice_id')->pluck('amount', 'invoice_id')
            ->map(fn ($amount) => (string) $amount)->all();
    }

    /** @return array<int,string> */
    protected function currentAppliedAmounts(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return ReceiptAllocation::whereIn('invoice_id', $invoiceIds)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
            ->select('invoice_id', DB::raw('SUM(applied_amount) as amount'))
            ->groupBy('invoice_id')->pluck('amount', 'invoice_id')
            ->map(fn ($amount) => (string) $amount)->all();
    }

    /**
     * Return only the minimal receipt facts needed for a PPA payment check.
     * Proof images, tender references, payer snapshots, and receipt artifacts
     * remain outside the PPA role's verification response.
     *
     * @return array{count:int,truncated:bool,items:array<int,array<string,string|null>>}
     */
    protected function currentReceiptSummaries(int $invoiceId): array
    {
        return $this->receiptSummariesForStatus($invoiceId, 'POSTED');
    }

    /**
     * Reversed receipts stay visible as settlement history for PPA checks, but never
     * contribute to applied/outstanding amounts or clearance eligibility.
     *
     * @return array{count:int,truncated:bool,items:array<int,array<string,string|null>>}
     */
    protected function reversedReceiptSummaries(int $invoiceId): array
    {
        return $this->receiptSummariesForStatus($invoiceId, 'REVERSED');
    }

    /**
     * @return array{count:int,truncated:bool,items:array<int,array<string,string|null>>}
     */
    protected function receiptSummariesForStatus(int $invoiceId, string $status): array
    {
        $count = ReceiptAllocation::where('invoice_id', $invoiceId)
            ->whereHas('receipt', fn ($query) => $query->where('status', $status))
            ->count();
        $limit = 10;
        $items = ReceiptAllocation::query()
            ->select('receipt_allocations.*')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipt_allocations.invoice_id', $invoiceId)
            ->where('receipts.status', $status)
            ->with('receipt:id,receipt_number,business_date,status')
            ->orderByDesc('receipts.business_date')
            ->orderByDesc('receipts.id')
            ->limit($limit)
            ->get()
            ->map(function (ReceiptAllocation $allocation) use ($status): array {
                return [
                    'receipt_number' => $allocation->receipt?->receipt_number,
                    'business_date' => $allocation->receipt?->business_date?->toDateString(),
                    'applied_amount' => (string) $allocation->applied_amount,
                    'receipt_status' => $status,
                ];
            })
            ->values()
            ->all();

        return ['count' => $count, 'truncated' => $count > $limit, 'items' => $items];
    }

    /** @return array<string,string> */
    protected function emptyBuckets(): array
    {
        return ['CURRENT' => '0.00', '1_30' => '0.00', '31_60' => '0.00', '61_90' => '0.00', '91_PLUS' => '0.00', 'UNCLASSIFIED' => '0.00'];
    }

    protected function bucketFor(int $daysPastDue): string
    {
        return match (true) {
            $daysPastDue <= 0 => 'CURRENT',
            $daysPastDue <= 30 => '1_30',
            $daysPastDue <= 60 => '31_60',
            $daysPastDue <= 90 => '61_90',
            default => '91_PLUS',
        };
    }

    protected function effectivePpaPolicy(int $organizationId, Carbon $at): ?PpaClearancePolicyVersion
    {
        return PpaClearancePolicyVersion::where('organization_id', $organizationId)
            ->where('status', PpaClearancePolicyVersion::STATUS_PUBLISHED)
            ->where('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('version_number')->first();
    }

    protected function assertNoPublishedPolicyOverlap(PpaClearancePolicyVersion $candidate): void
    {
        $published = PpaClearancePolicyVersion::where('organization_id', $candidate->organization_id)
            ->where('status', PpaClearancePolicyVersion::STATUS_PUBLISHED)->lockForUpdate()->get();
        foreach ($published as $policy) {
            $candidateBefore = $candidate->effective_to && $candidate->effective_to->lessThanOrEqualTo($policy->effective_from);
            $policyBefore = $policy->effective_to && $policy->effective_to->lessThanOrEqualTo($candidate->effective_from);
            if (! $candidateBefore && ! $policyBefore) {
                throw ValidationException::withMessages(['effective_from' => ["PPA clearance policy window overlaps published version {$policy->version_number}."]]);
            }
        }
    }

    protected function assertPortalAccess(User $actor, int $customerId): Customer
    {
        $customer = Customer::where('organization_id', $actor->organization_id)->whereKey($customerId)->firstOrFail();
        $linked = CustomerUserLink::where('customer_id', $customer->id)->where('user_id', $actor->id)->where('is_active', true)->exists();
        if (! $linked) {
            throw new AuthorizationException('Customer account is not linked to this portal user.');
        }

        return $customer;
    }

    protected function positiveDifference(string $total, string $applied): string
    {
        $value = bcsub($total, $applied, 2);

        return bccomp($value, '0.00', 2) > 0 ? $value : '0.00';
    }
}
