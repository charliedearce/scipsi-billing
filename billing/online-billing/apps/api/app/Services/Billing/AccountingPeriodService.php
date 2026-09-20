<?php

namespace App\Services\Billing;

use App\Models\AccountingPeriod;
use App\Models\BackdateAuthorization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function resolveOpenPeriod(int $organizationId, Carbon|string $businessDate, bool $lock = false): AccountingPeriod
    {
        $date = $this->date($businessDate);
        $query = AccountingPeriod::where('organization_id', $organizationId)
            ->where('status', 'OPEN')
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->orderByDesc('starts_on');

        if ($lock) {
            $query->lockForUpdate();
        }

        $period = $query->first();
        if (! $period) {
            throw ValidationException::withMessages(['business_date' => ["No open accounting period contains {$date->toDateString()}."]]);
        }

        return $period;
    }

    public function consumeForIssuance(
        int $organizationId,
        ?int $locationId,
        string $documentType,
        Carbon|string $businessDate,
        ?int $authorizationId,
        User $actor,
        int $documentId
    ): array {
        $date = $this->date($businessDate);
        $today = Carbon::today('Asia/Manila');
        if ($date->greaterThan($today)) {
            throw ValidationException::withMessages(['business_date' => ['Future business dates cannot be issued.']]);
        }

        $period = $this->resolveOpenPeriod($organizationId, $date, true);
        if ($date->isSameDay($today)) {
            if ($authorizationId !== null) {
                throw ValidationException::withMessages(['backdate_authorization_id' => ['A backdate authorization may only be used for a prior business date.']]);
            }

            return [$period, null];
        }

        if ($authorizationId === null) {
            throw ValidationException::withMessages(['backdate_authorization_id' => ['An approved, unexpired backdate authorization is required for a prior business date.']]);
        }

        $authorization = BackdateAuthorization::where('organization_id', $organizationId)->whereKey($authorizationId)->lockForUpdate()->firstOrFail();
        if (
            $authorization->status !== BackdateAuthorization::STATUS_APPROVED
            || $authorization->document_type !== $documentType
            || ! $authorization->business_date->isSameDay($date)
            || $authorization->accounting_period_id !== $period->id
            || $authorization->requested_by_user_id !== $actor->id
            || ($authorization->location_id !== null && $authorization->location_id !== $locationId)
            || ($authorization->expires_at !== null && $authorization->expires_at->isPast())
        ) {
            throw ValidationException::withMessages(['backdate_authorization_id' => ['The supplied backdate authorization is not valid for this issuance.']]);
        }

        $authorization->update([
            'status' => BackdateAuthorization::STATUS_CONSUMED,
            'consumed_at' => now(),
            'consumed_document_type' => $documentType,
            'consumed_document_id' => $documentId,
            'consumed_by_user_id' => $actor->id,
        ]);

        return [$period, $authorization];
    }

    private function date(Carbon|string $value): Carbon
    {
        $date = $value instanceof Carbon ? $value->toDateString() : $value;

        return Carbon::createFromFormat('Y-m-d', $date, 'Asia/Manila')->startOfDay();
    }
}
