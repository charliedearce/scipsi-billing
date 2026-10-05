<?php

namespace App\Services\Announcement;

use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;

class FuelRateAnnouncementService
{
    public function __construct(private AnnouncementService $announcements) {}

    public function currentRate(int $organizationId, Carbon $at): ?string
    {
        $policy = FuelSurchargePolicyVersion::with('bands')
            ->where('organization_id', $organizationId)
            ->where('status', 'effective')
            ->where('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('version_number')
            ->first();

        return $this->rateForPolicy($policy, $organizationId, $at);
    }

    private function rateForPolicy(?FuelSurchargePolicyVersion $policy, int $organizationId, Carbon $at): ?string
    {
        if (! $policy) {
            return null;
        }

        $observation = FuelPriceObservation::where('organization_id', $organizationId)
            ->where('scope_key', 'ORGANIZATION')
            ->where('product_grade', 'DIESEL')
            ->where('status', 'active')
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->first();

        if (! $observation ||
            $policy->fuel_currency !== $observation->currency ||
            $policy->fuel_unit_of_measure !== $observation->unit_of_measure) {
            return null;
        }

        return $policy->findMatchingBand($observation->price)?->surcharge_percent;
    }

    public function announceChange(User $actor, ?string $before, ?string $after): void
    {
        if ($before === null && $after !== null) {
            $previousPolicy = FuelSurchargePolicyVersion::with('bands')
                ->where('organization_id', $actor->organization_id)
                ->where('status', 'effective')
                ->where('effective_to', '<=', Carbon::now('Asia/Manila'))
                ->orderByDesc('effective_to')
                ->first();
            $before = $this->rateForPolicy($previousPolicy, $actor->organization_id, Carbon::now('Asia/Manila'));
        }

        if ($before === null || $after === null || bccomp($before, $after, 4) === 0) {
            return;
        }

        $customerRole = Role::whereNull('organization_id')->where('name', 'Customer')->firstOrFail();
        $oldPercent = bcmul($before, '100', 2);
        $newPercent = bcmul($after, '100', 2);

        $notice = $this->announcements->createDraft($actor, [
            'title' => 'Fuel surcharge rate updated',
            'body' => "The fuel surcharge for new bill calculations has changed from {$oldPercent}% to {$newPercent}%. Issued bills are unchanged.",
            'severity' => 'IMPORTANT',
            'audience_type' => 'targeted',
            'role_ids' => [$customerRole->id],
            'is_dismissible' => true,
        ]);

        $this->announcements->publish($notice, $actor);
    }
}
