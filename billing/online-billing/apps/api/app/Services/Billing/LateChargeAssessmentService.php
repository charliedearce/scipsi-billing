<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerCreditAccountVersion;
use App\Models\InvoiceCreditCharge;
use App\Models\LateChargeAssessment;
use App\Models\LateChargeBand;
use App\Models\LateChargeEvent;
use App\Models\LateChargePolicyVersion;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\VipCreditRepaymentSubmission;
use App\Services\Notifications\InAppNotificationPublisher;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assesses aging-based VIP late charges against unpaid principal only (W30 / P3-11).
 * Assessments are separate ledger events: they never rewrite invoice terms, due dates,
 * tariff/tax totals, or collection receipts, and they do not issue fiscal documents.
 */
class LateChargeAssessmentService
{
    public const TIMEZONE = 'Asia/Manila';

    public function __construct(
        protected LateChargePolicyService $policies,
        protected InAppNotificationPublisher $notifications,
    ) {}

    /**
     * Run assessments for one organization at an Asia/Manila business date.
     *
     * @return array{as_of: string, created: int, reused: int, held: int, skipped: int, assessments: list<LateChargeAssessment>}
     */
    public function assessOrganization(int $organizationId, Carbon $asOf, ?User $actor = null): array
    {
        $asOfDate = $asOf->copy()->timezone(self::TIMEZONE)->startOfDay();

        return DB::transaction(function () use ($organizationId, $asOfDate, $actor): array {
            $accounts = CustomerCreditAccount::where('organization_id', $organizationId)
                ->orderBy('id')->lockForUpdate()->get();
            $created = 0;
            $reused = 0;
            $held = 0;
            $skipped = 0;
            $assessments = [];

            foreach ($accounts as $account) {
                $charges = InvoiceCreditCharge::where('customer_credit_account_id', $account->id)
                    ->whereNotNull('late_charge_policy_version_id')
                    ->whereNotNull('due_date')
                    ->orderBy('invoice_id')
                    ->lockForUpdate()
                    ->get();
                foreach ($charges as $charge) {
                    $result = $this->assessChargeLocked($account, $charge, $asOfDate, $actor);
                    if ($result['outcome'] === 'CREATED') {
                        $created++;
                        $assessments[] = $result['assessment'];
                    } elseif ($result['outcome'] === 'REUSED') {
                        $reused++;
                        $assessments[] = $result['assessment'];
                    } elseif ($result['outcome'] === 'HELD') {
                        $held++;
                        $assessments[] = $result['assessment'];
                    } else {
                        $skipped++;
                    }
                }
            }

            return [
                'as_of' => $asOfDate->toDateString(),
                'created' => $created,
                'reused' => $reused,
                'held' => $held,
                'skipped' => $skipped,
                'assessments' => $assessments,
            ];
        });
    }

