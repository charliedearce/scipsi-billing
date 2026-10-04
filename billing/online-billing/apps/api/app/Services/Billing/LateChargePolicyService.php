<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\LateChargeBand;
use App\Models\LateChargePolicyVersion;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns versioned VIP late-charge policy drafts/publication (W30 / P3-11).
 * Policies never rewrite existing credit charges; capture happens only on future assignments.
 */
class LateChargePolicyService
{
    public const TIMEZONE = 'Asia/Manila';

    /** @return Collection<int, LateChargePolicyVersion> */
    public function listPolicies(User $actor): Collection
    {
        return LateChargePolicyVersion::where('organization_id', $actor->organization_id)
            ->with(['bands', 'createdBy:id,name,email', 'publishedBy:id,name,email'])
            ->orderByDesc('version_number')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createDraft(User $actor, array $data): LateChargePolicyVersion
    {
        return DB::transaction(function () use ($actor, $data): LateChargePolicyVersion {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->assertPolicyShape($data);
            $next = ((int) LateChargePolicyVersion::where('organization_id', $actor->organization_id)->max('version_number')) + 1;

            $policy = LateChargePolicyVersion::create([
                'organization_id' => $actor->organization_id,
                'version_number' => $next,
                'currency' => strtoupper((string) ($data['currency'] ?? 'PHP')),
                'enabled' => (bool) ($data['enabled'] ?? true),
                'allow_customer_overrides' => (bool) ($data['allow_customer_overrides'] ?? false),
                'grace_days' => (int) ($data['grace_days'] ?? 0),
                'basis' => strtoupper((string) $data['basis']),
                'fixed_amount' => $this->nullableDecimal($data['fixed_amount'] ?? null),
                'percentage_rate' => $this->nullableDecimal($data['percentage_rate'] ?? null),
                'cadence' => strtoupper((string) $data['cadence']),
                'minimum_amount' => $this->nullableDecimal($data['minimum_amount'] ?? null),
                'cap_amount' => $this->nullableDecimal($data['cap_amount'] ?? null),
                'rounding_mode' => strtoupper((string) ($data['rounding_mode'] ?? LateChargePolicyVersion::ROUNDING_TRUNCATE_2)),
                'allocation_priority' => 'PRINCIPAL_FIRST',
                'affects_available_credit' => (bool) ($data['affects_available_credit'] ?? false),
                'contract_reference' => isset($data['contract_reference']) ? trim((string) $data['contract_reference']) : null,
                'customer_notice' => isset($data['customer_notice']) ? trim((string) $data['customer_notice']) : null,
                'status' => LateChargePolicyVersion::STATUS_DRAFT,
                'effective_from' => Carbon::parse((string) $data['effective_from'], self::TIMEZONE),
                'effective_to' => empty($data['effective_to']) ? null : Carbon::parse((string) $data['effective_to'], self::TIMEZONE),
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);

            foreach ($data['bands'] as $index => $band) {
                LateChargeBand::create([
                    'late_charge_policy_version_id' => $policy->id,
                    'days_from' => (int) $band['days_from'],
                    'days_to' => array_key_exists('days_to', $band) && $band['days_to'] !== null && $band['days_to'] !== ''
                        ? (int) $band['days_to']
                        : null,
                    'label' => trim((string) $band['label']),
                    'fixed_amount_override' => $this->nullableDecimal($band['fixed_amount_override'] ?? null),
                    'percentage_rate_override' => $this->nullableDecimal($band['percentage_rate_override'] ?? null),
                    'sort_order' => $index,
                ]);
            }

            $this->audit($policy, $actor, 'LATE_CHARGE_POLICY_DRAFT_CREATED', 'credit_late_charge_policies:manage', 'Late-charge policy draft created.');

            return $policy->fresh(['bands', 'createdBy:id,name,email']);
        });
    }

    public function deleteDraft(LateChargePolicyVersion $policy, User $actor, int $expectedVersion): void
    {
        DB::transaction(function () use ($policy, $actor, $expectedVersion): void {
            $locked = LateChargePolicyVersion::where('organization_id', $actor->organization_id)
                ->whereKey($policy->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->status !== LateChargePolicyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['policy' => ['Only a draft late-charge policy can be deleted.']]);
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConcurrencyException('Late-charge policy changed since it was loaded. Refresh before deleting.');
            }
            if ($locked->assessments()->exists()) {
                throw ValidationException::withMessages(['policy' => ['This late-charge policy is referenced by an assessment and cannot be deleted.']]);
            }

            $this->audit($locked, $actor, 'LATE_CHARGE_POLICY_DRAFT_DELETED', 'credit_late_charge_policies:manage', 'Late-charge policy draft deleted.');
            $locked->bands()->delete();
            $locked->delete();
        });
    }

    public function publish(LateChargePolicyVersion $policy, User $actor, int $expectedVersion, string $reason): LateChargePolicyVersion
    {
        return DB::transaction(function () use ($policy, $actor, $expectedVersion, $reason): LateChargePolicyVersion {
            $locked = LateChargePolicyVersion::where('organization_id', $actor->organization_id)
                ->whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConcurrencyException('Late-charge policy changed since it was loaded. Refresh before publishing.');
            }
            if ($locked->status !== LateChargePolicyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['policy' => ['Only a draft late-charge policy can be published.']]);
            }
            if ($locked->bands()->count() < 1) {
                throw ValidationException::withMessages(['bands' => ['A late-charge policy requires at least one day band before publication.']]);
            }
            $this->preparePublishedLateChargePolicyWindow($locked);
            $this->assertNoPublishedOverlap($locked);

            $locked->update([
                'status' => LateChargePolicyVersion::STATUS_PUBLISHED,
                'published_by_user_id' => $actor->id,
                'published_at' => Carbon::now(self::TIMEZONE),
                'publication_reason' => trim($reason),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit($locked, $actor, 'LATE_CHARGE_POLICY_PUBLISHED', 'credit_late_charge_policies:manage', trim($reason));

            return $locked->fresh(['bands', 'createdBy:id,name,email', 'publishedBy:id,name,email']);
        });
    }

    public function effectivePolicy(int $organizationId, string $currency, Carbon $at, bool $lock = false): ?LateChargePolicyVersion
    {
        $query = LateChargePolicyVersion::where('organization_id', $organizationId)
            ->where('status', LateChargePolicyVersion::STATUS_PUBLISHED)
            ->where('enabled', true)
            ->where('currency', strtoupper($currency))
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->with('bands')
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array<string, mixed> */
    public function snapshot(LateChargePolicyVersion $policy): array
    {
        return [
            'policy_version_id' => $policy->id,
            'version_number' => $policy->version_number,
            'currency' => $policy->currency,
            'enabled' => $policy->enabled,
            'grace_days' => $policy->grace_days,
            'basis' => $policy->basis,
            'fixed_amount' => $policy->fixed_amount,
            'percentage_rate' => $policy->percentage_rate,
            'cadence' => $policy->cadence,
            'minimum_amount' => $policy->minimum_amount,
            'cap_amount' => $policy->cap_amount,
            'rounding_mode' => $policy->rounding_mode,
            'allocation_priority' => $policy->allocation_priority,
            'affects_available_credit' => $policy->affects_available_credit,
            'contract_reference' => $policy->contract_reference,
            'customer_notice' => $policy->customer_notice,
            'bands' => $policy->bands->map(fn (LateChargeBand $band) => [
                'id' => $band->id,
                'days_from' => $band->days_from,
                'days_to' => $band->days_to,
                'label' => $band->label,
                'fixed_amount_override' => $band->fixed_amount_override,
                'percentage_rate_override' => $band->percentage_rate_override,
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function assertPolicyShape(array $data): void
    {
        $basis = strtoupper((string) ($data['basis'] ?? ''));
        $cadence = strtoupper((string) ($data['cadence'] ?? ''));
        $rounding = strtoupper((string) ($data['rounding_mode'] ?? LateChargePolicyVersion::ROUNDING_TRUNCATE_2));
        if (! in_array($basis, [LateChargePolicyVersion::BASIS_FIXED, LateChargePolicyVersion::BASIS_PERCENTAGE], true)) {
            throw ValidationException::withMessages(['basis' => ['Late-charge basis must be FIXED or PERCENTAGE.']]);
        }
        if (! in_array($cadence, [LateChargePolicyVersion::CADENCE_ONCE, LateChargePolicyVersion::CADENCE_MONTHLY], true)) {
            throw ValidationException::withMessages(['cadence' => ['Late-charge cadence must be ONCE or MONTHLY.']]);
        }
        if ($rounding !== LateChargePolicyVersion::ROUNDING_TRUNCATE_2) {
            throw ValidationException::withMessages(['rounding_mode' => ['Only TRUNCATE_2 rounding is approved for the late-charge baseline.']]);
        }
        if ($basis === LateChargePolicyVersion::BASIS_FIXED && $this->nullableDecimal($data['fixed_amount'] ?? null) === null) {
            throw ValidationException::withMessages(['fixed_amount' => ['A FIXED late-charge policy requires fixed_amount.']]);
        }
        if ($basis === LateChargePolicyVersion::BASIS_PERCENTAGE && $this->nullableDecimal($data['percentage_rate'] ?? null) === null) {
            throw ValidationException::withMessages(['percentage_rate' => ['A PERCENTAGE late-charge policy requires percentage_rate.']]);
        }
        $bands = $data['bands'] ?? null;
        if (! is_array($bands) || $bands === []) {
            throw ValidationException::withMessages(['bands' => ['At least one non-overlapping day band is required.']]);
        }
        $normalized = [];
        foreach ($bands as $index => $band) {
            if (! is_array($band)) {
                throw ValidationException::withMessages(["bands.$index" => ['Each band must be an object.']]);
            }
            $from = (int) ($band['days_from'] ?? -1);
            $to = array_key_exists('days_to', $band) && $band['days_to'] !== null && $band['days_to'] !== ''
                ? (int) $band['days_to']
                : null;
            if ($from < 0) {
                throw ValidationException::withMessages(["bands.$index.days_from" => ['days_from must be zero or greater.']]);
            }
            if ($to !== null && $to < $from) {
                throw ValidationException::withMessages(["bands.$index.days_to" => ['days_to must be greater than or equal to days_from.']]);
            }
            if (trim((string) ($band['label'] ?? '')) === '') {
                throw ValidationException::withMessages(["bands.$index.label" => ['Each band requires a label.']]);
            }
            $normalized[] = ['from' => $from, 'to' => $to, 'index' => $index];
        }
        usort($normalized, fn (array $a, array $b) => $a['from'] <=> $b['from']);
        $previousTo = -1;
        foreach ($normalized as $band) {
            if ($band['from'] <= $previousTo) {
                throw ValidationException::withMessages(['bands' => ['Late-charge day bands must be non-overlapping and ordered.']]);
            }
            if ($previousTo >= 0 && $band['from'] !== $previousTo + 1) {
                throw ValidationException::withMessages(['bands' => ['Late-charge day bands must be contiguous without gaps after grace coverage.']]);
            }
            $previousTo = $band['to'] ?? PHP_INT_MAX;
            if ($band['to'] === null && $band !== end($normalized)) {
                throw ValidationException::withMessages(['bands' => ['Only the final late-charge band may have an open upper bound.']]);
            }
        }
    }

    protected function preparePublishedLateChargePolicyWindow(LateChargePolicyVersion $candidate): void
    {
        $cutover = Carbon::now(self::TIMEZONE);
        if ($candidate->effective_from) {
            $from = $candidate->effective_from->copy()->timezone(self::TIMEZONE);
            if ($from->greaterThan($cutover)) {
                $cutover = $from;
            }
        }

        $safety = 0;
        while ($safety < 10) {
            $safety++;
            $openEnded = LateChargePolicyVersion::where('organization_id', $candidate->organization_id)
                ->where('status', LateChargePolicyVersion::STATUS_PUBLISHED)
                ->where('currency', $candidate->currency)
                ->whereNull('effective_to')
                ->whereKeyNot($candidate->id)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            if ($openEnded->isEmpty()) {
                break;
            }

            $closedAny = false;
            foreach ($openEnded as $policy) {
                $policyStart = $policy->effective_from->copy()->timezone(self::TIMEZONE);
                if ($policyStart->lessThan($cutover)) {
                    $policy->forceFill([
                        'effective_to' => $cutover,
                        'lock_version' => $policy->lock_version + 1,
                    ])->save();
                    $closedAny = true;
                }
            }

            if ($closedAny) {
                continue;
            }

            $latestStart = $openEnded
                ->map(fn (LateChargePolicyVersion $policy) => $policy->effective_from->copy()->timezone(self::TIMEZONE)->getTimestamp())
                ->max();
            $cutover = Carbon::createFromTimestamp((int) $latestStart, self::TIMEZONE)->addSecond();
        }

        $candidate->forceFill(['effective_from' => $cutover])->save();
        $candidate->refresh();
    }

    protected function assertNoPublishedOverlap(LateChargePolicyVersion $candidate): void
    {
        $published = LateChargePolicyVersion::where('organization_id', $candidate->organization_id)
            ->where('status', LateChargePolicyVersion::STATUS_PUBLISHED)
            ->where('currency', $candidate->currency)
            ->whereKeyNot($candidate->id)
            ->lockForUpdate()
            ->get();
        foreach ($published as $existing) {
            $candidateFrom = $candidate->effective_from;
            $candidateTo = $candidate->effective_to;
            $existingFrom = $existing->effective_from;
            $existingTo = $existing->effective_to;
            $overlaps = $candidateFrom < ($existingTo ?? Carbon::parse('9999-12-31', self::TIMEZONE))
                && $existingFrom < ($candidateTo ?? Carbon::parse('9999-12-31', self::TIMEZONE));
            if ($overlaps) {
                throw ValidationException::withMessages([
                    'effective_from' => ["Published late-charge policy v{$existing->version_number} already covers an overlapping {$candidate->currency} window."],
                ]);
            }
        }
    }

    protected function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return bcadd((string) $value, '0', 4);
    }

    protected function audit(LateChargePolicyVersion $policy, User $actor, string $type, string $permission, string $reason): void
    {
        AuditEvent::create([
            'organization_id' => $policy->organization_id,
            'event_type' => $type,
            'aggregate_type' => 'LATE_CHARGE_POLICY_VERSION',
            'aggregate_id' => $policy->id,
            'aggregate_version' => $policy->lock_version,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => $permission,
            'occurred_at' => Carbon::now(self::TIMEZONE),
            'reason' => $reason,
            'metadata' => [
                'version_number' => $policy->version_number,
                'currency' => $policy->currency,
                'status' => $policy->status,
                'basis' => $policy->basis,
                'cadence' => $policy->cadence,
            ],
        ]);
    }
}
