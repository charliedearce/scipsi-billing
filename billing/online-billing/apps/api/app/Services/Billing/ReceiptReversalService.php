<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentCorrectionLink;
use App\Models\DocumentCorrectionRequest;
use App\Models\DocumentCorrectionRequestEvent;
use App\Models\DocumentRevision;
use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\WithholdingApplication;
use App\Services\Audit\DocumentRevisionService;
use App\Services\Sms\NotificationEventRecorder;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * P3-04 settlement-only receipt reversal.
 * Restores invoice collectible balances and withholding certificate capacity exactly once.
 * Does not issue a fiscal credit note, cancel a printed number, or rewrite the original PDF.
 */
class ReceiptReversalService
{
    public const TIMEZONE = 'Asia/Manila';

    public function __construct(
        protected DocumentRevisionService $revisionService,
        protected NotificationEventRecorder $notificationEvents,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
    ) {}

    public function execute(DocumentCorrectionRequest $request, User $actor, string $notes): Receipt
    {
        if ($request->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Correction request is outside your organization scope.');
        }
        if (trim($notes) === '' || strlen(trim($notes)) < 5) {
            throw ValidationException::withMessages(['execution_notes' => ['Execution notes of at least five characters are required.']]);
        }

        $receipt = DB::transaction(function () use ($request, $actor, $notes): Receipt {
            $lockedRequest = DocumentCorrectionRequest::where('organization_id', $actor->organization_id)
                ->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->requested_action !== DocumentCorrectionRequest::ACTION_RECEIPT_REVERSAL) {
                throw ValidationException::withMessages([
                    'requested_action' => ['Only an approved RECEIPT_REVERSAL request can execute settlement reversal under P3-04.'],
                ]);
            }
            $selfExecution = $lockedRequest->requested_by_user_id === $actor->id;
            if ($selfExecution && ! $actor->hasRole('Administrator')) {
                throw ValidationException::withMessages([
                    'executor' => ['The requester cannot execute their own receipt reversal.'],
                ]);
            }

            if ($lockedRequest->status === DocumentCorrectionRequest::STATUS_EXECUTED) {
                return Receipt::with(['allocations', 'withholdingApplications', 'tenders', 'series'])
                    ->findOrFail($lockedRequest->receipt_id);
            }
            if ($lockedRequest->status !== DocumentCorrectionRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => ['Receipt reversal execution requires an APPROVED correction request.'],
                ]);
            }

            $receipt = Receipt::where('organization_id', $actor->organization_id)
                ->whereKey($lockedRequest->receipt_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status === 'REVERSED') {
                $this->markExecuted($lockedRequest, $actor, $notes, $receipt, reused: true);

                return $receipt->fresh(['allocations', 'withholdingApplications', 'tenders', 'series']);
            }
            if ($receipt->status !== 'POSTED') {
                throw ValidationException::withMessages([
                    'receipt' => ["Receipt {$receipt->id} is {$receipt->status} and cannot be reversed."],
                ]);
            }

            $latestRevision = DocumentRevision::where('organization_id', $receipt->organization_id)
                ->where('document_type', 'RECEIPT')
                ->where('document_id', $receipt->id)
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->firstOrFail();
            if (
                $receipt->lock_version !== $lockedRequest->target_lock_version
                || ! hash_equals($lockedRequest->target_snapshot_hash, $latestRevision->snapshot_hash)
            ) {
                throw new ConcurrencyException(
                    'The receipt changed after the correction request was approved. Create a new reversal request from the current receipt state.'
                );
            }

            app(CustomerPaymentCreditService::class)->reverseUnusedForReceipt($receipt, $actor);

