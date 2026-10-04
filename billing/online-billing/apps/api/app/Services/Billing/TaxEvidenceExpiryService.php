<?php

namespace App\Services\Billing;

use App\Events\DataRefreshEvent;
use App\Models\Customer;
use App\Models\CustomerTaxEvidenceEvent;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerWithholdingCertificate;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Services\Notifications\InAppNotificationPublisher;
use App\Services\Sms\SmsDeliveryOrchestrator;
use App\Support\SafeBroadcast;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marks approved tax evidence EXPIRED after its end date and notifies customers
 * when less than 30 Asia/Manila calendar days remain (P2-08 / W20 / W31).
 * Never rewrites posted invoices, receipts, or certificate amounts.
 */
class TaxEvidenceExpiryService
{
    public const TIMEZONE = 'Asia/Manila';

    public const APPROACHING_DAYS = 30;

    public function __construct(
        protected InAppNotificationPublisher $notifications,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
    ) {}

    /**
     * @return array{
     *     as_of: string,
     *     expired_withholding: int,
     *     expired_exemptions: int,
     *     approaching_withholding: int,
     *     approaching_exemptions: int,
     *     notifications_created: int
     * }
     */
    public function processOrganization(int $organizationId, ?Carbon $asOf = null): array
    {
        $asOfDate = ($asOf ?? Carbon::now(self::TIMEZONE))
            ->copy()
            ->timezone(self::TIMEZONE)
            ->startOfDay();

        $expiredWithholding = 0;
        $expiredExemptions = 0;
        $approachingWithholding = 0;
        $approachingExemptions = 0;
        $notificationsCreated = 0;

        $dueCertificates = CustomerWithholdingCertificate::query()
            ->where('organization_id', $organizationId)
            ->where('status', CustomerWithholdingCertificate::STATUS_APPROVED)
            ->whereDate('period_to', '<', $asOfDate->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($dueCertificates as $cert) {
            $result = $this->expireWithholding($cert, $asOfDate);
            if ($result['expired']) {
                $expiredWithholding++;
            }
            $notificationsCreated += $result['notifications'];
        }

        $dueExemptions = CustomerTaxExemption::query()
            ->where('organization_id', $organizationId)
            ->where('status', CustomerTaxExemption::STATUS_APPROVED)
            ->whereNotNull('valid_to')
            ->whereDate('valid_to', '<', $asOfDate->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($dueExemptions as $exemption) {
            $result = $this->expireExemption($exemption, $asOfDate);
            if ($result['expired']) {
                $expiredExemptions++;
            }
            $notificationsCreated += $result['notifications'];
        }

        $windowEnd = $asOfDate->copy()->addDays(self::APPROACHING_DAYS - 1)->toDateString();
        $asOfStr = $asOfDate->toDateString();

        $approachingCertificates = CustomerWithholdingCertificate::query()
            ->where('organization_id', $organizationId)
            ->where('status', CustomerWithholdingCertificate::STATUS_APPROVED)
            ->whereDate('period_to', '>=', $asOfStr)
            ->whereDate('period_to', '<=', $windowEnd)
            ->orderBy('id')
            ->get();

        foreach ($approachingCertificates as $cert) {
            $created = $this->notifyApproachingWithholding($cert, $asOfDate);
            if ($created > 0) {
                $approachingWithholding++;
            }
            $notificationsCreated += $created;
        }

        $approachingExemptionsRows = CustomerTaxExemption::query()
            ->where('organization_id', $organizationId)
            ->where('status', CustomerTaxExemption::STATUS_APPROVED)
            ->whereNotNull('valid_to')
            ->whereDate('valid_to', '>=', $asOfStr)
            ->whereDate('valid_to', '<=', $windowEnd)
            ->orderBy('id')
            ->get();

        foreach ($approachingExemptionsRows as $exemption) {
            $created = $this->notifyApproachingExemption($exemption, $asOfDate);
            if ($created > 0) {
                $approachingExemptions++;
            }
            $notificationsCreated += $created;
        }

        return [
            'as_of' => $asOfStr,
            'expired_withholding' => $expiredWithholding,
            'expired_exemptions' => $expiredExemptions,
            'approaching_withholding' => $approachingWithholding,
            'approaching_exemptions' => $approachingExemptions,
            'notifications_created' => $notificationsCreated,
        ];
    }

    /**
     * @return array{expired: bool, notifications: int}
     */
    protected function expireWithholding(CustomerWithholdingCertificate $cert, Carbon $asOfDate): array
    {
        return DB::transaction(function () use ($cert, $asOfDate): array {
            /** @var CustomerWithholdingCertificate $locked */
            $locked = CustomerWithholdingCertificate::query()
                ->whereKey($cert->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CustomerWithholdingCertificate::STATUS_APPROVED) {
                return ['expired' => false, 'notifications' => 0];
            }

            if ($locked->period_to->toDateString() >= $asOfDate->toDateString()) {
                return ['expired' => false, 'notifications' => 0];
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => CustomerWithholdingCertificate::STATUS_EXPIRED,
                'lock_version' => $locked->lock_version + 1,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $locked->organization_id,
                'evidence_type' => 'WITHHOLDING_CERTIFICATE',
                'evidence_id' => $locked->id,
                'actor_id' => null,
                'event_type' => 'EXPIRED',
                'from_status' => $oldStatus,
                'to_status' => CustomerWithholdingCertificate::STATUS_EXPIRED,
                'notes' => 'Certificate period ended; customer must file renewal tax evidence.',
                'metadata' => [
                    'period_to' => $locked->period_to->toDateString(),
                    'as_of' => $asOfDate->toDateString(),
                    'source' => 'tax:process-evidence-expiry',
                ],
                'created_at' => Carbon::now(),
            ]);

            $recipient = $this->resolveRecipient($locked->customer_id);
            $notifications = 0;

            if ($recipient) {
                $created = $this->notifications->publishOnce(
                    (int) $locked->organization_id,
                    (int) $recipient->id,
                    'TAX',
                    'Tax evidence expired — renew required',
                    'Your approved BIR 2307 certificate has expired. File updated tax evidence with a new validity period to renew.',
                    "tax-expired:withholding:{$locked->id}",
                    [
                        'certificate_id' => $locked->id,
                        'certificate_no' => $locked->certificate_no,
                        'status' => CustomerWithholdingCertificate::STATUS_EXPIRED,
                        'alert' => 'expired',
                        'renew_path' => '/my-tax-evidence',
                    ],
                );
                if ($created) {
                    $notifications++;
                }

                $this->dispatchExpiredSms(
                    (int) $locked->organization_id,
                    'withholding_certificate',
                    (int) $locked->id,
                    $recipient,
                );
            }

            SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
                $locked->organization_id,
                'tax_evidence',
                'withholding_certificate',
                $locked->id,
                'expired',
                $locked->lock_version,
                $recipient?->id,
                false,
            ));

            return ['expired' => true, 'notifications' => $notifications];
        });
    }

    /**
     * @return array{expired: bool, notifications: int}
     */
    protected function expireExemption(CustomerTaxExemption $exemption, Carbon $asOfDate): array
    {
        return DB::transaction(function () use ($exemption, $asOfDate): array {
            /** @var CustomerTaxExemption $locked */
            $locked = CustomerTaxExemption::query()
                ->whereKey($exemption->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CustomerTaxExemption::STATUS_APPROVED) {
                return ['expired' => false, 'notifications' => 0];
            }

            if ($locked->valid_to === null || $locked->valid_to->toDateString() >= $asOfDate->toDateString()) {
                return ['expired' => false, 'notifications' => 0];
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => CustomerTaxExemption::STATUS_EXPIRED,
                'lock_version' => $locked->lock_version + 1,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $locked->organization_id,
                'evidence_type' => 'TAX_EXEMPTION',
                'evidence_id' => $locked->id,
                'actor_id' => null,
                'event_type' => 'EXPIRED',
                'from_status' => $oldStatus,
                'to_status' => CustomerTaxExemption::STATUS_EXPIRED,
                'notes' => 'Exemption validity ended; customer must file renewal tax evidence.',
                'metadata' => [
                    'valid_to' => $locked->valid_to->toDateString(),
                    'as_of' => $asOfDate->toDateString(),
                    'source' => 'tax:process-evidence-expiry',
                ],
                'created_at' => Carbon::now(),
            ]);

            $recipient = $this->resolveRecipient($locked->customer_id);
            $notifications = 0;

            if ($recipient) {
                $created = $this->notifications->publishOnce(
                    (int) $locked->organization_id,
                    (int) $recipient->id,
                    'TAX',
                    'Tax evidence expired — renew required',
                    'Your approved tax exemption or zero-rated ruling has expired. File updated tax evidence with a new validity period to renew.',
                    "tax-expired:exemption:{$locked->id}",
                    [
                        'exemption_id' => $locked->id,
                        'ruling_or_cert_no' => $locked->ruling_or_cert_no,
                        'status' => CustomerTaxExemption::STATUS_EXPIRED,
                        'alert' => 'expired',
                        'renew_path' => '/my-tax-evidence',
                    ],
                );
                if ($created) {
                    $notifications++;
                }

                $this->dispatchExpiredSms(
                    (int) $locked->organization_id,
                    'tax_exemption',
                    (int) $locked->id,
                    $recipient,
                );
            }

            SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
                $locked->organization_id,
                'tax_evidence',
                'tax_exemption',
                $locked->id,
                'expired',
                $locked->lock_version,
                $recipient?->id,
                false,
            ));

            return ['expired' => true, 'notifications' => $notifications];
        });
    }

    protected function notifyApproachingWithholding(CustomerWithholdingCertificate $cert, Carbon $asOfDate): int
    {
        $recipient = $this->resolveRecipient($cert->customer_id);
        if (! $recipient) {
            return 0;
        }

        $endDate = $cert->period_to->toDateString();
        $daysLeftLabel = (int) $asOfDate->diffInDays($cert->period_to->copy()->startOfDay());

        $created = $this->notifications->publishOnce(
            (int) $cert->organization_id,
            (int) $recipient->id,
            'TAX',
            'Tax evidence expiring soon — renew',
            "Your approved BIR 2307 certificate expires in {$daysLeftLabel} day(s) ({$endDate}). Update your files on Tax Evidence to refresh the period.",
            "tax-expiring:withholding:{$cert->id}:{$endDate}",
            [
                'certificate_id' => $cert->id,
                'certificate_no' => $cert->certificate_no,
                'status' => $cert->status,
                'period_to' => $endDate,
                'days_remaining' => $daysLeftLabel,
                'alert' => 'approaching',
                'renew_path' => '/my-tax-evidence',
            ],
        );

        return $created ? 1 : 0;
    }

    protected function notifyApproachingExemption(CustomerTaxExemption $exemption, Carbon $asOfDate): int
    {
        $recipient = $this->resolveRecipient($exemption->customer_id);
        if (! $recipient || $exemption->valid_to === null) {
            return 0;
        }

        $endDate = $exemption->valid_to->toDateString();
        $daysLeftLabel = (int) $asOfDate->diffInDays($exemption->valid_to->copy()->startOfDay());

        $created = $this->notifications->publishOnce(
            (int) $exemption->organization_id,
            (int) $recipient->id,
            'TAX',
            'Tax evidence expiring soon — renew',
            "Your approved tax exemption or zero-rated ruling expires in {$daysLeftLabel} day(s) ({$endDate}). Update your files on Tax Evidence to refresh validity.",
            "tax-expiring:exemption:{$exemption->id}:{$endDate}",
            [
                'exemption_id' => $exemption->id,
                'ruling_or_cert_no' => $exemption->ruling_or_cert_no,
                'status' => $exemption->status,
                'valid_to' => $endDate,
                'days_remaining' => $daysLeftLabel,
                'alert' => 'approaching',
                'renew_path' => '/my-tax-evidence',
            ],
        );

        return $created ? 1 : 0;
    }

    protected function resolveRecipient(int $customerId): ?User
    {
        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return null;
        }

        return $customer->users()->wherePivot('is_active', true)->first()
            ?? $customer->users()->first();
    }

    /**
     * W31 covers tax evidence expired outcomes. Generic action only — no certificate content.
     */
    protected function dispatchExpiredSms(
        int $organizationId,
        string $evidenceType,
        int $evidenceId,
        User $recipientUser,
    ): void {
        $already = NotificationEvent::query()
            ->where('organization_id', $organizationId)
            ->where('event_key', 'TAX_EVIDENCE_EXPIRED')
            ->where('event_source_type', $evidenceType)
            ->where('event_source_id', $evidenceId)
            ->where('user_id', $recipientUser->id)
            ->exists();

        if ($already) {
            return;
        }

        $now = Carbon::now();
        $payload = [
            'recipient_name' => $recipientUser->name,
            'action_label' => 'expired',
            'org_name' => 'SCIPSI',
            'date_formatted' => $now->timezone(self::TIMEZONE)->format('M d, Y'),
        ];

        $dispatch = function () use ($organizationId, $evidenceType, $evidenceId, $recipientUser, $payload, $now): void {
            try {
                $event = NotificationEvent::create([
                    'organization_id' => $organizationId,
                    'event_key' => 'TAX_EVIDENCE_EXPIRED',
                    'event_source_type' => $evidenceType,
                    'event_source_id' => $evidenceId,
                    'user_id' => $recipientUser->id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => $now,
                ]);

                $this->smsOrchestrator->queueIntent($event);
            } catch (\Throwable $e) {
                report($e);
            }
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }
    }
}
