<?php

namespace App\Services\Billing;

use App\Events\DataRefreshEvent;
use App\Models\BillingRequest;
use App\Models\BillingRequestDocument;
use App\Models\BillingRequestEvent;
use App\Models\Customer;
use App\Models\DocumentRequirement;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\PrivateFile;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingRequestQueueService
{
    public function __construct(
        protected SmsDeliveryOrchestrator $smsOrchestrator
    ) {}

    /**
     * Create a new billing request draft.
     */
    public function createDraft(
        User $actor,
        Customer $customer,
        int $locationId,
        string $serviceType,
        ?string $customerNotes = null
    ): BillingRequest {
        return DB::transaction(function () use ($actor, $customer, $locationId, $serviceType, $customerNotes) {
            $yearMonth = Carbon::now()->format('Ym');
            $uniqueSuffix = strtoupper(Str::random(6));
            $transactionNo = "REQ-{$yearMonth}-{$uniqueSuffix}";

            $request = BillingRequest::create([
                'organization_id' => $customer->organization_id,
                'location_id' => $locationId,
                'customer_id' => $customer->id,
                'created_by_user_id' => $actor->id,
                'service_type' => $serviceType,
                'transaction_no' => $transactionNo,
                'status' => BillingRequest::STATUS_DRAFT,
                'correction_notes' => null,
                'internal_notes' => null,
                'lock_version' => 1,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $actor->id,
                'event_type' => 'DRAFT_CREATED',
                'from_status' => null,
                'to_status' => BillingRequest::STATUS_DRAFT,
                'notes' => $customerNotes ? "Draft created with notes: {$customerNotes}" : 'Draft created',
                'metadata' => ['service_type' => $serviceType],
                'created_at' => Carbon::now(),
            ]);

            return $request->load(['customer', 'location', 'documents']);
        });
    }

    /**
     * Attach an uploaded private file to a billing request draft or correction.
     */
    public function attachDocument(
        BillingRequest $request,
        User $actor,
        int $documentTypeId,
        int $privateFileId,
        ?int $requirementId = null,
        ?string $customerNotes = null
    ): BillingRequestDocument {
        if (! in_array($request->status, [BillingRequest::STATUS_DRAFT, BillingRequest::STATUS_NEEDS_CORRECTION])) {
            throw ValidationException::withMessages([
                'status' => "Cannot attach documents when request is in status {$request->status}.",
            ]);
        }

        $file = PrivateFile::where('id', $privateFileId)
            ->where('organization_id', $request->organization_id)
            ->firstOrFail();

        $latestVersion = $file->latestVersion;
        if (! $latestVersion || $latestVersion->scan_status !== 'CLEAN') {
            throw ValidationException::withMessages([
                'private_file_id' => 'The selected file is quarantined, un-scanned, or unsafe.',
            ]);
        }

        return DB::transaction(function () use ($request, $documentTypeId, $privateFileId, $requirementId, $customerNotes, $latestVersion) {
            $doc = BillingRequestDocument::updateOrCreate(
                [
                    'billing_request_id' => $request->id,
                    'document_type_id' => $documentTypeId,
                ],
                [
                    'document_requirement_id' => $requirementId,
                    'private_file_id' => $privateFileId,
                    'reviewed_version_number' => $latestVersion->version_number,
                    'review_status' => BillingRequestDocument::STATUS_PENDING,
                    'rejection_reason' => null,
                    'customer_notes' => $customerNotes,
                ]
            );

            return $doc->load(['documentType', 'privateFile']);
        });
    }

    /**
     * Submit a billing request and automatically admit it to the teller queue (Decision W21).
     * Validates completeness against active DocumentRequirement rules for the service.
     */
    public function submitRequest(BillingRequest $request, User $actor): BillingRequest
    {
        if ($request->status !== BillingRequest::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Only DRAFT requests can be submitted. Current status: {$request->status}.",
            ]);
        }

        // 1. Validate requirements completeness
        $requirements = DocumentRequirement::where('organization_id', $request->organization_id)
            ->where(function ($q) use ($request) {
                $q->where('location_id', $request->location_id)
                    ->orWhereNull('location_id');
            })
            ->where('service_type', $request->service_type)
            ->where('is_required', true)
            ->with('documentType')
            ->get();

        $attachedDocs = $request->documents()->with('privateFile.latestVersion')->get()->keyBy('document_type_id');
        $missingRequirements = [];

        foreach ($requirements as $req) {
            $attached = $attachedDocs->get($req->document_type_id);
            if (! $attached) {
                $missingRequirements[] = $req->documentType->name ?? "Doc Type ID {$req->document_type_id}";

                continue;
            }

            $version = $attached->privateFile?->latestVersion;
            if (! $version || $version->scan_status !== 'CLEAN') {
                $missingRequirements[] = "{$req->documentType->name} (file quarantined or unverified)";
            }
        }

        if (! empty($missingRequirements)) {
            throw ValidationException::withMessages([
                'requirements' => 'Missing or unverified required documents: '.implode(', ', $missingRequirements),
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $requirements) {
            $now = Carbon::now();

            // 2. Allocate Monotonic Queue Ticket Number (Decision W22)
            $ticketNumber = QueueTicket::nextTicketNumber($request->organization_id, $request->location_id);

            // 3. Freeze requirement snapshot
            $reqSnapshot = $requirements->map(fn ($r) => [
                'requirement_id' => $r->id,
                'document_type_id' => $r->document_type_id,
                'document_type_code' => $r->documentType?->code,
                'document_type_name' => $r->documentType?->name,
                'version' => $r->version,
            ])->toArray();

            $request->update([
                'ticket_number' => $ticketNumber,
                'requirement_snapshot' => $reqSnapshot,
                'initial_submitted_at' => $now, // Immutable first submission time for queue priority
                'submitted_at' => $now,
                'admitted_at' => $now,
                'status' => BillingRequest::STATUS_QUEUED,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $actor->id,
                'event_type' => 'SUBMITTED',
                'from_status' => BillingRequest::STATUS_DRAFT,
                'to_status' => BillingRequest::STATUS_QUEUED,
                'notes' => "Auto-admitted to queue with Ticket #{$ticketNumber}",
                'metadata' => [
                    'ticket_number' => $ticketNumber,
                    'transaction_no' => $request->transaction_no,
                ],
                'created_at' => $now,
            ]);

            // 4. In-App Notification to Customer
            InAppNotification::create([
                'organization_id' => $request->organization_id,
                'user_id' => $request->created_by_user_id,
                'type' => 'QUEUE',
                'title' => 'Billing Request Admitted',
                'body' => "Your billing request {$request->transaction_no} has been queued with ticket #{$ticketNumber}.",
                'data' => [
                    'billing_request_id' => $request->id,
                    'transaction_no' => $request->transaction_no,
                    'ticket_number' => $ticketNumber,
                ],
                'is_read' => false,
            ]);

            // 5. Outbound Transactional SMS Intent (Decision W31)
            $this->dispatchTransactionalNotice(
                $request,
                'BILLING_REQUEST_QUEUED',
                [
                    'recipient_name' => $actor->name,
                    'reference_no' => $request->transaction_no,
                    'queue_ticket' => (string) $ticketNumber,
                    'org_name' => $request->organization?->name ?? 'SCIPSI',
                ]
            );

            // 6. Realtime Refresh Broadcast
            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'queued'
            ));

            return $request->fresh(['customer', 'location', 'documents.documentType']);
        });
    }

    /**
     * Atomically claim the oldest eligible request in the queue (Decision W22).
     * Uses pessimistic locking (SELECT ... FOR UPDATE SKIP LOCKED) ordered by (initial_submitted_at ASC, ticket_number ASC).
     */
    public function claimNextEligible(
        User $teller,
        int $organizationId,
        int $locationId,
        ?string $serviceType = null
    ): ?BillingRequest {
        return DB::transaction(function () use ($teller, $organizationId, $locationId, $serviceType) {
            $query = BillingRequest::where('organization_id', $organizationId)
                ->where('location_id', $locationId)
                ->where('status', BillingRequest::STATUS_QUEUED);

            if ($serviceType) {
                $query->where('service_type', $serviceType);
            }

            // Oldest-eligible claim: order by initial_submitted_at ASC, ticket_number ASC
            $request = $query->orderBy('initial_submitted_at', 'asc')
                ->orderBy('ticket_number', 'asc')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if (! $request) {
                return null;
            }

            $now = Carbon::now();
            $request->update([
                'status' => BillingRequest::STATUS_IN_REVIEW,
                'assigned_to_user_id' => $teller->id,
                'assigned_at' => $now,
                'assignment_heartbeat_at' => $now,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'CLAIMED',
                'from_status' => BillingRequest::STATUS_QUEUED,
                'to_status' => BillingRequest::STATUS_IN_REVIEW,
                'notes' => "Claimed by teller {$teller->name}",
                'metadata' => [
                    'teller_id' => $teller->id,
                    'teller_name' => $teller->name,
                ],
                'created_at' => $now,
            ]);

            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'claimed'
            ));

            return $request->fresh(['customer', 'location', 'documents.documentType', 'documents.privateFile.latestVersion']);
        });
    }

    /**
     * Extend teller assignment lease heartbeat.
     */
    public function heartbeat(BillingRequest $request, User $teller): void
    {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'You are not the assigned teller for this request.',
            ]);
        }

        $request->update([
            'assignment_heartbeat_at' => Carbon::now(),
        ]);
    }

    /**
     * Teller returns request to customer for document corrections (Decision W22).
     * Deficient documents are marked, customer note is recorded, original priority timestamp is preserved.
     */
    public function requestCorrection(
        BillingRequest $request,
        User $teller,
        string $correctionNotes,
        array $fileRemarks = []
    ): BillingRequest {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only the assigned teller can request corrections on this request.',
            ]);
        }

        if (! in_array($request->status, [BillingRequest::STATUS_IN_REVIEW, BillingRequest::STATUS_BILLING_IN_PROGRESS])) {
            throw ValidationException::withMessages([
                'status' => "Cannot request correction when request is in status {$request->status}.",
            ]);
        }

        return DB::transaction(function () use ($request, $teller, $correctionNotes, $fileRemarks) {
            $now = Carbon::now();

            // Mark specified documents as NEEDS_CORRECTION
            foreach ($fileRemarks as $remark) {
                $docTypeId = $remark['document_type_id'] ?? null;
                $reason = $remark['rejection_reason'] ?? 'Deficient or illegible document';

                if ($docTypeId) {
                    $doc = $request->documents()->where('document_type_id', $docTypeId)->first();
                    if ($doc) {
                        $doc->update([
                            'review_status' => BillingRequestDocument::STATUS_NEEDS_CORRECTION,
                            'rejection_reason' => $reason,
                        ]);
                    }
                }
            }

            $request->update([
                'status' => BillingRequest::STATUS_NEEDS_CORRECTION,
                'correction_rounds' => $request->correction_rounds + 1,
                'correction_notes' => $correctionNotes,
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'assignment_heartbeat_at' => null,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'CORRECTION_REQUESTED',
                'from_status' => BillingRequest::STATUS_IN_REVIEW,
                'to_status' => BillingRequest::STATUS_NEEDS_CORRECTION,
                'notes' => $correctionNotes,
                'metadata' => [
                    'file_remarks' => $fileRemarks,
                    'round' => $request->correction_rounds,
                ],
                'created_at' => $now,
            ]);

            // Notify Customer
            InAppNotification::create([
                'organization_id' => $request->organization_id,
                'user_id' => $request->created_by_user_id,
                'type' => 'QUEUE',
                'title' => 'Document Correction Required',
                'body' => "Your request {$request->transaction_no} requires correction: {$correctionNotes}",
                'data' => [
                    'billing_request_id' => $request->id,
                    'transaction_no' => $request->transaction_no,
                    'correction_notes' => $correctionNotes,
                ],
                'is_read' => false,
            ]);

            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'correction_requested'
            ));

            // Outbound Transactional SMS Intent (Decision W31)
            $this->dispatchTransactionalNotice(
                $request,
                'BILLING_REQUEST_CORRECTION_REQUIRED',
                [
                    'recipient_name' => $request->customer?->name ?? 'Valued Customer',
                    'reference_no' => $request->transaction_no,
                    'action_label' => 'Document Correction Required',
                    'org_name' => $request->organization?->name ?? 'SCIPSI',
                ]
            );

            return $request->fresh(['customer', 'location', 'documents.documentType']);
        });
    }

    /**
     * Customer resubmits corrected documents (Decision W22).
     * Crucial fairness invariant: initial_submitted_at is PRESERVED, keeping original queue priority!
     */
    public function resubmit(BillingRequest $request, User $actor, ?string $customerNotes = null): BillingRequest
    {
        if ($request->status !== BillingRequest::STATUS_NEEDS_CORRECTION) {
            throw ValidationException::withMessages([
                'status' => "Only requests in NEEDS_CORRECTION can be resubmitted. Current status: {$request->status}.",
            ]);
        }

        // Validate that all deficient documents have been replaced with a newer CLEAN version
        $deficientDocs = $request->documents()->where('review_status', BillingRequestDocument::STATUS_NEEDS_CORRECTION)->get();
        $unresolvedDocs = [];

        foreach ($deficientDocs as $doc) {
            $latestVersion = $doc->privateFile?->latestVersion;
            if (! $latestVersion || $latestVersion->version_number <= $doc->reviewed_version_number) {
                $unresolvedDocs[] = $doc->documentType?->name ?? "Doc Type ID {$doc->document_type_id}";
            } elseif ($latestVersion->scan_status !== 'CLEAN') {
                $unresolvedDocs[] = "{$doc->documentType?->name} (replacement file is not clean)";
            }
        }

        if (! empty($unresolvedDocs)) {
            throw ValidationException::withMessages([
                'documents' => 'Please upload clean replacement versions for: '.implode(', ', $unresolvedDocs),
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $customerNotes, $deficientDocs) {
            $now = Carbon::now();

            // Update reviewed version numbers to latest and set review status back to PENDING
            foreach ($deficientDocs as $doc) {
                $latestVersion = $doc->privateFile->latestVersion;
                $doc->update([
                    'reviewed_version_number' => $latestVersion->version_number,
                    'review_status' => BillingRequestDocument::STATUS_PENDING,
                    'rejection_reason' => null,
                ]);
            }

            // CRITICAL INVARIANT: initial_submitted_at remains untouched!
            // Priority is maintained exactly as originally submitted.
            $request->update([
                'status' => BillingRequest::STATUS_QUEUED,
                'submitted_at' => $now, // record resubmission timestamp
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $actor->id,
                'event_type' => 'RESUBMITTED',
                'from_status' => BillingRequest::STATUS_NEEDS_CORRECTION,
                'to_status' => BillingRequest::STATUS_QUEUED,
                'notes' => $customerNotes ? "Resubmitted with note: {$customerNotes}" : 'Resubmitted after document replacement',
                'metadata' => [
                    'original_priority_at' => $request->initial_submitted_at->toISOString(),
                    'ticket_number' => $request->ticket_number,
                ],
                'created_at' => $now,
            ]);

            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'resubmitted'
            ));

            return $request->fresh(['customer', 'location', 'documents.documentType']);
        });
    }

    /**
     * Teller accepts documents and prepares an invoice draft for the request.
     */
    public function prepareBillingDraft(BillingRequest $request, User $teller): Invoice
    {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only the assigned teller can prepare a draft invoice for this request.',
            ]);
        }

        if ($request->draft_invoice_id) {
            $existingDraft = Invoice::find($request->draft_invoice_id);
            if ($existingDraft && $existingDraft->status === 'DRAFT') {
                return $existingDraft;
            }
        }

        return DB::transaction(function () use ($request, $teller) {
            $customer = $request->customer;
            $buyerProfileVersion = $customer->buyerProfile?->currentVersion();

            $invoice = Invoice::create([
                'organization_id' => $request->organization_id,
                'location_id' => $request->location_id,
                'customer_id' => $request->customer_id,
                'buyer_profile_version_id' => $buyerProfileVersion?->id,
                'status' => 'DRAFT',
                'business_date' => Carbon::now()->toDateString(),
                'currency' => 'PHP',
                'base_gross_amount' => '0.00',
                'fuel_surcharge_amount' => '0.00',
                'gross_amount' => '0.00',
                'ppa_amount' => '0.00',
                'discount_amount' => '0.00',
                'net_amount' => '0.00',
                'tax_amount' => '0.00',
                'total_charge_amount' => '0.00',
                'notes' => "Generated from Billing Request {$request->transaction_no}",
                'created_by_user_id' => $teller->id,
            ]);

            $request->update([
                'draft_invoice_id' => $invoice->id,
                'status' => BillingRequest::STATUS_BILLING_IN_PROGRESS,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'DRAFT_PREPARED',
                'from_status' => BillingRequest::STATUS_IN_REVIEW,
                'to_status' => BillingRequest::STATUS_BILLING_IN_PROGRESS,
                'notes' => "Invoice draft #{$invoice->id} initialized",
                'metadata' => ['invoice_id' => $invoice->id],
                'created_at' => Carbon::now(),
            ]);

            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'draft_prepared'
            ));

            return $invoice;
        });
    }

    /**
     * Mark billing request as complete and bill-ready upon invoice posting.
     * Guaranteed idempotent: retries never post duplicate invoices or notifications.
     */
    public function completeBillReady(BillingRequest $request, Invoice $invoice, User $teller): BillingRequest
    {
        if ($invoice->customer_id !== $request->customer_id) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice customer does not match the billing request customer.',
            ]);
        }

        if ($invoice->status !== 'POSTED') {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot mark bill-ready with an unposted invoice.',
            ]);
        }

        // Idempotency: if already BILL_READY and linked to this invoice, return safely
        if ($request->status === BillingRequest::STATUS_BILL_READY && $request->invoice_id === $invoice->id) {
            return $request;
        }

        return DB::transaction(function () use ($request, $invoice, $teller) {
            $now = Carbon::now();

            $request->update([
                'status' => BillingRequest::STATUS_BILL_READY,
                'invoice_id' => $invoice->id,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'BILL_READY',
                'from_status' => $request->status,
                'to_status' => BillingRequest::STATUS_BILL_READY,
                'notes' => "Official invoice {$invoice->invoice_number} ready for customer",
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'created_at' => $now,
            ]);

            // In-app notification
            InAppNotification::create([
                'organization_id' => $request->organization_id,
                'user_id' => $request->created_by_user_id,
                'type' => 'INVOICE',
                'title' => 'Invoice Ready',
                'body' => "Your official invoice {$invoice->invoice_number} is ready for viewing and payment.",
                'data' => [
                    'billing_request_id' => $request->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'is_read' => false,
            ]);

            // Transactional SMS notice (Decision W31)
            $this->dispatchTransactionalNotice(
                $request,
                'INVOICE_ARTIFACT_READY',
                [
                    'recipient_name' => $request->customer->name,
                    'reference_no' => $invoice->invoice_number,
                    'org_name' => $request->organization?->name ?? 'SCIPSI',
                ]
            );

            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'bill_ready'
            ));

            return $request->fresh(['customer', 'location', 'invoice']);
        });
    }

    /**
     * Release teller assignment back to the queue (e.g. teller stepping away).
     * Original queue priority is completely preserved.
     */
    public function releaseAssignment(BillingRequest $request, User $teller, ?string $reason = null): BillingRequest
    {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'You are not the assigned teller for this request.',
            ]);
        }

        return DB::transaction(function () use ($request, $teller, $reason) {
            $now = Carbon::now();

            $request->update([
                'status' => BillingRequest::STATUS_QUEUED,
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'assignment_heartbeat_at' => null,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'RELEASED',
                'from_status' => BillingRequest::STATUS_IN_REVIEW,
                'to_status' => BillingRequest::STATUS_QUEUED,
                'notes' => $reason ? "Released: {$reason}" : 'Released back to queue by teller',
                'metadata' => ['original_priority_at' => $request->initial_submitted_at->toISOString()],
                'created_at' => $now,
            ]);

            broadcast(new DataRefreshEvent(
                $request->organization_id,
                'queue',
                'billing_request',
                $request->id,
                'released'
            ));

            return $request;
        });
    }

    /**
     * Recover stale teller assignments (Decision W22).
     * Requests whose heartbeat exceeded threshold are reset to QUEUED, retaining original queue priority.
     */
    public function recoverStaleAssignments(int $staleMinutes = 15): int
    {
        $cutoff = Carbon::now()->subMinutes($staleMinutes);

        return DB::transaction(function () use ($cutoff, $staleMinutes) {
            $staleRequests = BillingRequest::where('status', BillingRequest::STATUS_IN_REVIEW)
                ->where('assignment_heartbeat_at', '<', $cutoff)
                ->lockForUpdate()
                ->get();

            $count = 0;
            $now = Carbon::now();

            foreach ($staleRequests as $request) {
                $previousTellerId = $request->assigned_to_user_id;

                $request->update([
                    'status' => BillingRequest::STATUS_QUEUED,
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'assignment_heartbeat_at' => null,
                ]);

                BillingRequestEvent::create([
                    'billing_request_id' => $request->id,
                    'actor_id' => null,
                    'event_type' => 'ASSIGNMENT_RECOVERED',
                    'from_status' => BillingRequest::STATUS_IN_REVIEW,
                    'to_status' => BillingRequest::STATUS_QUEUED,
                    'notes' => "Assignment recovered due to inactivity (stale > {$staleMinutes}m)",
                    'metadata' => [
                        'previous_teller_id' => $previousTellerId,
                        'original_priority_at' => $request->initial_submitted_at->toISOString(),
                    ],
                    'created_at' => $now,
                ]);

                broadcast(new DataRefreshEvent(
                    $request->organization_id,
                    'queue',
                    'billing_request',
                    $request->id,
                    'recovered'
                ));

                $count++;
            }

            return $count;
        });
    }

    /**
     * Dispatch an authoritative transactional notification event and queue SMS intent if configured.
     * Guaranteed after-commit dispatch (Decision W31).
     */
    protected function dispatchTransactionalNotice(BillingRequest $request, string $eventKey, array $payload): void
    {
        $dispatch = function () use ($request, $eventKey, $payload) {
            try {
                $event = NotificationEvent::create([
                    'organization_id' => $request->organization_id,
                    'event_key' => $eventKey,
                    'event_source_type' => 'billing_request',
                    'event_source_id' => $request->id,
                    'user_id' => $request->created_by_user_id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => Carbon::now(),
                ]);
                if ($this->smsOrchestrator) {
                    $this->smsOrchestrator->queueIntent($event);
                }
            } catch (\Throwable $e) {
                // Transactional SMS or event creation shouldn't block the primary billing flow
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