            $invoiceIds = ReceiptAllocation::where('receipt_id', $receipt->id)
                ->orderBy('invoice_id')
                ->pluck('invoice_id')
                ->unique()
                ->values()
                ->all();
            if ($invoiceIds !== []) {
                Invoice::where('organization_id', $receipt->organization_id)
                    ->whereIn('id', $invoiceIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $certificateIds = WithholdingApplication::where('receipt_id', $receipt->id)
                ->orderBy('certificate_id')
                ->pluck('certificate_id')
                ->unique()
                ->values()
                ->all();
            $certificates = CustomerWithholdingCertificate::where('organization_id', $receipt->organization_id)
                ->whereIn('id', $certificateIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $restoredCapacity = [];
            foreach (WithholdingApplication::where('receipt_id', $receipt->id)->orderBy('id')->get() as $application) {
                $certificate = $certificates->get($application->certificate_id);
                if (! $certificate) {
                    throw ValidationException::withMessages([
                        'certificate' => ["Withholding certificate {$application->certificate_id} is unavailable for capacity restoration."],
                    ]);
                }
                $amount = (string) $application->applied_amount;
                $certificate->update([
                    'allocated_amount' => bcsub((string) $certificate->allocated_amount, $amount, 2),
                    'remaining_amount' => bcadd((string) $certificate->remaining_amount, $amount, 2),
                    'lock_version' => $certificate->lock_version + 1,
                ]);
                $restoredCapacity[] = [
                    'certificate_id' => $certificate->id,
                    'restored_amount' => $amount,
                    'remaining_amount' => (string) $certificate->fresh()->remaining_amount,
                ];
            }

            $now = Carbon::now(self::TIMEZONE);
            $receipt->update([
                'status' => 'REVERSED',
                'lock_version' => $receipt->lock_version + 1,
            ]);
            app(LateChargeAssessmentService::class)->reconcileVoidedAfterReceiptReversal($receipt, $actor);

            $existingLink = DocumentCorrectionLink::where('organization_id', $receipt->organization_id)
                ->where('original_document_type', 'RECEIPT')
                ->where('original_document_id', $receipt->id)
                ->where('correction_type', 'REVERSAL')
                ->lockForUpdate()
                ->first();
            if (! $existingLink) {
                DocumentCorrectionLink::create([
                    'organization_id' => $receipt->organization_id,
                    'original_document_type' => 'RECEIPT',
                    'original_document_id' => $receipt->id,
                    'correction_document_type' => 'DOCUMENT_CORRECTION_REQUEST',
                    'correction_document_id' => $lockedRequest->id,
                    'correction_type' => 'REVERSAL',
                    'reason' => trim($notes),
                    'approved_by' => $actor->id,
                    'created_at' => $now,
                ]);
            }

            $fresh = $receipt->fresh(['tenders', 'allocations.invoice', 'withholdingApplications.certificate', 'series']);
            $this->revisionService->createRevision(
                $receipt->organization_id,
                $receipt->location_id,
                'RECEIPT',
                $receipt->id,
                $actor,
                $fresh->toArray(),
                'Settlement-only receipt reversal restored invoice balances and certificate capacity once.',
                $latestRevision->lock_version,
            );

            $this->markExecuted($lockedRequest, $actor, $notes, $fresh, reused: false, restoredCapacity: $restoredCapacity);

            AuditEvent::create([
                'organization_id' => $receipt->organization_id,
                'location_id' => $receipt->location_id,
                'event_type' => 'RECEIPT_REVERSED',
                'aggregate_type' => 'RECEIPT',
                'aggregate_id' => $receipt->id,
                'aggregate_version' => $fresh->lock_version,
                'actor_type' => 'user',
                'actor_id' => $actor->id,
                'permission_snapshot' => 'receipts:reverse',
                'occurred_at' => $now,
                'business_date' => $receipt->business_date,
                'reason' => trim($notes),
                'metadata' => [
                    'correction_request_id' => $lockedRequest->id,
                    'self_execution' => $selfExecution,
                    'receipt_number' => $receipt->receipt_number,
                    'applied_amount' => (string) $receipt->applied_amount,
                    'unapplied_amount' => (string) $receipt->unapplied_amount,
                    'restored_certificate_capacity' => $restoredCapacity,
                    'fiscal_document_created' => false,
                    'original_pdf_rewritten' => false,
                    'number_cancelled' => false,
                ],
            ]);

            $this->dispatchReversalNotice($fresh);

            return $fresh;
        });

        return $receipt->fresh(['allocations', 'withholdingApplications', 'tenders', 'series', 'canonicalArtifact']);
    }

    /**
     * Queue a local RECEIPT_REVERSED intent only after settlement reversal commits.
     * Safe payload carries the receipt number only; notification failure never alters
     * receipt, allocation, certificate capacity or fiscal state.
     */
    protected function dispatchReversalNotice(Receipt $receipt): void
    {
        try {
            if ($receipt->status !== 'REVERSED') {
                return;
            }

            $alreadyDispatched = NotificationEvent::where('organization_id', $receipt->organization_id)
                ->where('event_key', 'RECEIPT_REVERSED')
                ->where('event_source_type', 'receipt')
                ->where('event_source_id', $receipt->id)
                ->exists();
            if ($alreadyDispatched) {
                return;
            }

            $receipt->loadMissing(['customer.users', 'organization']);
            $recipientUser = $receipt->customer?->users()->wherePivot('is_active', true)->first()
                ?? $receipt->customer?->users()->first();
            if (! $recipientUser) {
                return;
            }

            $payload = [
                'recipient_name' => $recipientUser->name,
                'reference_no' => $receipt->receipt_number,
                'action_label' => 'collection receipt reversal',
                'org_name' => $receipt->organization?->name ?? 'SCIPSI',
                'date_formatted' => Carbon::now(self::TIMEZONE)->format('M d, Y'),
            ];

            $dispatch = function () use ($receipt, $recipientUser, $payload): void {
                try {
                    $event = $this->notificationEvents->record([
                        'organization_id' => $receipt->organization_id,
                        'event_key' => 'RECEIPT_REVERSED',
                        'event_source_type' => 'receipt',
                        'event_source_id' => $receipt->id,
                        'user_id' => $recipientUser->id,
                        'payload_snapshot' => $payload,
                        'occurred_at' => Carbon::now(self::TIMEZONE),
                    ], $receipt->location_id === null ? [] : [(int) $receipt->location_id]);

                    $this->smsOrchestrator->queueIntent($event);
                } catch (Throwable $exception) {
                    report($exception);
                }
            };

            if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param list<array<string, mixed>> $restoredCapacity */
    protected function markExecuted(
        DocumentCorrectionRequest $request,
        User $actor,
        string $notes,
        Receipt $receipt,
        bool $reused,
        array $restoredCapacity = [],
    ): void {
        if ($request->status !== DocumentCorrectionRequest::STATUS_EXECUTED) {
            $request->update(['status' => DocumentCorrectionRequest::STATUS_EXECUTED]);
            DocumentCorrectionRequestEvent::create([
                'correction_request_id' => $request->id,
                'actor_id' => $actor->id,
                'event_type' => $reused ? 'REVERSAL_ALREADY_EXECUTED' : 'REVERSAL_EXECUTED',
                'from_status' => DocumentCorrectionRequest::STATUS_APPROVED,
                'to_status' => DocumentCorrectionRequest::STATUS_EXECUTED,
                'notes' => trim($notes),
                'metadata' => [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'restored_certificate_capacity' => $restoredCapacity,
                    'fiscal_document_created' => false,
                ],
            ]);
        }
    }
}
