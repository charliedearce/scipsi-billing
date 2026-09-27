<?php

namespace App\Services\Billing;

use App\Events\DataRefreshEvent;
use App\Models\Customer;
use App\Models\CustomerTaxEvidenceEvent;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerWithholdingCertificate;
use App\Models\InAppNotification;
use App\Models\NotificationEvent;
use App\Models\PrivateFile;
use App\Models\User;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxEvidenceService
{
    public function __construct(
        protected SmsDeliveryOrchestrator $smsOrchestrator
    ) {}

    /**
     * Submit a BIR Form 2307 Creditable Withholding Certificate.
     */
    public function submitWithholdingCertificate(
        User $actor,
        Customer $customer,
        array $data
    ): CustomerWithholdingCertificate {
        // Validate clean private file
        $file = PrivateFile::where('id', $data['private_file_id'])
            ->where('organization_id', $customer->organization_id)
            ->firstOrFail();

        $latestVersion = $file->latestVersion;
        if (! $latestVersion || $latestVersion->scan_status !== 'CLEAN') {
            throw ValidationException::withMessages([
                'private_file_id' => 'The selected certificate file is unverified or quarantined.',
            ]);
        }

        // Validate dates
        $periodFrom = Carbon::parse($data['period_from']);
        $periodTo = Carbon::parse($data['period_to']);
        if ($periodFrom->gt($periodTo)) {
            throw ValidationException::withMessages([
                'period_from' => 'Tax period start date cannot be after end date.',
            ]);
        }

        // Validate amounts. The certified amount is the figure on the BIR 2307.
        // A rate is stored only when the customer or reviewer supplied one.
        $base = number_format((float) $data['income_payment_base'], 2, '.', '');
        $rate = null;
        if (isset($data['withholding_rate']) && $data['withholding_rate'] !== '') {
            $rate = number_format((float) $data['withholding_rate'], 4, '.', '');
            if (bccomp($rate, '0', 4) !== 1) {
                throw ValidationException::withMessages([
                    'withholding_rate' => 'Withholding rate must be greater than zero when provided.',
                ]);
            }
        }
        $certifiedAmount = number_format((float) $data['certified_amount'], 2, '.', '');

        if (bccomp($certifiedAmount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'certified_amount' => 'Certified withholding amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($actor, $customer, $data, $file, $latestVersion, $periodFrom, $periodTo, $base, $rate, $certifiedAmount) {
            // Check uniqueness of certificate number per org
            $exists = CustomerWithholdingCertificate::where('organization_id', $customer->organization_id)
                ->where('certificate_no', $data['certificate_no'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'certificate_no' => "Withholding certificate number [{$data['certificate_no']}] has already been submitted.",
                ]);
            }

            $cert = CustomerWithholdingCertificate::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'certificate_no' => $data['certificate_no'],
                'private_file_id' => $file->id,
                'reviewed_version_number' => $latestVersion->version_number,
                'payor_tin' => $data['payor_tin'],
                'payor_name' => $data['payor_name'],
                'payee_tin' => $data['payee_tin'] ?? '000-000-000-000',
                'payee_name' => $data['payee_name'] ?? 'SCIPSI',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'tax_type' => $data['tax_type'] ?? 'CREDITABLE_WITHHOLDING_TAX',
                'atc_code' => $data['atc_code'],
                'income_payment_base' => $base,
                'withholding_rate' => $rate,
                'certified_amount' => $certifiedAmount,
                'allocated_amount' => '0.00',
                'remaining_amount' => $certifiedAmount,
                'status' => CustomerWithholdingCertificate::STATUS_PENDING_REVIEW,
                'customer_notes' => $data['customer_notes'] ?? null,
                'lock_version' => 1,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $customer->organization_id,
                'evidence_type' => 'WITHHOLDING_CERTIFICATE',
                'evidence_id' => $cert->id,
                'actor_id' => $actor->id,
                'event_type' => 'SUBMITTED',
                'from_status' => null,
                'to_status' => CustomerWithholdingCertificate::STATUS_PENDING_REVIEW,
                'notes' => "Withholding certificate {$cert->certificate_no} submitted for review",
                'metadata' => [
                    'certified_amount' => $certifiedAmount,
                    'atc_code' => $cert->atc_code,
                ],
                'created_at' => Carbon::now(),
            ]);

            broadcast(new DataRefreshEvent(
                $customer->organization_id,
                'tax_evidence',
                'withholding_certificate',
                $cert->id,
                'submitted'
            ));

            return $cert->load(['customer', 'privateFile.latestVersion']);
        });
    }

    /**
     * Staff reviews a BIR Form 2307 certificate (Decision W20).
     */
    public function reviewWithholdingCertificate(
        CustomerWithholdingCertificate $cert,
        User $reviewer,
        string $decision,
        ?string $decisionNotes = null,
        ?string $rejectionReason = null
    ): CustomerWithholdingCertificate {
        if (! in_array($decision, [
            CustomerWithholdingCertificate::STATUS_APPROVED,
            CustomerWithholdingCertificate::STATUS_NEEDS_CORRECTION,
            CustomerWithholdingCertificate::STATUS_REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'decision' => "Invalid review decision [{$decision}].",
            ]);
        }

        return DB::transaction(function () use ($cert, $reviewer, $decision, $decisionNotes, $rejectionReason) {
            $now = Carbon::now();
            $oldStatus = $cert->status;

            $updateData = [
                'status' => $decision,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => $now,
                'decision_notes' => $decisionNotes,
                'rejection_reason' => $rejectionReason,
            ];

            if ($decision === CustomerWithholdingCertificate::STATUS_APPROVED) {
                // Ensure remaining amount equals certified minus allocated
                $remaining = bcsub($cert->certified_amount, $cert->allocated_amount, 2);
                $updateData['remaining_amount'] = $remaining;
            }

            $cert->update($updateData);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $cert->organization_id,
                'evidence_type' => 'WITHHOLDING_CERTIFICATE',
                'evidence_id' => $cert->id,
                'actor_id' => $reviewer->id,
                'event_type' => $decision,
                'from_status' => $oldStatus,
                'to_status' => $decision,
                'notes' => $decisionNotes ?: ($rejectionReason ?: "Status updated to {$decision}"),
                'metadata' => [
                    'reviewer_name' => $reviewer->name,
                ],
                'created_at' => $now,
            ]);

            // Notify customer
            InAppNotification::create([
                'organization_id' => $cert->organization_id,
                'user_id' => $cert->customer->users()->first()?->id ?? $reviewer->id,
                'type' => 'TAX',
                'title' => "Withholding Certificate Review: {$decision}",
                'body' => "Your BIR 2307 certificate {$cert->certificate_no} has been {$decision}.".($rejectionReason ? " Reason: {$rejectionReason}" : ''),
                'data' => [
                    'certificate_id' => $cert->id,
                    'certificate_no' => $cert->certificate_no,
                    'status' => $decision,
                ],
                'is_read' => false,
            ]);

            broadcast(new DataRefreshEvent(
                $cert->organization_id,
                'tax_evidence',
                'withholding_certificate',
                $cert->id,
                strtolower($decision)
            ));

            // Outbound Transactional SMS Notice (Decision W31 & BIR-14)
            $eventKey = match ($decision) {
                CustomerWithholdingCertificate::STATUS_APPROVED => 'TAX_EVIDENCE_APPROVED',
                CustomerWithholdingCertificate::STATUS_NEEDS_CORRECTION => 'TAX_EVIDENCE_CORRECTION_REQUIRED',
                CustomerWithholdingCertificate::STATUS_REJECTED => 'TAX_EVIDENCE_REJECTED',
                default => 'TAX_EVIDENCE_APPROVED',
            };

            $recipientUser = $cert->customer->users()->wherePivot('is_active', true)->first()
                ?? $cert->customer->users()->first();

            if ($recipientUser) {
                $this->dispatchTaxEvidenceNotice(
                    $cert->organization_id,
                    'withholding_certificate',
                    $cert->id,
                    $recipientUser,
                    $eventKey,
                    strtolower(str_replace('_', ' ', $decision))
                );
            }

            return $cert->fresh(['customer', 'reviewer', 'privateFile.latestVersion']);
        });
    }

    /**
     * Revoke an active withholding certificate.
     */
    public function revokeWithholdingCertificate(
        CustomerWithholdingCertificate $cert,
        User $admin,
        string $reason
    ): CustomerWithholdingCertificate {
        return DB::transaction(function () use ($cert, $admin, $reason) {
            $now = Carbon::now();
            $oldStatus = $cert->status;

            $cert->update([
                'status' => CustomerWithholdingCertificate::STATUS_REVOKED,
                'decision_notes' => $reason,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $cert->organization_id,
                'evidence_type' => 'WITHHOLDING_CERTIFICATE',
                'evidence_id' => $cert->id,
                'actor_id' => $admin->id,
                'event_type' => 'REVOKED',
                'from_status' => $oldStatus,
                'to_status' => CustomerWithholdingCertificate::STATUS_REVOKED,
                'notes' => "Certificate revoked: {$reason}",
                'created_at' => $now,
            ]);

            // Outbound Transactional SMS Notice (Decision W31 & BIR-14)
            $recipientUser = $cert->customer->users()->wherePivot('is_active', true)->first()
                ?? $cert->customer->users()->first();

            if ($recipientUser) {
                $this->dispatchTaxEvidenceNotice(
                    $cert->organization_id,
                    'withholding_certificate',
                    $cert->id,
                    $recipientUser,
                    'TAX_EVIDENCE_REVOKED',
                    'revoked'
                );
            }

            return $cert;
        });
    }

    /**
     * Submit a Tax Exemption / Zero-Rating Certificate.
     */
    public function submitTaxExemption(
        User $actor,
        Customer $customer,
        array $data
    ): CustomerTaxExemption {
        $file = PrivateFile::where('id', $data['private_file_id'])
            ->where('organization_id', $customer->organization_id)
            ->firstOrFail();

        $latestVersion = $file->latestVersion;
        if (! $latestVersion || $latestVersion->scan_status !== 'CLEAN') {
            throw ValidationException::withMessages([
                'private_file_id' => 'The selected exemption proof file is unverified or quarantined.',
            ]);
        }

        $validFrom = Carbon::parse($data['valid_from']);
        $validTo = ! empty($data['valid_to']) ? Carbon::parse($data['valid_to']) : null;

        if ($validTo && $validFrom->gt($validTo)) {
            throw ValidationException::withMessages([
                'valid_from' => 'Exemption valid from date cannot be after valid to date.',
            ]);
        }

        $exemptionType = $data['exemption_type'];
        if (! in_array($exemptionType, [CustomerTaxExemption::TYPE_VAT_EXEMPT, CustomerTaxExemption::TYPE_ZERO_RATED], true)) {
            throw ValidationException::withMessages([
                'exemption_type' => "Invalid exemption type [{$exemptionType}]. Must be VAT_EXEMPT or ZERO_RATED.",
            ]);
        }

        return DB::transaction(function () use ($actor, $customer, $data, $file, $latestVersion, $validFrom, $validTo, $exemptionType) {
            $exemption = CustomerTaxExemption::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'exemption_type' => $exemptionType,
                'legal_basis' => $data['legal_basis'],
                'ruling_or_cert_no' => $data['ruling_or_cert_no'],
                'covered_services' => $data['covered_services'] ?? ['ALL'],
                'valid_from' => $validFrom->toDateString(),
                'valid_to' => $validTo?->toDateString(),
                'private_file_id' => $file->id,
                'reviewed_version_number' => $latestVersion->version_number,
                'status' => CustomerTaxExemption::STATUS_PENDING_REVIEW,
                'customer_notes' => $data['customer_notes'] ?? null,
                'lock_version' => 1,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $customer->organization_id,
                'evidence_type' => 'TAX_EXEMPTION',
                'evidence_id' => $exemption->id,
                'actor_id' => $actor->id,
                'event_type' => 'SUBMITTED',
                'from_status' => null,
                'to_status' => CustomerTaxExemption::STATUS_PENDING_REVIEW,
                'notes' => "Tax exemption proof submitted: {$exemption->legal_basis}",
                'metadata' => [
                    'exemption_type' => $exemptionType,
                    'ruling_or_cert_no' => $exemption->ruling_or_cert_no,
                ],
                'created_at' => Carbon::now(),
            ]);

            broadcast(new DataRefreshEvent(
                $customer->organization_id,
                'tax_evidence',
                'tax_exemption',
                $exemption->id,
                'submitted'
            ));

            return $exemption->load(['customer', 'privateFile.latestVersion']);
        });
    }

    /**
     * Administrator reviews a Tax Exemption submission (Decision W20).
     */
    public function reviewTaxExemption(
        CustomerTaxExemption $exemption,
        User $reviewer,
        string $decision,
        ?string $decisionNotes = null,
        ?string $rejectionReason = null
    ): CustomerTaxExemption {
        if (! in_array($decision, [
            CustomerTaxExemption::STATUS_APPROVED,
            CustomerTaxExemption::STATUS_NEEDS_CORRECTION,
            CustomerTaxExemption::STATUS_REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'decision' => "Invalid review decision [{$decision}].",
            ]);
        }

        return DB::transaction(function () use ($exemption, $reviewer, $decision, $decisionNotes, $rejectionReason) {
            $now = Carbon::now();
            $oldStatus = $exemption->status;

            $exemption->update([
                'status' => $decision,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => $now,
                'decision_notes' => $decisionNotes,
                'rejection_reason' => $rejectionReason,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $exemption->organization_id,
                'evidence_type' => 'TAX_EXEMPTION',
                'evidence_id' => $exemption->id,
                'actor_id' => $reviewer->id,
                'event_type' => $decision,
                'from_status' => $oldStatus,
                'to_status' => $decision,
                'notes' => $decisionNotes ?: ($rejectionReason ?: "Status updated to {$decision}"),
                'metadata' => [
                    'reviewer_name' => $reviewer->name,
                ],
                'created_at' => $now,
            ]);

            // Notify customer
            InAppNotification::create([
                'organization_id' => $exemption->organization_id,
                'user_id' => $exemption->customer->users()->first()?->id ?? $reviewer->id,
                'type' => 'TAX',
                'title' => "Tax Exemption Review: {$decision}",
                'body' => "Your tax exemption proof ({$exemption->legal_basis}) has been {$decision}.".($rejectionReason ? " Reason: {$rejectionReason}" : ''),
                'data' => [
                    'exemption_id' => $exemption->id,
                    'status' => $decision,
                ],
                'is_read' => false,
            ]);

            broadcast(new DataRefreshEvent(
                $exemption->organization_id,
                'tax_evidence',
                'tax_exemption',
                $exemption->id,
                strtolower($decision)
            ));

            // Outbound Transactional SMS Notice (Decision W31 & BIR-14)
            $eventKey = match ($decision) {
                CustomerTaxExemption::STATUS_APPROVED => 'TAX_EVIDENCE_APPROVED',
                CustomerTaxExemption::STATUS_NEEDS_CORRECTION => 'TAX_EVIDENCE_CORRECTION_REQUIRED',
                CustomerTaxExemption::STATUS_REJECTED => 'TAX_EVIDENCE_REJECTED',
                default => 'TAX_EVIDENCE_APPROVED',
            };

            $recipientUser = $exemption->customer->users()->wherePivot('is_active', true)->first()
                ?? $exemption->customer->users()->first();

            if ($recipientUser) {
                $this->dispatchTaxEvidenceNotice(
                    $exemption->organization_id,
                    'tax_exemption',
                    $exemption->id,
                    $recipientUser,
                    $eventKey,
                    strtolower(str_replace('_', ' ', $decision))
                );
            }

            return $exemption->fresh(['customer', 'reviewer', 'privateFile.latestVersion']);
        });
    }

    /**
     * Revoke an active tax exemption ruling.
     */
    public function revokeTaxExemption(
        CustomerTaxExemption $exemption,
        User $admin,
        string $reason
    ): CustomerTaxExemption {
        return DB::transaction(function () use ($exemption, $admin, $reason) {
            $now = Carbon::now();
            $oldStatus = $exemption->status;

            $exemption->update([
                'status' => CustomerTaxExemption::STATUS_REVOKED,
                'decision_notes' => $reason,
            ]);

            CustomerTaxEvidenceEvent::create([
                'organization_id' => $exemption->organization_id,
                'evidence_type' => 'TAX_EXEMPTION',
                'evidence_id' => $exemption->id,
                'actor_id' => $admin->id,
                'event_type' => 'REVOKED',
                'from_status' => $oldStatus,
                'to_status' => CustomerTaxExemption::STATUS_REVOKED,
                'notes' => "Tax exemption revoked: {$reason}",
                'created_at' => $now,
            ]);

            // Outbound Transactional SMS Notice (Decision W31 & BIR-14)
            $recipientUser = $exemption->customer->users()->wherePivot('is_active', true)->first()
                ?? $exemption->customer->users()->first();

            if ($recipientUser) {
                $this->dispatchTaxEvidenceNotice(
                    $exemption->organization_id,
                    'tax_exemption',
                    $exemption->id,
                    $recipientUser,
                    'TAX_EVIDENCE_REVOKED',
                    'revoked'
                );
            }

            return $exemption;
        });
    }

    /**
     * Find active, approved tax exemption covering a customer, business date, and service type.
     */
    public function findEffectiveExemption(
        int $customerId,
        Carbon|string $date,
        string $serviceType
    ): ?CustomerTaxExemption {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $candidates = CustomerTaxExemption::where('customer_id', $customerId)
            ->where('status', CustomerTaxExemption::STATUS_APPROVED)
            ->where('valid_from', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $dateStr);
            })
            ->get();

        foreach ($candidates as $cand) {
            if ($cand->isCurrentlyEffective($dateStr, $serviceType)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Dispatch tax evidence review outcome transactional notice (Decision W31 & BIR-14).
     * Strictly after-commit and sanitized: never includes tax returns, certificates, or file details.
     */
    protected function dispatchTaxEvidenceNotice(
        int $organizationId,
        string $evidenceType,
        int $evidenceId,
        User $recipientUser,
        string $eventKey,
        string $actionLabel
    ): void {
        $now = Carbon::now();
        $payload = [
            'recipient_name' => $recipientUser->name,
            'action_label' => $actionLabel,
            'org_name' => 'SCIPSI',
            'date_formatted' => $now->timezone('Asia/Manila')->format('M d, Y'),
        ];

        $dispatch = function () use ($organizationId, $evidenceType, $evidenceId, $recipientUser, $eventKey, $payload, $now) {
            try {
                $event = NotificationEvent::create([
                    'organization_id' => $organizationId,
                    'event_key' => $eventKey,
                    'event_source_type' => $evidenceType,
                    'event_source_id' => $evidenceId,
                    'user_id' => $recipientUser->id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => $now,
                ]);

                if ($this->smsOrchestrator) {
                    $this->smsOrchestrator->queueIntent($event);
                }
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
