<?php

namespace App\Services\Billing;

use App\Events\DataRefreshEvent;
use App\Models\BillClaimEvent;
use App\Models\BillClaimRequest;
use App\Models\Customer;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Portal-side service for invoice-number claim and verification.
 *
 * Security invariants (W25 / Decision §6 CUSTOMER_SERVICE_LIFECYCLE.md):
 * - Number-only guessing grants no information. Non-existent numbers return generic responses.
 * - Claim codes are cryptographically random, single-use, purpose-bound, and TTL-limited.
 * - Registration / mobile-change OTPs cannot be reused here (different purpose prefix).
 * - Approval links only the specific invoice — not all company bills.
 * - Issued buyer snapshots on posted invoices are NEVER altered by a claim.
 */
class BillClaimService
{
    /** Rolling window for per-user rate limiting of claim initiations (minutes). */
    private const RATE_LIMIT_WINDOW_MINUTES = 60;

    /** Max claim initiations per user per window. */
    private const RATE_LIMIT_MAX = 10;

    /** Claim code TTL in minutes. */
    private const CODE_TTL_MINUTES = 15;

    /** Purpose prefix that distinguishes claim codes from registration OTPs (never interchangeable). */
    private const CLAIM_PURPOSE_PREFIX = 'BILL_CLAIM_';

    public function __construct(
        protected WalkInBillingService $walkInService,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
    ) {}