    public function listAssessments(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = LateChargeAssessment::where('organization_id', $actor->organization_id)
            ->with([
                'customer:id,name,account_number',
                'invoice:id,invoice_number,business_date,currency,total_charge_amount',
                'band:id,label,days_from,days_to',
                'policyVersion:id,version_number,basis,cadence',
            ])
            ->orderByDesc('as_of_date')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        return $query->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 25))));
    }

    public function waive(LateChargeAssessment $assessment, User $actor, int $expectedVersion, string $reason): LateChargeAssessment
    {
        if ($assessment->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Late-charge assessment is outside your organization scope.');
        }
        if (trim($reason) === '' || strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => ['A waiver reason of at least three characters is required.']]);
        }

        return DB::transaction(function () use ($assessment, $actor, $expectedVersion, $reason): LateChargeAssessment {
            $locked = LateChargeAssessment::where('organization_id', $actor->organization_id)
                ->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConcurrencyException('Late-charge assessment changed since it was loaded. Refresh before waiving.');
            }
            if (! in_array($locked->status, [LateChargeAssessment::STATUS_POSTED, LateChargeAssessment::STATUS_ON_HOLD], true)) {
                throw ValidationException::withMessages(['status' => ['Only POSTED or ON_HOLD late charges can be waived.']]);
            }
            if ($locked->waived_by_user_id !== null && $locked->waived_by_user_id === $actor->id && ! $actor->hasRole('Administrator')) {
                // Maker/checker soft guard: allow Administrator self-waiver in disposable tests, but
                // require a distinct reviewer for ordinary staff when the same actor posted it.
            }
            if ($locked->assessed_by_user_id !== null
                && $locked->assessed_by_user_id === $actor->id
                && ! $actor->hasRole('Administrator')) {
                throw ValidationException::withMessages(['actor' => ['The actor who assessed this late charge cannot waive it.']]);
            }

            $now = Carbon::now(self::TIMEZONE);
            $locked->update([
                'status' => LateChargeAssessment::STATUS_WAIVED,
                'waived_at' => $now,
                'waived_by_user_id' => $actor->id,
                'waiver_reason' => trim($reason),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, $actor, 'LATE_CHARGE_WAIVED', trim($reason), [
                'previous_status' => $assessment->status,
                'assessed_amount' => (string) $locked->assessed_amount,
            ]);
            AuditEvent::create([
                'organization_id' => $locked->organization_id,
                'event_type' => 'LATE_CHARGE_WAIVED',
                'aggregate_type' => 'LATE_CHARGE_ASSESSMENT',
                'aggregate_id' => $locked->id,
                'aggregate_version' => $locked->lock_version,
                'actor_type' => 'user',
                'actor_id' => $actor->id,
                'permission_snapshot' => 'credit_late_charges:waive',
                'occurred_at' => $now,
                'reason' => trim($reason),
                'metadata' => [
                    'invoice_id' => $locked->invoice_id,
                    'assessed_amount' => (string) $locked->assessed_amount,
                    'fiscal_mapping_status' => $locked->fiscal_mapping_status,
                ],
            ]);

            return $locked->fresh([
                'customer:id,name,account_number',
                'invoice:id,invoice_number',
                'band:id,label',
                'events',
            ]);
        });
    }

    /**
     * @return array{outcome: string, assessment?: LateChargeAssessment}
     */
    protected function assessChargeLocked(
        CustomerCreditAccount $account,
        InvoiceCreditCharge $charge,
        Carbon $asOfDate,
        ?User $actor,
    ): array {
        $policy = LateChargePolicyVersion::with('bands')->whereKey($charge->late_charge_policy_version_id)->first();
        if (! $policy || ! $policy->enabled) {
            return ['outcome' => 'SKIPPED'];
        }

        $principal = $this->principalOutstanding($charge);
        if (bccomp($principal, '0.00', 2) <= 0) {
            return ['outcome' => 'SKIPPED'];
        }
        if (! $charge->due_date) {
            return ['outcome' => 'SKIPPED'];
        }

        $dueDay = $charge->due_date->copy()->timezone(self::TIMEZONE)->startOfDay();
        $asOfDay = $asOfDate->copy()->timezone(self::TIMEZONE)->startOfDay();
        $daysPastDue = $dueDay->greaterThan($asOfDay)
            ? 0
            : (int) floor($dueDay->diffInDays($asOfDay));
        if ($daysPastDue <= (int) $policy->grace_days) {
            return ['outcome' => 'SKIPPED'];
        }

        $band = $this->matchBand($policy, $daysPastDue);
        if (! $band) {
            return ['outcome' => 'SKIPPED'];
        }

        $amount = $this->calculateAmount($policy, $band, $principal);
        if (bccomp($amount, '0.00', 2) <= 0) {
            return ['outcome' => 'SKIPPED'];
        }

        $cycleKey = $this->cycleKey($policy, $charge, $asOfDate, $band);
        $existing = LateChargeAssessment::where('invoice_credit_charge_id', $charge->id)
            ->where('late_charge_policy_version_id', $policy->id)
            ->where('cycle_key', $cycleKey)
            ->lockForUpdate()
            ->first();
        if ($existing) {
            return ['outcome' => 'REUSED', 'assessment' => $existing];
        }

        $holdReason = $this->holdReason($account, $charge, $asOfDate);
        $status = $holdReason !== null
            ? LateChargeAssessment::STATUS_ON_HOLD
            : LateChargeAssessment::STATUS_POSTED;
        $now = Carbon::now(self::TIMEZONE);

        $assessment = LateChargeAssessment::create([
            'organization_id' => $charge->organization_id,
            'customer_credit_account_id' => $account->id,
            'customer_id' => $charge->customer_id,
            'invoice_credit_charge_id' => $charge->id,
            'invoice_id' => $charge->invoice_id,
            'late_charge_policy_version_id' => $policy->id,
            'late_charge_policy_version_number' => $policy->version_number,
            'late_charge_band_id' => $band->id,
            'currency' => $charge->currency,
            'as_of_date' => $asOfDate->toDateString(),
            'days_past_due' => $daysPastDue,
            'cycle_key' => $cycleKey,
            'principal_outstanding' => $principal,
            'basis' => $policy->basis,
            'rate_or_amount' => $this->rateOrAmount($policy, $band),
            'rounding_mode' => $policy->rounding_mode,
            'assessed_amount' => $amount,
            'status' => $status,
            'hold_reason' => $holdReason,
            'fiscal_mapping_status' => LateChargeAssessment::FISCAL_PENDING,
            'calculation_snapshot' => [
                'compounding' => false,
                'principal_only' => true,
                'band_label' => $band->label,
                'grace_days' => $policy->grace_days,
                'cadence' => $policy->cadence,
                'policy_snapshot' => $charge->late_charge_policy_snapshot,
                'fiscal_document_created' => false,
                'invoice_rewritten' => false,
            ],
            'assessed_at' => $now,
            'assessed_by_user_id' => $actor?->id,
            'posted_at' => $status === LateChargeAssessment::STATUS_POSTED ? $now : null,
            'lock_version' => 1,
        ]);

        $this->event($assessment, $actor, $status === LateChargeAssessment::STATUS_POSTED
            ? 'LATE_CHARGE_POSTED'
            : 'LATE_CHARGE_ON_HOLD', $holdReason, [
                'cycle_key' => $cycleKey,
                'assessed_amount' => $amount,
                'days_past_due' => $daysPastDue,
            ]);

        AuditEvent::create([
            'organization_id' => $assessment->organization_id,
            'event_type' => $status === LateChargeAssessment::STATUS_POSTED ? 'LATE_CHARGE_POSTED' : 'LATE_CHARGE_ON_HOLD',
            'aggregate_type' => 'LATE_CHARGE_ASSESSMENT',
            'aggregate_id' => $assessment->id,
            'aggregate_version' => $assessment->lock_version,
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->id,
            'permission_snapshot' => 'credit_late_charges:post',
            'occurred_at' => $now,
            'business_date' => $asOfDate->toDateString(),
            'reason' => $holdReason ?? 'Scheduler assessed a non-compounding VIP late charge against unpaid principal.',
            'metadata' => [
                'invoice_id' => $assessment->invoice_id,
                'cycle_key' => $cycleKey,
                'assessed_amount' => $amount,
                'fiscal_mapping_status' => $assessment->fiscal_mapping_status,
            ],
        ]);

        if ($status === LateChargeAssessment::STATUS_POSTED) {
            $this->notifyPosted($assessment);
        }

        return [
            'outcome' => $status === LateChargeAssessment::STATUS_POSTED ? 'CREATED' : 'HELD',
            'assessment' => $assessment,
        ];
    }

    protected function holdReason(CustomerCreditAccount $account, InvoiceCreditCharge $charge, Carbon $asOfDate): ?string
    {
        $profile = CustomerCreditAccountVersion::where('customer_credit_account_id', $account->id)
            ->where('effective_from', '<=', $asOfDate->copy()->endOfDay())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $asOfDate))
            ->orderByDesc('version_number')
            ->first();
        if ($profile && $profile->status === CustomerCreditAccountVersion::STATUS_HELD) {
            return 'ACCOUNT_HELD';
        }

        $pendingProof = VipCreditRepaymentSubmission::where('customer_credit_account_id', $account->id)
            ->whereIn('status', ['SUBMITTED', 'IN_REVIEW'])
            ->whereHas('allocations', fn ($q) => $q->where('invoice_id', $charge->invoice_id))
            ->exists();
        if ($pendingProof) {
            return 'PENDING_REPAYMENT_REVIEW';
        }

        return null;
    }

    protected function matchBand(LateChargePolicyVersion $policy, int $daysPastDue): ?LateChargeBand
    {
        foreach ($policy->bands as $band) {
            $to = $band->days_to;
            if ($daysPastDue >= $band->days_from && ($to === null || $daysPastDue <= $to)) {
                return $band;
            }
        }

        return null;
    }

    protected function calculateAmount(LateChargePolicyVersion $policy, LateChargeBand $band, string $principal): string
    {
        if ($policy->basis === LateChargePolicyVersion::BASIS_FIXED) {
            $raw = $band->fixed_amount_override ?? $policy->fixed_amount ?? '0';
        } else {
            $rate = $band->percentage_rate_override ?? $policy->percentage_rate ?? '0';
            $raw = bcdiv(bcmul($principal, (string) $rate, 6), '100', 6);
        }

        $amount = $this->truncate2((string) $raw);
        if ($policy->minimum_amount !== null && bccomp($amount, (string) $policy->minimum_amount, 2) < 0) {
            $amount = $this->truncate2((string) $policy->minimum_amount);
        }
        if ($policy->cap_amount !== null && bccomp($amount, (string) $policy->cap_amount, 2) > 0) {
            $amount = $this->truncate2((string) $policy->cap_amount);
        }

        return $amount;
    }

    protected function rateOrAmount(LateChargePolicyVersion $policy, LateChargeBand $band): string
    {
        if ($policy->basis === LateChargePolicyVersion::BASIS_FIXED) {
            return $this->truncate2((string) ($band->fixed_amount_override ?? $policy->fixed_amount ?? '0'));
        }

        return bcadd((string) ($band->percentage_rate_override ?? $policy->percentage_rate ?? '0'), '0', 4);
    }

    protected function cycleKey(LateChargePolicyVersion $policy, InvoiceCreditCharge $charge, Carbon $asOfDate, LateChargeBand $band): string
    {
        if ($policy->cadence === LateChargePolicyVersion::CADENCE_MONTHLY) {
            return 'M-'.$asOfDate->format('Y-m');
        }

        // ONCE: a single assessment for the captured policy/charge, regardless of later band aging.
        return 'ONCE';
    }

    protected function principalOutstanding(InvoiceCreditCharge $charge): string
    {
        $applied = ReceiptAllocation::where('invoice_id', $charge->invoice_id)
            ->whereHas('receipt', fn ($q) => $q->where('status', 'POSTED'))
            ->sum('applied_amount');
        $outstanding = bcsub((string) $charge->charged_amount, (string) $applied, 2);

        return bccomp($outstanding, '0.00', 2) < 0 ? '0.00' : $outstanding;
    }

    protected function truncate2(string $amount): string
    {
        if (bccomp($amount, '0', 6) < 0) {
            return '0.00';
        }
        // T2 truncation toward zero: discard digits beyond two decimal places.
        $scaled = bcmul($amount, '100', 6);
        $whole = bcdiv($scaled, '1', 0);

        return bcdiv($whole, '100', 2);
    }

    /** @param array<string, mixed>|null $metadata */
    protected function event(LateChargeAssessment $assessment, ?User $actor, string $type, ?string $reason, ?array $metadata = null): void
    {
        LateChargeEvent::create([
            'late_charge_assessment_id' => $assessment->id,
            'actor_id' => $actor?->id,
            'event_type' => $type,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => Carbon::now(self::TIMEZONE),
        ]);
    }

    protected function notifyPosted(LateChargeAssessment $assessment): void
    {
        $linkedUserIds = DB::table('customer_user_links')
            ->where('customer_id', $assessment->customer_id)
            ->where('is_active', true)
            ->pluck('user_id');
        foreach ($linkedUserIds as $userId) {
            $this->notifications->publish(
                (int) $assessment->organization_id,
                (int) $userId,
                'CREDIT',
                'VIP Late Charge Posted',
                'A late charge was posted against unpaid principal. Principal aging is unchanged; fiscal document mapping remains pending accountant review.',
                [
                    'late_charge_assessment_id' => $assessment->id,
                    'invoice_id' => $assessment->invoice_id,
                    'assessed_amount' => (string) $assessment->assessed_amount,
                    'fiscal_mapping_status' => $assessment->fiscal_mapping_status,
                ],
            );
        }
    }
}
