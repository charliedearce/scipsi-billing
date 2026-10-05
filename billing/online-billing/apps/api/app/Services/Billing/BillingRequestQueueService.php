<?php

namespace App\Services\Billing;

use App\Events\DataRefreshEvent;
use App\Models\BillingRequest;
use App\Models\BillingRequestDocument;
use App\Models\BillingRequestEvent;
use App\Models\BillingRequestInvoice;
use App\Models\Customer;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\NotificationEvent;
use App\Models\PrivateFile;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Audit\DocumentRevisionService;
use App\Services\Communications\ConversationService;
use App\Services\Notifications\InAppNotificationPublisher;
use App\Services\Sms\SmsDeliveryOrchestrator;
use App\Support\SafeBroadcast;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingRequestQueueService
{
    public function __construct(
        protected SmsDeliveryOrchestrator $smsOrchestrator,
        protected DocumentRevisionService $revisionService,
        protected InAppNotificationPublisher $notifications,
        protected ConversationService $conversations,
    ) {}

    /**
     * Resolve the branch for a customer billing request.
     *
     * Preference order: explicit request (must belong to the org), the user's
     * primary/sole membership, then the organization's sole active location
     * (single-branch deployments). When falling back to the org sole location,
     * attach it as the user's primary membership so later portal calls see it.
     */
    public function resolveBillingLocationId(User $actor, ?int $requestedLocationId = null): int
    {
        $organizationId = (int) $actor->organization_id;

        if ($requestedLocationId) {
            $requested = Location::query()
                ->where('organization_id', $organizationId)
                ->where('id', $requestedLocationId)
                ->where('is_active', true)
                ->first();

            if (! $requested) {
                throw ValidationException::withMessages([
                    'location_id' => 'The selected branch is not available for this organization.',
                ]);
            }

            $membershipIds = $actor->locations()->pluck('locations.id');
            if ($membershipIds->isNotEmpty() && ! $membershipIds->contains($requested->id)) {
                throw ValidationException::withMessages([
                    'location_id' => 'You are not assigned to the selected branch.',
                ]);
            }

            if ($membershipIds->isEmpty()) {
                $actor->locations()->syncWithoutDetaching([
                    $requested->id => ['is_primary' => true],
                ]);
            }

            return (int) $requested->id;
        }

        $memberships = $actor->locations()
            ->where('locations.is_active', true)
            ->get();

        if ($memberships->count() === 1) {
            return (int) $memberships->first()->id;
        }

        if ($memberships->count() > 1) {
            $primary = $memberships->first(fn (Location $location) => (bool) $location->pivot?->is_primary)
                ?? $memberships->first();

            return (int) $primary->id;
        }

        $orgLocations = Location::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($orgLocations->count() === 1) {
            $sole = $orgLocations->first();
            $actor->locations()->syncWithoutDetaching([
                $sole->id => ['is_primary' => true],
            ]);

            return (int) $sole->id;
        }

        throw ValidationException::withMessages([
            'location_id' => $orgLocations->isEmpty()
                ? 'No active branch exists for this organization. Ask Admin to create a location.'
                : 'Multiple branches exist and none is assigned to this portal user. Ask Admin to assign a location.',
        ]);
    }

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

        $documentType = DocumentType::where('organization_id', $request->organization_id)
            ->findOrFail($documentTypeId);
        $maxFiles = max(1, (int) ($documentType->max_files ?: 1));

        return DB::transaction(function () use ($request, $documentTypeId, $privateFileId, $requirementId, $customerNotes, $latestVersion, $maxFiles) {
            $existingSameFile = BillingRequestDocument::where('billing_request_id', $request->id)
                ->where('private_file_id', $privateFileId)
                ->first();
            if ($existingSameFile) {
                return $existingSameFile->load(['documentType', 'privateFile']);
            }

            $currentCount = BillingRequestDocument::where('billing_request_id', $request->id)
                ->where('document_type_id', $documentTypeId)
                ->count();

            if ($currentCount >= $maxFiles) {
                throw ValidationException::withMessages([
                    'private_file_id' => "At most {$maxFiles} file(s) are allowed for this document type.",
                ]);
            }

            $doc = BillingRequestDocument::create([
                'billing_request_id' => $request->id,
                'document_type_id' => $documentTypeId,
                'document_requirement_id' => $requirementId,
                'private_file_id' => $privateFileId,
                'reviewed_version_number' => $latestVersion->version_number,
                'review_status' => BillingRequestDocument::STATUS_PENDING,
                'rejection_reason' => null,
                'customer_notes' => $customerNotes,
            ]);

            return $doc->load(['documentType', 'privateFile']);
        });
    }

    /**
     * Detach a document from a draft or correction request (link only; private file retained).
     */
    public function removeDocument(
        BillingRequest $request,
        User $actor,
        int $documentId
    ): void {
        if (! in_array($request->status, [BillingRequest::STATUS_DRAFT, BillingRequest::STATUS_NEEDS_CORRECTION], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot remove documents when request is in status {$request->status}.",
            ]);
        }

        $doc = BillingRequestDocument::where('billing_request_id', $request->id)
            ->whereKey($documentId)
            ->firstOrFail();

        DB::transaction(function () use ($request, $actor, $doc) {
            $metadata = [
                'billing_request_document_id' => $doc->id,
                'document_type_id' => $doc->document_type_id,
                'private_file_id' => $doc->private_file_id,
            ];
            $doc->delete();

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $actor->id,
                'event_type' => 'DOCUMENT_REMOVED',
                'from_status' => $request->status,
                'to_status' => $request->status,
                'notes' => 'Customer removed an attached document',
                'metadata' => $metadata,
                'created_at' => Carbon::now(),
            ]);
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

        $attachedDocs = $request->documents()->with('privateFile.latestVersion')->get()->groupBy('document_type_id');
        $missingRequirements = [];

        foreach ($requirements as $req) {
            $attachedGroup = $attachedDocs->get($req->document_type_id) ?? collect();
            if ($attachedGroup->isEmpty()) {
                $missingRequirements[] = $req->documentType->name ?? "Doc Type ID {$req->document_type_id}";

                continue;
            }

            $hasClean = $attachedGroup->contains(function ($attached) {
                $version = $attached->privateFile?->latestVersion;

                return $version && $version->scan_status === 'CLEAN';
            });

            if (! $hasClean) {
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

            // 4. In-App Notification to Customer (durable + Reverb wake-up)
            $this->notifications->publish(
                (int) $request->organization_id,
                (int) $request->created_by_user_id,
                'QUEUE',
                'Billing Request Admitted',
                "Your billing request {$request->transaction_no} has been queued with ticket #{$ticketNumber}.",
                [
                    'billing_request_id' => $request->id,
                    'transaction_no' => $request->transaction_no,
                    'ticket_number' => $ticketNumber,
                ],
            );

            $this->notifications->publishToTellers(
                (int) $request->organization_id,
                (int) $request->location_id,
                'TELLER_BILLING',
                'Billing request waiting',
                "Ticket #{$ticketNumber} is ready for teller review.",
                "billing-request:{$request->id}:submitted",
                ['billing_request_id' => $request->id],
            );

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
            $this->broadcastQueueRefresh($request, 'queued');

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

            $this->conversations->findOrCreateForBillingRequest($request, $teller);

            $this->broadcastQueueRefresh($request, 'claimed');

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

            // Notify Customer (durable + Reverb wake-up)
            $this->notifications->publish(
                (int) $request->organization_id,
                (int) $request->created_by_user_id,
                'QUEUE',
                'Document Correction Required',
                "Your request {$request->transaction_no} requires correction: {$correctionNotes}",
                [
                    'billing_request_id' => $request->id,
                    'transaction_no' => $request->transaction_no,
                    'correction_notes' => $correctionNotes,
                ],
            );

            $this->broadcastQueueRefresh($request, 'correction_requested');

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

            $this->notifications->publishToTellers(
                (int) $request->organization_id,
                (int) $request->location_id,
                'TELLER_BILLING',
                'Corrected billing request waiting',
                "Ticket #{$request->ticket_number} is ready for teller review.",
                "billing-request:{$request->id}:resubmitted:{$now->getTimestamp()}",
                ['billing_request_id' => $request->id],
            );

            $this->broadcastQueueRefresh($request, 'resubmitted');

            return $request->fresh(['customer', 'location', 'documents.documentType']);
        });
    }

    /**
     * Teller accepts documents and prepares an invoice draft for the request.
     * After a posted bill with complete=false, the same claim may prepare another draft.
     */
    public function prepareBillingDraft(BillingRequest $request, User $teller): Invoice
    {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only the assigned teller can prepare a draft invoice for this request.',
            ]);
        }

        $allowedStatuses = [
            BillingRequest::STATUS_IN_REVIEW,
            BillingRequest::STATUS_BILLING_IN_PROGRESS,
            BillingRequest::STATUS_BILL_READY,
        ];
        if (! in_array($request->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot prepare a draft while the request is in {$request->status}.",
            ]);
        }

        return DB::transaction(function () use ($request, $teller) {
            $documents = $request->documents()
                ->with('privateFile.latestVersion')
                ->lockForUpdate()
                ->get();

            foreach ($documents as $document) {
                if ($document->review_status !== BillingRequestDocument::STATUS_PENDING) {
                    continue;
                }

                $document->update([
                    'review_status' => BillingRequestDocument::STATUS_ACCEPTED,
                    'reviewed_version_number' => $document->privateFile?->latestVersion?->version_number,
                    'rejection_reason' => null,
                ]);
            }

            if ($request->draft_invoice_id) {
                $existingDraft = Invoice::find($request->draft_invoice_id);
                if ($existingDraft && $existingDraft->status === 'DRAFT') {
                    return $existingDraft;
                }
            }

            $customer = $request->customer;
            $buyerProfileVersion = $customer->buyerProfile?->currentVersion();
            $linkedCount = BillingRequestInvoice::where('billing_request_id', $request->id)->count();
            $draftLabel = $linkedCount > 0
                ? "Additional invoice draft from Billing Request {$request->transaction_no}"
                : "Generated from Billing Request {$request->transaction_no}";

            $invoice = Invoice::create([
                'organization_id' => $request->organization_id,
                'location_id' => $request->location_id,
                'customer_id' => $request->customer_id,
                'buyer_profile_version_id' => $buyerProfileVersion?->id,
                'status' => 'DRAFT',
                'business_date' => Carbon::now('Asia/Manila')->toDateString(),
                'currency' => 'PHP',
                'base_gross_amount' => '0.00',
                'fuel_surcharge_amount' => '0.00',
                'gross_amount' => '0.00',
                'ppa_amount' => '0.00',
                'discount_amount' => '0.00',
                'net_amount' => '0.00',
                'tax_amount' => '0.00',
                'total_charge_amount' => '0.00',
                'notes' => $draftLabel,
                'lock_version' => 1,
                'created_by_user_id' => $teller->id,
            ]);

            $this->revisionService->createRevision(
                organizationId: $invoice->organization_id,
                locationId: $invoice->location_id,
                documentType: 'INVOICE',
                documentId: $invoice->id,
                actor: $teller,
                newSnapshot: $invoice->load(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
                reason: $linkedCount > 0
                    ? "Additional invoice draft from billing request {$request->transaction_no}"
                    : "Initial invoice draft from billing request {$request->transaction_no}",
                expectedVersion: 1
            );

            $fromStatus = $request->status;
            $request->update([
                'draft_invoice_id' => $invoice->id,
                'status' => BillingRequest::STATUS_BILLING_IN_PROGRESS,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'DRAFT_PREPARED',
                'from_status' => $fromStatus,
                'to_status' => BillingRequest::STATUS_BILLING_IN_PROGRESS,
                'notes' => "Invoice draft #{$invoice->id} initialized",
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'sequence' => $linkedCount + 1,
                ],
                'created_at' => Carbon::now(),
            ]);

            $this->broadcastQueueRefresh($request, 'draft_prepared');

            return $invoice;
        });
    }

    /**
     * Link a posted invoice to the billing request and mark bill-ready.
     * First linked invoice sets BILL_READY. When $complete is false the teller keeps the claim
     * so additional drafts/posts can be prepared under the same ticket. When true, assignment is released.
     * Guaranteed idempotent for the same invoice_id: retries never post duplicate notifications.
     */
    public function completeBillReady(
        BillingRequest $request,
        Invoice $invoice,
        User $teller,
        bool $complete = true
    ): BillingRequest {
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

        $alreadyLinked = BillingRequestInvoice::where('billing_request_id', $request->id)
            ->where('invoice_id', $invoice->id)
            ->exists();

        // Idempotency: same invoice already linked and request already BILL_READY
        if ($alreadyLinked && $request->status === BillingRequest::STATUS_BILL_READY) {
            if ($complete && $request->assigned_to_user_id !== null) {
                return DB::transaction(function () use ($request, $invoice, $teller) {
                    return $this->releaseClaimAfterBillReady($request, $invoice, $teller);
                });
            }

            return $request->fresh(['customer', 'location', 'invoice', 'invoices']);
        }

        if ($request->assigned_to_user_id !== null && $request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only the assigned teller can mark this request bill-ready.',
            ]);
        }

        return DB::transaction(function () use ($request, $invoice, $teller, $complete, $alreadyLinked) {
            $now = Carbon::now();
            $fromStatus = $request->status;
            $isFirstInvoice = ! BillingRequestInvoice::where('billing_request_id', $request->id)->exists();

            if (! $alreadyLinked) {
                $otherOwner = BillingRequestInvoice::where('invoice_id', $invoice->id)->first();
                if ($otherOwner && (int) $otherOwner->billing_request_id !== (int) $request->id) {
                    throw ValidationException::withMessages([
                        'invoice' => 'This invoice is already linked to another billing request.',
                    ]);
                }

                BillingRequestInvoice::create([
                    'billing_request_id' => $request->id,
                    'invoice_id' => $invoice->id,
                    'linked_by_user_id' => $teller->id,
                    'linked_at' => $now,
                ]);
            }

            $updates = [
                'status' => BillingRequest::STATUS_BILL_READY,
                // Keep legacy singular FK as primary / first ready bill.
                'invoice_id' => $request->invoice_id ?: $invoice->id,
                'draft_invoice_id' => null,
            ];

            if ($complete) {
                $updates['assigned_to_user_id'] = null;
                $updates['assigned_at'] = null;
                $updates['assignment_heartbeat_at'] = null;
            }

            $request->update($updates);

            $eventType = $isFirstInvoice ? 'BILL_READY' : 'INVOICE_LINKED';
            $notes = $isFirstInvoice
                ? ($complete
                    ? "Official invoice {$invoice->invoice_number} ready for customer; teller assignment released for queue tracking"
                    : "Official invoice {$invoice->invoice_number} ready for customer; teller keeps claim for additional bills")
                : ($complete
                    ? "Additional invoice {$invoice->invoice_number} linked; teller assignment released"
                    : "Additional invoice {$invoice->invoice_number} linked; teller keeps claim for more bills");

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => $eventType,
                'from_status' => $fromStatus,
                'to_status' => BillingRequest::STATUS_BILL_READY,
                'notes' => $notes,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'completed_by_user_id' => $teller->id,
                    'completed_by_name' => $teller->name,
                    'assignment_released' => $complete,
                    'complete' => $complete,
                    'is_first_invoice' => $isFirstInvoice,
                ],
                'created_at' => $now,
            ]);

            if (! $alreadyLinked) {
                $this->notifications->publish(
                    (int) $request->organization_id,
                    (int) $request->created_by_user_id,
                    'INVOICE',
                    'Invoice Ready',
                    "Your official invoice {$invoice->invoice_number} is ready for viewing and payment.",
                    [
                        'billing_request_id' => $request->id,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                    ],
                );

                $this->dispatchTransactionalNotice(
                    $request,
                    'INVOICE_ARTIFACT_READY',
                    [
                        'recipient_name' => $request->customer->name,
                        'reference_no' => $invoice->invoice_number,
                        'org_name' => $request->organization?->name ?? 'SCIPSI',
                    ]
                );
            }

            $this->broadcastQueueRefresh($request, $isFirstInvoice ? 'bill_ready' : 'invoice_linked');

            return $request->fresh(['customer', 'location', 'invoice', 'invoices']);
        });
    }

    /**
     * Finish multi-bill work: release assignment while staying BILL_READY.
     */
    protected function releaseClaimAfterBillReady(
        BillingRequest $request,
        Invoice $invoice,
        User $teller
    ): BillingRequest {
        if ($request->assigned_to_user_id !== null && $request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only the assigned teller can finish this request.',
            ]);
        }

        $now = Carbon::now();
        $fromStatus = $request->status;

        $request->update([
            'assigned_to_user_id' => null,
            'assigned_at' => null,
            'assignment_heartbeat_at' => null,
            'draft_invoice_id' => null,
        ]);

        BillingRequestEvent::create([
            'billing_request_id' => $request->id,
            'actor_id' => $teller->id,
            'event_type' => 'ASSIGNMENT_RELEASED',
            'from_status' => $fromStatus,
            'to_status' => BillingRequest::STATUS_BILL_READY,
            'notes' => 'Teller finished multi-bill work; assignment released for queue tracking',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'completed_by_user_id' => $teller->id,
                'assignment_released' => true,
                'complete' => true,
            ],
            'created_at' => $now,
        ]);

        $this->broadcastQueueRefresh($request, 'bill_ready_finished');

        return $request->fresh(['customer', 'location', 'invoice', 'invoices']);
    }

    /**
     * Release teller assignment back to the queue (e.g. teller stepping away to process others).
     * Original queue priority is completely preserved. This is not a rejection or cancellation.
     */
    public function releaseAssignment(BillingRequest $request, User $teller, ?string $reason = null): BillingRequest
    {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'You are not the assigned teller for this request.',
            ]);
        }

        if (! in_array($request->status, [
            BillingRequest::STATUS_IN_REVIEW,
            BillingRequest::STATUS_BILLING_IN_PROGRESS,
            BillingRequest::STATUS_BILL_READY,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot release a request that is currently in {$request->status}.",
            ]);
        }

        return DB::transaction(function () use ($request, $teller, $reason) {
            $now = Carbon::now();
            $fromStatus = $request->status;
            $hasPostedLinks = BillingRequestInvoice::where('billing_request_id', $request->id)->exists()
                || $request->invoice_id;
            // Multi-bill: if invoices already linked, stay BILL_READY and only drop the claim.
            $toStatus = $hasPostedLinks
                ? BillingRequest::STATUS_BILL_READY
                : BillingRequest::STATUS_QUEUED;

            if ($request->draft_invoice_id && $hasPostedLinks) {
                $draft = Invoice::whereKey($request->draft_invoice_id)->lockForUpdate()->first();
                if ($draft && $draft->status === 'DRAFT') {
                    $draft->update([
                        'status' => 'CANCELLED',
                        'notes' => trim(($draft->notes ? $draft->notes."\n" : '').'Abandoned: teller released multi-bill claim.'),
                    ]);
                }
            }

            $request->update([
                'status' => $toStatus,
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'assignment_heartbeat_at' => null,
                'draft_invoice_id' => $hasPostedLinks ? null : $request->draft_invoice_id,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'RELEASED',
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $reason
                    ? ($hasPostedLinks ? "Finished claim: {$reason}" : "Released: {$reason}")
                    : ($hasPostedLinks
                        ? 'Teller finished multi-bill claim; request stays bill-ready for payment'
                        : 'Released back to queue by teller'),
                'metadata' => [
                    'original_priority_at' => $request->initial_submitted_at?->toISOString(),
                    'kept_bill_ready' => (bool) $hasPostedLinks,
                ],
                'created_at' => $now,
            ]);

            $this->broadcastQueueRefresh($request, $hasPostedLinks ? 'bill_ready_finished' : 'released');

            return $request->fresh(['customer', 'location', 'documents.documentType', 'invoice', 'invoices']);
        });
    }

    /**
     * Terminal teller cancellation (Decision W22).
     * Distinct from request-correction: the request leaves the queue and cannot be resubmitted as the same ticket.
     * Linked unposted draft invoices are abandoned; posted fiscal documents are never rewritten.
     */
    public function cancelByTeller(BillingRequest $request, User $teller, string $reason): BillingRequest
    {
        if ($request->assigned_to_user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only the assigned teller can cancel this request.',
            ]);
        }

        if (! in_array($request->status, [
            BillingRequest::STATUS_IN_REVIEW,
            BillingRequest::STATUS_BILLING_IN_PROGRESS,
            BillingRequest::STATUS_BILL_READY,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot cancel a request that is currently in {$request->status}. Use release or request correction instead.",
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A customer-visible cancellation reason is required.',
            ]);
        }

        return DB::transaction(function () use ($request, $teller, $reason) {
            $now = Carbon::now();
            $fromStatus = $request->status;

            if ($request->draft_invoice_id) {
                $draft = Invoice::whereKey($request->draft_invoice_id)->lockForUpdate()->first();
                if ($draft && $draft->status === 'DRAFT') {
                    $draft->update([
                        'status' => 'CANCELLED',
                        'notes' => trim(($draft->notes ? $draft->notes."\n" : '').'Abandoned: billing request cancelled by teller.'),
                    ]);
                }
            }

            $hasPostedLinks = BillingRequestInvoice::where('billing_request_id', $request->id)->exists()
                || $request->invoice_id;

            if ($hasPostedLinks) {
                throw ValidationException::withMessages([
                    'status' => 'This request already has a linked posted invoice and cannot be cancelled here.',
                ]);
            }

            $request->update([
                'status' => BillingRequest::STATUS_CANCELLED,
                'correction_notes' => $reason,
                'internal_notes' => trim(($request->internal_notes ? $request->internal_notes."\n" : '')."Cancelled by teller {$teller->id}: {$reason}"),
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'assignment_heartbeat_at' => null,
            ]);

            BillingRequestEvent::create([
                'billing_request_id' => $request->id,
                'actor_id' => $teller->id,
                'event_type' => 'CANCELLED',
                'from_status' => $fromStatus,
                'to_status' => BillingRequest::STATUS_CANCELLED,
                'notes' => $reason,
                'metadata' => [
                    'cancelled_by_user_id' => $teller->id,
                    'draft_invoice_id' => $request->draft_invoice_id,
                ],
                'created_at' => $now,
            ]);

            $this->notifications->publish(
                (int) $request->organization_id,
                (int) $request->created_by_user_id,
                'QUEUE',
                'Billing Request Cancelled',
                "Your request {$request->transaction_no} was cancelled: {$reason}",
                [
                    'billing_request_id' => $request->id,
                    'transaction_no' => $request->transaction_no,
                    'status' => BillingRequest::STATUS_CANCELLED,
                    'reason' => $reason,
                ],
            );

            $this->broadcastQueueRefresh($request, 'cancelled');

            $this->dispatchTransactionalNotice(
                $request,
                'BILLING_REQUEST_CANCELLED',
                [
                    'recipient_name' => $request->customer?->name ?? 'Valued Customer',
                    'reference_no' => $request->transaction_no,
                    'action_label' => 'Request Cancelled',
                    'org_name' => $request->organization?->name ?? 'SCIPSI',
                ]
            );

            return $request->fresh(['customer', 'location', 'documents.documentType']);
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

                $this->broadcastQueueRefresh($request, 'recovered');

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

    /**
     * Wake authorized tellers and only the customer who owns the request.
     */
    protected function broadcastQueueRefresh(BillingRequest $request, string $action): void
    {
        SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
            organizationId: $request->organization_id,
            scope: 'queue',
            entity: 'billing_request',
            entityId: $request->id,
            action: $action,
            userId: $request->created_by_user_id,
            broadcastToOrganization: false,
        ));
    }
}