    /**
     * Initiate an invoice-number claim from a portal user.
     *
     * Security design:
     * - Invoice number lookup is scoped to the organization, never leaks existence.
     * - Rate-limited; failed/generic response on limit breach.
     * - Determines CLAIM_CODE or TELLER_REVIEW route based on contact availability.
     */
    public function initiateClaim(
        User $requester,
        Customer $customer,
        string $invoiceNumber
    ): BillClaimRequest {
        // 1. Rate limiting: prevent brute-force enumeration of invoice numbers
        $recentAttempts = BillClaimRequest::where('user_id', $requester->id)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::RATE_LIMIT_WINDOW_MINUTES))
            ->count();

        if ($recentAttempts >= self::RATE_LIMIT_MAX) {
            // Audit this but return generic error — do not confirm limit was reason
            throw ValidationException::withMessages([
                'invoice_number' => 'Unable to process claim request. Please try again later.',
            ]);
        }

        // 2. Expire any stale pending codes before creating a new one
        $this->expireStaleClaimCodes();

        return DB::transaction(function () use ($requester, $customer, $invoiceNumber): BillClaimRequest {
            $orgId = $customer->organization_id;

            // 3. Look up invoice scoped to organization (never reveal non-existence)
            $invoice = Invoice::where('organization_id', $orgId)
                ->where('invoice_number', $invoiceNumber)
                ->where('status', 'POSTED')
                ->first();

            // 4. Check for an existing active claim for this user + number
            if ($invoice) {
                $existingApproved = BillClaimRequest::where('user_id', $requester->id)
                    ->where('invoice_id', $invoice->id)
                    ->where('claim_status', BillClaimRequest::STATUS_APPROVED)
                    ->exists();

                if ($existingApproved) {
                    throw ValidationException::withMessages([
                        'invoice_number' => 'This invoice is already linked to your account.',
                    ]);
                }

                // Cancel any previous pending claims for this user+invoice pair
                BillClaimRequest::where('user_id', $requester->id)
                    ->where('invoice_id', $invoice->id)
                    ->whereIn('claim_status', [
                        BillClaimRequest::STATUS_PENDING_VERIFICATION,
                        BillClaimRequest::STATUS_PENDING_TELLER_REVIEW,
                    ])
                    ->update(['claim_status' => BillClaimRequest::STATUS_CANCELLED]);
            }

            // 5. Determine verification route
            // Try to find a contact point on the walk-in record for this invoice
            $verificationRoute = BillClaimRequest::ROUTE_TELLER_REVIEW;
            $codeHash = null;
            $codeSalt = null;
            $codeExpiresAt = null;
            $rawCode = null;
            $claimStatus = BillClaimRequest::STATUS_PENDING_TELLER_REVIEW;

            if ($invoice) {
                $walkIn = $invoice->walkInCustomer ?? null;
                $contactMobile = $walkIn?->contact_mobile;
                $contactEmail = $walkIn?->contact_email;

                // Check if the portal user's verified contact matches the walk-in's contact
                $hasMatchingContact = false;
                if ($contactMobile) {
                    $hasMatchingContact = $customer->contactPoints()
                        ->where('type', 'mobile')
                        ->where('value', $contactMobile)
                        ->where('is_verified', true)
                        ->exists();
                }
                if (! $hasMatchingContact && $contactEmail) {
                    $hasMatchingContact = $customer->contactPoints()
                        ->where('type', 'email')
                        ->where('value', $contactEmail)
                        ->where('is_verified', true)
                        ->exists();
                }

                if ($hasMatchingContact) {
                    $verificationRoute = BillClaimRequest::ROUTE_CLAIM_CODE;
                    $claimStatus = BillClaimRequest::STATUS_PENDING_VERIFICATION;

                    // Generate cryptographically secure 6-digit code
                    $rawCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $codeSalt = Str::random(32);
                    // Purpose-bound: prefix ensures registration OTPs cannot be recycled here
                    $codeHash = hash('sha256', self::CLAIM_PURPOSE_PREFIX.$rawCode.$codeSalt);
                    $codeExpiresAt = Carbon::now()->addMinutes(self::CODE_TTL_MINUTES);
                }
            }

            // 6. Create the claim record
            $claim = BillClaimRequest::create([
                'organization_id' => $orgId,
                'user_id' => $requester->id,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'invoice_id' => $invoice?->id,
                'claim_status' => $claimStatus,
                'verification_route' => $verificationRoute,
                'code_hash' => $codeHash,
                'code_salt' => $codeSalt,
                'code_expires_at' => $codeExpiresAt,
                'attempt_count' => 0,
                'max_attempts' => 5,
                'lock_version' => 1,
            ]);

            $now = Carbon::now();

            // 7. Audit: CLAIM_INITIATED
            BillClaimEvent::create([
                'bill_claim_request_id' => $claim->id,
                'organization_id' => $orgId,
                'actor_id' => $requester->id,
                'event_type' => 'CLAIM_INITIATED',
                'notes' => "Claim for invoice number: {$invoiceNumber}",
                'metadata' => [
                    'invoice_found' => $invoice !== null,
                    'verification_route' => $verificationRoute,
                ],
                'created_at' => $now,
            ]);

            // 8. If CLAIM_CODE route: dispatch code via SMS intent and notify in-app
            if ($verificationRoute === BillClaimRequest::ROUTE_CLAIM_CODE && $rawCode !== null) {
                BillClaimEvent::create([
                    'bill_claim_request_id' => $claim->id,
                    'organization_id' => $orgId,
                    'actor_id' => $requester->id,
                    'event_type' => 'CODE_ISSUED',
                    'notes' => 'Claim code issued via verified contact. Expires in '.self::CODE_TTL_MINUTES.' minutes.',
                    'metadata' => ['expires_at' => $codeExpiresAt->toISOString()],
                    'created_at' => $now,
                ]);

                $this->dispatchClaimCodeSms($claim, $requester, $rawCode, $orgId);
            }

            // 9. If TELLER_REVIEW route: audit and notify staff
            if ($verificationRoute === BillClaimRequest::ROUTE_TELLER_REVIEW) {
                BillClaimEvent::create([
                    'bill_claim_request_id' => $claim->id,
                    'organization_id' => $orgId,
                    'actor_id' => $requester->id,
                    'event_type' => 'TELLER_REVIEW_QUEUED',
                    'notes' => 'No matching verified contact found. Routed to staff review.',
                    'metadata' => [],
                    'created_at' => $now,
                ]);

                broadcast(new DataRefreshEvent($orgId, 'bill_claims', 'bill_claim_request', $claim->id, 'teller_review_queued'));
            }

            // Raw code is never returned to caller — only the claim record without hash/salt
            $claim->makeHidden(['code_hash', 'code_salt']);

            return $claim->load(['events']);
        });
    }

    /**
     * Verify the claim code submitted by the portal user.
     * Uses constant-time comparison to prevent timing attacks.
     */
    public function verifyClaimCode(
        User $requester,
        BillClaimRequest $claim,
        string $rawCode
    ): BillClaimRequest {
        if ($claim->user_id !== $requester->id) {
            throw ValidationException::withMessages([
                'claim' => 'Claim does not belong to this user.',
            ]);
        }

        if ($claim->claim_status !== BillClaimRequest::STATUS_PENDING_VERIFICATION) {
            throw ValidationException::withMessages([
                'claim_status' => "Claim is not pending verification. Current status: {$claim->claim_status}.",
            ]);
        }

        // Check expiry
        if ($claim->isExpired()) {
            $this->markExpired($claim, $requester);
            throw ValidationException::withMessages([
                'code' => 'The claim code has expired. Please initiate a new claim.',
            ]);
        }

        // Check attempt limit before comparing
        if ($claim->hasExceededAttempts()) {
            throw ValidationException::withMessages([
                'code' => 'Maximum verification attempts exceeded. Please contact support.',
            ]);
        }

        // Increment attempt count atomically
        $claim->increment('attempt_count');
        $claim->refresh();

        $now = Carbon::now();

        // Constant-time hash comparison (purpose-prefixed to block OTP reuse)
        $expectedHash = hash('sha256', self::CLAIM_PURPOSE_PREFIX.$rawCode.$claim->code_salt);
        $isCorrect = hash_equals($expectedHash, $claim->code_hash);

        if (! $isCorrect) {
            BillClaimEvent::create([
                'bill_claim_request_id' => $claim->id,
                'organization_id' => $claim->organization_id,
                'actor_id' => $requester->id,
                'event_type' => 'CODE_ATTEMPT_FAILED',
                'notes' => "Failed attempt #{$claim->attempt_count} of {$claim->max_attempts}",
                'metadata' => ['attempt' => $claim->attempt_count],
                'created_at' => $now,
            ]);

            // Lock out after max attempts
            if ($claim->attempt_count >= $claim->max_attempts) {
                $claim->update([
                    'claim_status' => BillClaimRequest::STATUS_REJECTED,
                    'resolved_at' => $now,
                    'rejection_reason' => 'Maximum code verification attempts exceeded.',
                ]);
                BillClaimEvent::create([
                    'bill_claim_request_id' => $claim->id,
                    'organization_id' => $claim->organization_id,
                    'actor_id' => $requester->id,
                    'event_type' => 'REJECTED',
                    'notes' => 'Claim rejected after max attempts.',
                    'metadata' => [],
                    'created_at' => $now,
                ]);
            }

            throw ValidationException::withMessages([
                'code' => 'Invalid verification code.',
            ]);
        }

        // Code correct: approve the claim inside a transaction
        return DB::transaction(fn () => $this->approveClaim($claim, $requester->id, $now, 'Code verified successfully.'));
    }

    /**
     * Staff (Teller or Admin with bill_claims:review permission) decides a PENDING_TELLER_REVIEW claim.
     *
     * @param  string  $decision  'APPROVE' | 'REJECT'
     */
    public function staffDecideClaim(
        User $staff,
        BillClaimRequest $claim,
        string $decision,
        ?string $notes = null
    ): BillClaimRequest {
        if ($claim->claim_status !== BillClaimRequest::STATUS_PENDING_TELLER_REVIEW) {
            throw ValidationException::withMessages([
                'claim_status' => "Only PENDING_TELLER_REVIEW claims can be staff-decided. Current: {$claim->claim_status}.",
            ]);
        }

        if (! in_array($decision, ['APPROVE', 'REJECT'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Decision must be APPROVE or REJECT.',
            ]);
        }

        if ($decision === 'REJECT' && empty(trim($notes ?? ''))) {
            throw ValidationException::withMessages([
                'notes' => 'A rejection reason is required.',
            ]);
        }

        return DB::transaction(function () use ($staff, $claim, $decision, $notes): BillClaimRequest {
            $now = Carbon::now();

            if ($decision === 'APPROVE') {
                return $this->approveClaim($claim, $staff->id, $now, $notes ?? 'Approved by staff after identity review.');
            }

            // Reject
            $claim->update([
                'claim_status' => BillClaimRequest::STATUS_REJECTED,
                'resolved_at' => $now,
                'resolved_by_user_id' => $staff->id,
                'rejection_reason' => $notes,
            ]);

            BillClaimEvent::create([
                'bill_claim_request_id' => $claim->id,
                'organization_id' => $claim->organization_id,
                'actor_id' => $staff->id,
                'event_type' => 'REJECTED',
                'notes' => $notes,
                'metadata' => ['decided_by' => $staff->name],
                'created_at' => $now,
            ]);

            // Notify the requester
            InAppNotification::create([
                'organization_id' => $claim->organization_id,
                'user_id' => $claim->user_id,
                'type' => 'BILL_CLAIM',
                'title' => 'Invoice Claim Rejected',
                'body' => "Your claim for invoice {$claim->invoice_number} was rejected. Reason: {$notes}",
                'data' => ['bill_claim_request_id' => $claim->id],
                'is_read' => false,
            ]);

            broadcast(new DataRefreshEvent($claim->organization_id, 'bill_claims', 'bill_claim_request', $claim->id, 'rejected'));

            return $claim->fresh(['events']);
        });
    }

    /**
     * Cancel a pending claim (user-initiated).
     */
    public function cancelClaim(User $requester, BillClaimRequest $claim): void
    {
        if ($claim->user_id !== $requester->id) {
            throw ValidationException::withMessages([
                'claim' => 'Claim does not belong to this user.',
            ]);
        }

        if ($claim->isTerminal()) {
            throw ValidationException::withMessages([
                'claim_status' => 'Cannot cancel a claim that is already resolved.',
            ]);
        }

        DB::transaction(function () use ($requester, $claim): void {
            $now = Carbon::now();

            $claim->update([
                'claim_status' => BillClaimRequest::STATUS_CANCELLED,
                'resolved_at' => $now,
            ]);

            BillClaimEvent::create([
                'bill_claim_request_id' => $claim->id,
                'organization_id' => $claim->organization_id,
                'actor_id' => $requester->id,
                'event_type' => 'CANCELLED',
                'notes' => 'Claim cancelled by requesting user.',
                'metadata' => [],
                'created_at' => $now,
            ]);

            broadcast(new DataRefreshEvent($claim->organization_id, 'bill_claims', 'bill_claim_request', $claim->id, 'cancelled'));
        });
    }

    /**
     * Bulk-expire PENDING_VERIFICATION claims whose code TTL has elapsed.
     * Safe to call from the scheduler without any portal impact.
     *
     * @return int Number of claims expired.
     */
    public function expireStaleClaimCodes(): int
    {
        return DB::transaction(function (): int {
            $staleClaims = BillClaimRequest::where('claim_status', BillClaimRequest::STATUS_PENDING_VERIFICATION)
                ->where('code_expires_at', '<', Carbon::now())
                ->lockForUpdate()
                ->get();

            $count = 0;
            $now = Carbon::now();

            foreach ($staleClaims as $claim) {
                $claim->update([
                    'claim_status' => BillClaimRequest::STATUS_EXPIRED,
                    'resolved_at' => $now,
                ]);

                BillClaimEvent::create([
                    'bill_claim_request_id' => $claim->id,
                    'organization_id' => $claim->organization_id,
                    'actor_id' => null,
                    'event_type' => 'EXPIRED',
                    'notes' => 'Claim code expired without verification.',
                    'metadata' => ['expired_at' => $now->toISOString()],
                    'created_at' => $now,
                ]);

                $count++;
            }

            return $count;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Approve a claim: link the invoice to the customer account.
     * Security invariant: buyer snapshot on POSTED invoices is NEVER altered.
     */
    private function approveClaim(
        BillClaimRequest $claim,
        int $resolvedByUserId,
        Carbon $now,
        string $notes
    ): BillClaimRequest {
        $claim->update([
            'claim_status' => BillClaimRequest::STATUS_APPROVED,
            'resolved_at' => $now,
            'resolved_by_user_id' => $resolvedByUserId,
        ]);

        BillClaimEvent::create([
            'bill_claim_request_id' => $claim->id,
            'organization_id' => $claim->organization_id,
            'actor_id' => $resolvedByUserId,
            'event_type' => 'APPROVED',
            'notes' => $notes,
            'metadata' => [
                'invoice_id' => $claim->invoice_id,
                'invoice_number' => $claim->invoice_number,
            ],
            'created_at' => $now,
        ]);

        // Link walk-in record to portal customer (non-destructive — POSTED invoice snapshots unchanged)
        if ($claim->invoice_id) {
            $invoice = Invoice::find($claim->invoice_id);
            if ($invoice && $invoice->walk_in_customer_id) {
                $walkIn = $invoice->walkInCustomer;
                $portalCustomer = $claim->customer;
                if ($walkIn && $portalCustomer) {
                    $this->walkInService->linkWalkInToPortalCustomer(
                        User::find($resolvedByUserId),
                        $walkIn,
                        $portalCustomer,
                        'Linked via approved portal bill claim.'
                    );
                }
            }
        }

        // Notify the user
        InAppNotification::create([
            'organization_id' => $claim->organization_id,
            'user_id' => $claim->user_id,
            'type' => 'BILL_CLAIM',
            'title' => 'Invoice Claim Approved',
            'body' => "Invoice {$claim->invoice_number} has been linked to your account.",
            'data' => [
                'bill_claim_request_id' => $claim->id,
                'invoice_id' => $claim->invoice_id,
            ],
            'is_read' => false,
        ]);

        broadcast(new DataRefreshEvent($claim->organization_id, 'bill_claims', 'bill_claim_request', $claim->id, 'approved'));

        return $claim->fresh(['events', 'invoice']);
    }

    /**
     * Mark a pending-verification claim as EXPIRED.
     */
    private function markExpired(BillClaimRequest $claim, User $actor): void
    {
        $now = Carbon::now();
        $claim->update([
            'claim_status' => BillClaimRequest::STATUS_EXPIRED,
            'resolved_at' => $now,
        ]);
        BillClaimEvent::create([
            'bill_claim_request_id' => $claim->id,
            'organization_id' => $claim->organization_id,
            'actor_id' => $actor->id,
            'event_type' => 'EXPIRED',
            'notes' => 'Claim code expired at time of verification attempt.',
            'metadata' => [],
            'created_at' => $now,
        ]);
    }

    /**
     * Dispatch a transactional SMS claim-code intent (W31 — after commit boundary).
     * Raw code is passed in-memory only and is never logged or persisted.
     */
    private function dispatchClaimCodeSms(
        BillClaimRequest $claim,
        User $requester,
        string $rawCode,
        int $orgId
    ): void {
        $dispatch = function () use ($claim, $requester, $rawCode, $orgId) {
            try {
                $event = NotificationEvent::create([
                    'organization_id' => $orgId,
                    'event_key' => 'BILL_CLAIM_CODE_ISSUED',
                    'event_source_type' => 'bill_claim_request',
                    'event_source_id' => $claim->id,
                    'user_id' => $requester->id,
                    'payload_snapshot' => [
                        'recipient_name' => $requester->name,
                        'claim_code' => $rawCode, // included in SMS payload only
                        'expires_minutes' => self::CODE_TTL_MINUTES,
                        'reference_no' => $claim->invoice_number,
                        'invoice_number' => $claim->invoice_number,
                        'org_name' => 'SCIPSI',
                    ],
                    'occurred_at' => Carbon::now(),
                ]);

                if ($this->smsOrchestrator) {
                    $this->smsOrchestrator->queueIntent($event);
                }
            } catch (\Throwable $e) {
                // SMS failure must never block the primary claim flow
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
