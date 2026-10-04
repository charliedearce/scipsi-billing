<?php

namespace App\Services\Billing;

use App\Models\AuditEvent;
use App\Models\BillingRequest;
use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\ManualPaymentSubmission;
use App\Models\ManualPaymentSubmissionEvent;
use App\Models\ManualPaymentSubmissionItem;
use App\Models\ManualPaymentSubmissionProof;
use App\Models\PaymentGroup;
use App\Models\PaymentGroupItem;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\WalkInCustomer;
use App\Services\Notifications\InAppNotificationPublisher;
use App\Services\Sms\NotificationEventRecorder;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps payment-proof workflow separate from money recognition.
 * A submitted or rejected proof never changes an invoice balance. Only approve()
 * delegates to ReceiptPostingService and receives its source-level idempotency.
 */
class ManualPaymentProofService
{
    public function __construct(
        protected ReceiptPostingService $receiptPostingService,
        protected PaymentPolicyService $paymentPolicyService,
        protected BankTransferSettlementReferenceService $bankTransferReferences,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
        protected NotificationEventRecorder $notificationEvents,
        protected InAppNotificationPublisher $notifications,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function portalBills(User $actor, int $customerId): array
    {
        $customer = $this->assertActiveCustomerAccess($actor, $customerId);
        $invoices = $this->portalVisiblePostedInvoicesQuery($actor, $customer)
            ->with([
                'billingRequest',
                'receiptAllocations' => fn ($query) => $query
                    ->whereHas('receipt', fn ($receipt) => $receipt->where('status', 'POSTED'))
                    ->with(['receipt.canonicalArtifact']),
            ])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        return $invoices->map(function (Invoice $invoice): array {
            $applied = '0.00';
            $paidAt = null;
            foreach ($invoice->receiptAllocations as $allocation) {
                $applied = bcadd($applied, (string) $allocation->applied_amount, 2);
                $receiptPosted = $allocation->receipt?->posted_at;
                if ($receiptPosted && ($paidAt === null || $receiptPosted->lt($paidAt))) {
                    $paidAt = $receiptPosted;
                }
            }
            $outstanding = $this->positiveDifference((string) $invoice->total_charge_amount, $applied);
            $request = $invoice->billingRequest;
            if ($request && ! $request->relationLoaded('events')) {
                $request->load(['events', 'assignedTeller:id,name']);
            }

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'business_date' => $invoice->business_date?->toDateString(),
                'currency' => $invoice->currency,
                'total_charge_amount' => (string) $invoice->total_charge_amount,
                'applied_amount' => $applied,
                'outstanding_amount' => $outstanding,
                'lock_version' => $invoice->lock_version,
                'status' => $invoice->status,
                'billing_request_id' => $request?->id,
                'catering_teller' => $request?->cateringTeller(),
                'timeline' => [
                    'requested_at' => ($request?->initial_submitted_at ?? $request?->submitted_at)?->toIso8601String(),
                    'bill_approved_at' => $invoice->posted_at?->toIso8601String(),
                    'paid_at' => $paidAt?->toIso8601String(),
                ],
                'receipt_history' => $this->mapReceiptHistory($invoice->receiptAllocations),
            ];
        })->all();
    }

    /**
     * Full posted-bill detail for an authorized portal customer (line items, snapshots, PDF flag).
     */
    public function portalBillDetail(User $actor, int $customerId, int $invoiceId): array
    {
        $customer = $this->assertActiveCustomerAccess($actor, $customerId);
        $invoice = $this->portalVisiblePostedInvoicesQuery($actor, $customer)
            ->with([
                'items.pricingSnapshot',
                'canonicalArtifact',
                'billingRequest',
                'receiptAllocations' => fn ($query) => $query
                    ->whereHas('receipt', fn ($receipt) => $receipt->where('status', 'POSTED'))
                    ->with(['receipt.canonicalArtifact']),
            ])
            ->findOrFail($invoiceId);

        $applied = '0.00';
        $paidAt = null;
        foreach ($invoice->receiptAllocations as $allocation) {
            $applied = bcadd($applied, (string) $allocation->applied_amount, 2);
            $receiptPosted = $allocation->receipt?->posted_at;
            if ($receiptPosted && ($paidAt === null || $receiptPosted->lt($paidAt))) {
                $paidAt = $receiptPosted;
            }
        }
        $outstanding = $this->positiveDifference((string) $invoice->total_charge_amount, $applied);
        $artifact = $invoice->canonicalArtifact;
        $pdfReady = $artifact
            && in_array($artifact->status, ['RENDERED', 'FAILED'], true)
            && ($artifact->status === 'FAILED' || $artifact->existsOnDisk());
        $request = $invoice->billingRequest ?? $invoice->billingRequests()->first();
        if ($request) {
            $request->load([
                'events',
                'assignedTeller:id,name',
                'invoices.receiptAllocations' => fn ($query) => $query
                    ->whereHas('receipt', fn ($receipt) => $receipt->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,status,posted_at',
            ]);
        }
        $cateringTeller = $request?->cateringTeller();

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'business_date' => $invoice->business_date?->toDateString(),
            'posted_at' => $invoice->posted_at?->toIso8601String(),
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'lock_version' => $invoice->lock_version,
            'notes' => $invoice->notes,
            'shipment' => [
                'vessel_name' => (string) ($invoice->vessel_name ?? ''),
                'voyage' => (string) ($invoice->voyage ?? ''),
                'movement_type' => in_array($invoice->movement_type, ['IN', 'OUT'], true) ? $invoice->movement_type : '',
                'route_type' => in_array($invoice->route_type, ['DOMESTIC', 'FOREIGN'], true) ? $invoice->route_type : '',
            ],
            'billing_request_id' => $request?->id,
            'transaction_no' => $request?->transaction_no,
            'catering_teller' => $cateringTeller,
            'request_progress' => $request?->progress(null, $cateringTeller),
            'timeline' => [
                'requested_at' => ($request?->initial_submitted_at ?? $request?->submitted_at)?->toIso8601String(),
                'bill_approved_at' => $invoice->posted_at?->toIso8601String(),
                'paid_at' => $paidAt?->toIso8601String(),
            ],
            'buyer' => [
                'name' => $invoice->buyer_snapshot_name,
                'trade_name' => $invoice->buyer_snapshot_trade_name,
                'tin' => $invoice->buyer_snapshot_tin,
                'branch_code' => $invoice->buyer_snapshot_branch_code,
                'tax_classification' => $invoice->buyer_snapshot_tax_classification,
                'address' => $invoice->buyer_snapshot_address,
                'email' => $invoice->buyer_snapshot_email,
                'phone' => $invoice->buyer_snapshot_phone,
            ],
            'amounts' => [
                'base_gross_amount' => (string) $invoice->base_gross_amount,
                'fuel_surcharge_amount' => (string) $invoice->fuel_surcharge_amount,
                'gross_amount' => (string) $invoice->gross_amount,
                'ppa_amount' => (string) $invoice->ppa_amount,
                'discount_amount' => (string) $invoice->discount_amount,
                'net_amount' => (string) $invoice->net_amount,
                'tax_amount' => (string) $invoice->tax_amount,
                'total_charge_amount' => (string) $invoice->total_charge_amount,
                'applied_amount' => $applied,
                'outstanding_amount' => $outstanding,
            ],
            'items' => $invoice->items->sortBy('line_number')->values()->map(fn ($item) => [
                'line_number' => $item->line_number,
                'description' => $item->description,
                'tariff_code' => $item->pricingSnapshot?->tariff_code,
                'quantity' => (string) $item->quantity,
                'unit_rate' => (string) $item->unit_rate,
                'base_gross_amount' => (string) $item->base_gross_amount,
                'fuel_surcharge_amount' => (string) $item->fuel_surcharge_amount,
                'gross_amount' => (string) $item->gross_amount,
                'ppa_amount' => (string) $item->ppa_amount,
                'discount_amount' => (string) $item->discount_amount,
                'net_amount' => (string) $item->net_amount,
                'tax_amount' => (string) $item->tax_amount,
                'total_charge_amount' => (string) $item->total_charge_amount,
                'tax_treatment' => $item->pricingSnapshot?->tax_treatment_key,
            ])->all(),
            'receipt_history' => $this->mapReceiptHistory($invoice->receiptAllocations),
            'pdf_available' => $pdfReady,
            'source_attachments' => $this->portalSourceAttachments($actor->organization_id, $customer->id, $invoice->id),
        ];
    }

    /**
     * Customer-owned billing-request files linked to this posted invoice (for portal tracking).
     *
     * @return list<array<string, mixed>>
     */
    protected function portalSourceAttachments(int $organizationId, int $customerId, int $invoiceId): array
    {
        $request = BillingRequest::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->where(function ($query) use ($invoiceId) {
                $query->where('invoice_id', $invoiceId)
                    ->orWhereHas('invoiceLinks', fn ($q) => $q->where('invoice_id', $invoiceId));
            })
            ->with(['documents.documentType', 'documents.privateFile.latestVersion'])
            ->orderByDesc('id')
            ->first();

        if (! $request) {
            return [];
        }

        return $request->documents->map(function ($doc) use ($request) {
            $version = $doc->privateFile?->latestVersion;

            return [
                'billing_request_id' => $request->id,
                'transaction_no' => $request->transaction_no,
                'document_id' => $doc->id,
                'document_type_name' => $doc->documentType?->name,
                'private_file_id' => $doc->private_file_id,
                'original_name' => $version?->original_name,
                'mime_type' => $version?->mime_type,
                'scan_status' => $version?->scan_status,
                'review_status' => $doc->review_status,
                'version_number' => $version?->version_number,
            ];
        })->values()->all();
    }

    /** @param array{payment_group_id:int,proof_file_id:int,declared_reference?:?string} $data */
    public function submit(User $actor, array $data): ManualPaymentSubmission
    {
        return DB::transaction(function () use ($actor, $data): ManualPaymentSubmission {
            $group = $this->paymentPolicyService->findAuthorizedGroup($actor, (int) $data['payment_group_id'], true);
            $customer = Customer::where('organization_id', $actor->organization_id)
                ->whereKey($group->customer_id)->lockForUpdate()->firstOrFail();
            $this->assertActiveCustomerAccess($actor, $customer->id, true);
            [$proofFile, $proofVersion] = $this->lockUsableOwnedProof($actor, (int) $data['proof_file_id']);
            if (ManualPaymentSubmission::where('payment_group_id', $group->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['payment_group_id' => ['This payment instruction already has a proof workflow. Use its correction/resubmission action instead.']]);
            }

            $groupItems = PaymentGroupItem::where('payment_group_id', $group->id)
                ->orderBy('invoice_id')->lockForUpdate()->get();
            if ($groupItems->isEmpty()) {
                throw ValidationException::withMessages(['payment_group_id' => ['This payment instruction has no selected bills.']]);
            }
            $invoiceIds = $groupItems->pluck('invoice_id')->all();
            $invoices = Invoice::where('organization_id', $actor->organization_id)
                ->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['payment_group_id' => ['A selected bill is no longer available.']]);
            }
            $requestedTotal = '0.00';
            foreach ($groupItems as $item) {
                $invoice = $invoices->get($item->invoice_id);
                $this->assertInvoiceSelectable($invoice, $customer, [
                    'expected_invoice_lock_version' => $item->expected_invoice_lock_version,
                    'requested_amount' => (string) $item->requested_amount,
                ], $group->currency);
                $requestedTotal = bcadd($requestedTotal, (string) $item->requested_amount, 2);
            }

            if (ManualPaymentSubmissionProof::where('private_file_id', $proofFile->id)
                ->where('private_file_version_number', $proofVersion->version_number)->exists()) {
                throw ValidationException::withMessages(['proof_file_id' => ['This exact proof-file version is already linked to a payment submission.']]);
            }

            $now = Carbon::now();
            $submission = ManualPaymentSubmission::create([
                'organization_id' => $actor->organization_id,
                'customer_id' => $customer->id,
                'payment_group_id' => $group->id,
                'proof_file_id' => $proofFile->id,
                'submitted_by_user_id' => $actor->id,
                'source_key' => (string) Str::uuid(),
                'status' => ManualPaymentSubmission::STATUS_SUBMITTED,
                'currency' => $group->currency,
                'requested_amount' => $requestedTotal,
                'declared_reference' => $data['declared_reference'] ?? null,
                'initial_submitted_at' => $now,
                'submitted_at' => $now,
                'lock_version' => 1,
            ]);

            foreach ($groupItems as $item) {
                ManualPaymentSubmissionItem::create([
                    'manual_payment_submission_id' => $submission->id,
                    'invoice_id' => $item->invoice_id,
                    'expected_invoice_lock_version' => $item->expected_invoice_lock_version,
                    'requested_amount' => $item->requested_amount,
                ]);
            }
            ManualPaymentSubmissionProof::create([
                'manual_payment_submission_id' => $submission->id,
                'private_file_id' => $proofFile->id,
                'private_file_version_number' => $proofVersion->version_number,
                'attempt_number' => 1,
                'submitted_by_user_id' => $actor->id,
                'created_at' => $now,
            ]);
            $this->event($submission, $actor, 'SUBMITTED', null, ManualPaymentSubmission::STATUS_SUBMITTED, null, [
                'proof_file_id' => $proofFile->id,
                'proof_file_version_number' => $proofVersion->version_number,
                'invoice_ids' => $invoiceIds,
                'requested_amount' => $requestedTotal,
                'payment_group_id' => $group->id,
                'payment_deadline_at' => $group->payment_deadline_at->toIso8601String(),
            ]);
            $this->audit($submission, $actor, 'PAYMENT_PROOF_SUBMITTED', 'proofs:upload', 'Customer submitted a bank-payment proof.');
            $this->paymentPolicyService->markProofSubmitted($group, $actor);

            return $this->loadSubmission($submission);
        });
    }

    /** @param array{proof_file_id?:int} $data */
    public function resubmit(ManualPaymentSubmission $submission, User $actor, array $data): ManualPaymentSubmission
    {
        $correctionWindowExpired = false;
        $result = DB::transaction(function () use ($submission, $actor, $data, &$correctionWindowExpired): ManualPaymentSubmission {
            $locked = ManualPaymentSubmission::where('organization_id', $actor->organization_id)
                ->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->assertActiveCustomerAccess($actor, $locked->customer_id, true);
            if ($locked->status !== ManualPaymentSubmission::STATUS_REJECTED) {
                throw ValidationException::withMessages(['status' => ['Only a rejected payment proof can be resubmitted.']]);
            }
            $group = PaymentGroup::where('organization_id', $actor->organization_id)
                ->whereKey($locked->payment_group_id)->lockForUpdate()->first();
            if (! $group) {
                throw ValidationException::withMessages(['payment_group_id' => ['This legacy proof has no payment instruction snapshot and cannot be resubmitted through the current flow.']]);
            }
            if ($group->correction_due_at && Carbon::now('Asia/Manila')->greaterThan($group->correction_due_at)) {
                $this->paymentPolicyService->expireCorrectionWindow($group, $actor);
                $correctionWindowExpired = true;

                return $this->loadSubmission($locked);
            }

            $items = ManualPaymentSubmissionItem::where('manual_payment_submission_id', $locked->id)
                ->orderBy('invoice_id')->lockForUpdate()->get();
            $invoiceIds = $items->pluck('invoice_id')->all();
            $invoices = Invoice::where('organization_id', $actor->organization_id)
                ->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $invoice = $invoices->get($item->invoice_id);
                if (! $invoice) {
                    throw ValidationException::withMessages(['allocations' => ['A selected invoice is no longer available.']]);
                }
                $this->assertInvoiceSelectable($invoice, $locked->customer, [
                    'expected_invoice_lock_version' => $item->expected_invoice_lock_version,
                    'requested_amount' => (string) $item->requested_amount,
                ], $locked->currency);
            }

            $activeSelection = ManualPaymentSubmissionItem::whereIn('invoice_id', $invoiceIds)
                ->where('manual_payment_submission_id', '<>', $locked->id)
                ->whereHas('submission', fn ($query) => $query
                    ->where('organization_id', $actor->organization_id)
                    ->whereIn('status', [ManualPaymentSubmission::STATUS_SUBMITTED, ManualPaymentSubmission::STATUS_IN_REVIEW]))
                ->lockForUpdate()->exists();
            if ($activeSelection) {
                throw ValidationException::withMessages(['allocations' => ['A selected invoice already has another proof awaiting review.']]);
            }

            [$proofFile, $proofVersion] = $this->lockUsableOwnedProof($actor, (int) ($data['proof_file_id'] ?? $locked->proof_file_id));
            if (ManualPaymentSubmissionProof::where('private_file_id', $proofFile->id)
                ->where('private_file_version_number', $proofVersion->version_number)->exists()) {
                throw ValidationException::withMessages(['proof_file_id' => ['Upload a new clean proof file or a newer version before resubmitting.']]);
            }

            $now = Carbon::now();
            $nextRound = $locked->resubmission_rounds + 1;
            $locked->update([
                'proof_file_id' => $proofFile->id,
                'status' => ManualPaymentSubmission::STATUS_SUBMITTED,
                'submitted_at' => $now,
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'assignment_heartbeat_at' => null,
                'rejection_reason' => null,
                'resubmission_rounds' => $nextRound,
                'lock_version' => $locked->lock_version + 1,
            ]);
            ManualPaymentSubmissionProof::create([
                'manual_payment_submission_id' => $locked->id,
                'private_file_id' => $proofFile->id,
                'private_file_version_number' => $proofVersion->version_number,
                'attempt_number' => $nextRound + 1,
                'submitted_by_user_id' => $actor->id,
                'created_at' => $now,
            ]);
            $this->event($locked, $actor, 'RESUBMITTED', ManualPaymentSubmission::STATUS_REJECTED, ManualPaymentSubmission::STATUS_SUBMITTED, null, [
                'proof_file_id' => $proofFile->id,
                'proof_file_version_number' => $proofVersion->version_number,
                'original_submitted_at' => $locked->initial_submitted_at->toIso8601String(),
                'resubmission_round' => $nextRound,
            ]);
            $this->audit($locked, $actor, 'PAYMENT_PROOF_RESUBMITTED', 'proofs:upload', 'Customer resubmitted a corrected bank-payment proof.');
            $this->paymentPolicyService->markProofSubmitted($group, $actor, true);

            return $this->loadSubmission($locked);
        });
        if ($correctionWindowExpired) {
            throw ValidationException::withMessages([
                'payment_group_id' => ['The correction window has elapsed. This unpaid instruction was closed; request new payment instructions for the current bill balance.'],
            ]);
        }

        return $result;
    }

    public function claimNext(User $teller): ?ManualPaymentSubmission
    {
        return DB::transaction(function () use ($teller): ?ManualPaymentSubmission {
            $submission = ManualPaymentSubmission::where('organization_id', $teller->organization_id)
                ->where('status', ManualPaymentSubmission::STATUS_SUBMITTED)
                ->orderBy('initial_submitted_at')
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();
            if (! $submission) {
                return null;
            }
            $group = PaymentGroup::where('organization_id', $teller->organization_id)
                ->whereKey($submission->payment_group_id)->lockForUpdate()->first();
            if (! $group) {
                throw ValidationException::withMessages(['payment_group_id' => ['This proof has no payment instruction snapshot and cannot enter the review queue.']]);
            }

            $now = Carbon::now();
            $submission->update([
                'status' => ManualPaymentSubmission::STATUS_IN_REVIEW,
                'assigned_to_user_id' => $teller->id,
                'assigned_at' => $now,
                'assignment_heartbeat_at' => $now,
                'lock_version' => $submission->lock_version + 1,
            ]);
            $this->event($submission, $teller, 'CLAIMED', ManualPaymentSubmission::STATUS_SUBMITTED, ManualPaymentSubmission::STATUS_IN_REVIEW, null, [
                'original_submitted_at' => $submission->initial_submitted_at->toIso8601String(),
            ]);
            $this->audit($submission, $teller, 'PAYMENT_PROOF_CLAIMED', 'proofs:review', 'Teller claimed the oldest payment proof in the separate review queue.');
            $this->paymentPolicyService->transitionReview($group, $teller, PaymentGroup::STATUS_IN_REVIEW, 'TELLER_CLAIMED', null, [
                'manual_payment_submission_id' => $submission->id,
            ]);

            return $this->loadSubmission($submission);
        });
    }

    /** @param array{expected_version:int,confirmed_reference?:?string,allocations:array<int,array{invoice_id:int,cash_amount:string,withholding_applications?:array<int,array{certificate_id:int,amount:string}>}>} $data */
    public function approve(ManualPaymentSubmission $submission, User $teller, array $data): ManualPaymentSubmission
    {
        return DB::transaction(function () use ($submission, $teller, $data): ManualPaymentSubmission {
            $locked = ManualPaymentSubmission::where('organization_id', $teller->organization_id)
                ->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $items = ManualPaymentSubmissionItem::where('manual_payment_submission_id', $locked->id)
                ->orderBy('invoice_id')->lockForUpdate()->get();
            $group = PaymentGroup::where('organization_id', $teller->organization_id)
                ->whereKey($locked->payment_group_id)->lockForUpdate()->first();
            if (! $group) {
                throw ValidationException::withMessages(['payment_group_id' => ['This proof has no payment instruction snapshot and cannot be approved through the current flow.']]);
            }
            $paymentTenderType = $group->payment_method === PaymentGroup::METHOD_CHECK_DEPOSIT ? 'CHECK' : 'BANK_TRANSFER';
            $paymentTenderStatus = $group->payment_method === PaymentGroup::METHOD_CHECK_DEPOSIT ? 'CLEARED' : 'CONFIRMED';
            $postingAllocations = $this->postingAllocations(
                $items,
                $data['allocations'],
                $locked->declared_reference,
                $data['confirmed_reference'] ?? null,
                $paymentTenderType,
                $paymentTenderStatus,
            );
            $approvalFingerprint = $this->approvalFingerprint(
                $data['confirmed_reference'] ?? null,
                $postingAllocations,
                $data['receipt_kind'] ?? Receipt::KIND_OFFICIAL,
            );

            if ($locked->status === ManualPaymentSubmission::STATUS_APPROVED && $locked->receipt_id) {
                if (! hash_equals((string) $locked->approval_payload_fingerprint, $approvalFingerprint)) {
                    throw ValidationException::withMessages(['submission' => ['This approved proof cannot be retried with a different receipt allocation.']]);
                }

                return $this->loadSubmission($locked);
            }
            $this->assertAssignedReview($locked, $teller, (int) $data['expected_version']);
            if (! $teller->hasPermission('receipts:post')) {
                throw new AuthorizationException('Receipt posting permission is required to approve a payment proof.');
            }
            if ($group->status !== PaymentGroup::STATUS_IN_REVIEW) {
                throw ValidationException::withMessages(['payment_group_id' => ['This payment instruction is not in an approvable review state.']]);
            }
            if ($group->payment_method === PaymentGroup::METHOD_CHECK_DEPOSIT
                && $group->check_clearance_status !== PaymentGroup::CHECK_CLEARED) {
                throw ValidationException::withMessages([
                    'payment_group_id' => ['A deposited check must be cleared before a collection receipt can be posted.'],
                ]);
            }
            $confirmedReference = $data['confirmed_reference'] ?? $locked->declared_reference;
            $normalizedBankReference = null;
            if ($paymentTenderType === 'BANK_TRANSFER' && $confirmedReference) {
                $normalizedBankReference = $this->bankTransferReferences->claim(
                    $teller->organization_id,
                    $locked->currency,
                    $confirmedReference,
                    'MANUAL_PAYMENT_PROOF',
                    $locked->source_key,
                );
            }

            $receipt = $this->receiptPostingService->post($teller, [
                'source_type' => 'MANUAL_PAYMENT_PROOF',
                'source_key' => $locked->source_key,
                'customer_id' => $locked->customer_id,
                'currency' => $locked->currency,
                'payer_name' => $locked->customer->name,
                'receipt_kind' => $data['receipt_kind'] ?? Receipt::KIND_OFFICIAL,
                'allocations' => $postingAllocations,
            ]);
            if ($normalizedBankReference) {
                $this->bankTransferReferences->linkReceipt($teller->organization_id, $locked->currency, $normalizedBankReference, $receipt);
            }

            $now = Carbon::now();
            $locked->update([
                'status' => ManualPaymentSubmission::STATUS_APPROVED,
                'receipt_id' => $receipt->id,
                'confirmed_reference' => $data['confirmed_reference'] ?? $locked->declared_reference,
                'reviewed_by_user_id' => $teller->id,
                'reviewed_at' => $now,
                'approval_payload_fingerprint' => $approvalFingerprint,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, $teller, 'APPROVED', ManualPaymentSubmission::STATUS_IN_REVIEW, ManualPaymentSubmission::STATUS_APPROVED, null, [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'applied_amount' => $receipt->applied_amount,
                'unapplied_amount' => $receipt->unapplied_amount,
            ]);
            $this->audit($locked, $teller, 'PAYMENT_PROOF_APPROVED', 'proofs:review', 'Teller approved the proof and posted the linked collection receipt.');
            $this->notifyCustomer($locked, 'Payment Confirmed', "Your payment proof was verified. Collection receipt {$receipt->receipt_number} is now available.", [
                'manual_payment_submission_id' => $locked->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
            ]);
            $this->paymentPolicyService->transitionReview($group, $teller, PaymentGroup::STATUS_SETTLED, 'SETTLED', null, [
                'manual_payment_submission_id' => $locked->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
            ]);
            $this->dispatchPaymentTransactionalNotice(
                $locked,
                'SETTLEMENT_POSTED',
                [
                    'recipient_name' => $locked->submittedBy->name,
                    'reference_no' => $receipt->receipt_number,
                    'action_label' => 'payment settlement posted',
                    'date_formatted' => $now->timezone('Asia/Manila')->format('M d, Y'),
                    'org_name' => 'SCIPSI',
                ],
            );

            return $this->loadSubmission($locked);
        });
    }

    public function recordCheckClearance(
        ManualPaymentSubmission $submission,
        User $teller,
        int $expectedSubmissionVersion,
        int $expectedGroupVersion,
        string $clearanceStatus,
        string $notes,
    ): ManualPaymentSubmission {
        return DB::transaction(function () use ($submission, $teller, $expectedSubmissionVersion, $expectedGroupVersion, $clearanceStatus, $notes): ManualPaymentSubmission {
            $locked = ManualPaymentSubmission::where('organization_id', $teller->organization_id)
                ->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignedReview($locked, $teller, $expectedSubmissionVersion);
            $group = PaymentGroup::where('organization_id', $teller->organization_id)
                ->whereKey($locked->payment_group_id)->lockForUpdate()->first();
            if (! $group) {
                throw ValidationException::withMessages(['payment_group_id' => ['This proof has no payment instruction snapshot and cannot record a check-clearance decision.']]);
            }

            $this->paymentPolicyService->recordCheckClearance(
                $group,
                $teller,
                $expectedGroupVersion,
                $clearanceStatus,
                $notes,
            );
            $this->event(
                $locked,
                $teller,
                'CHECK_'.$clearanceStatus,
                ManualPaymentSubmission::STATUS_IN_REVIEW,
                ManualPaymentSubmission::STATUS_IN_REVIEW,
                $notes,
                [
                    'payment_group_id' => $group->id,
                    'payment_group_lock_version' => $group->lock_version,
                    'check_clearance_status' => $clearanceStatus,
                ],
            );
            $this->audit(
                $locked,
                $teller,
                'PAYMENT_PROOF_CHECK_'.$clearanceStatus,
                'checks:confirm_clearance',
                $notes,
            );
            if ($clearanceStatus === PaymentGroup::CHECK_DISHONORED) {
                $this->dispatchPaymentTransactionalNotice(
                    $locked,
                    'CHECK_DISHONORED',
                    [
                        'recipient_name' => $locked->submittedBy->name,
                        'reference_no' => 'your deposited check',
                        'action_label' => 'payment follow-up required',
                        'date_formatted' => Carbon::now('Asia/Manila')->format('M d, Y'),
                        'org_name' => 'SCIPSI',
                    ],
                );
            }

            return $this->loadSubmission($locked);
        });
    }

    public function reject(ManualPaymentSubmission $submission, User $teller, int $expectedVersion, string $reason): ManualPaymentSubmission
    {
        return DB::transaction(function () use ($submission, $teller, $expectedVersion, $reason): ManualPaymentSubmission {
            $locked = ManualPaymentSubmission::where('organization_id', $teller->organization_id)
                ->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignedReview($locked, $teller, $expectedVersion);
            $group = PaymentGroup::where('organization_id', $teller->organization_id)
                ->whereKey($locked->payment_group_id)->lockForUpdate()->first();
            if (! $group) {
                throw ValidationException::withMessages(['payment_group_id' => ['This proof has no payment instruction snapshot and cannot be rejected through the current flow.']]);
            }

            $now = Carbon::now();
            $locked->update([
                'status' => ManualPaymentSubmission::STATUS_REJECTED,
                'reviewed_by_user_id' => $teller->id,
                'reviewed_at' => $now,
                'rejection_reason' => $reason,
                'assignment_heartbeat_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, $teller, 'REJECTED', ManualPaymentSubmission::STATUS_IN_REVIEW, ManualPaymentSubmission::STATUS_REJECTED, $reason, []);
            $this->audit($locked, $teller, 'PAYMENT_PROOF_REJECTED', 'proofs:review', 'Teller rejected a payment proof; no receipt was posted.');
            $this->notifyCustomer($locked, 'Payment Proof Needs Correction', "Your payment proof needs correction: {$reason}", [
                'manual_payment_submission_id' => $locked->id,
                'reason' => $reason,
            ]);
            $this->paymentPolicyService->transitionReview($group, $teller, PaymentGroup::STATUS_PROOF_REJECTED, 'PROOF_REJECTED', $reason, [
                'manual_payment_submission_id' => $locked->id,
            ]);
            $this->dispatchPaymentTransactionalNotice(
                $locked,
                'PAYMENT_PROOF_REJECTED',
                [
                    'recipient_name' => $locked->submittedBy->name,
                    'reference_no' => 'your payment proof',
                    'action_label' => 'payment proof needs correction',
                    'date_formatted' => $now->timezone('Asia/Manila')->format('M d, Y'),
                    'org_name' => 'SCIPSI',
                ],
            );

            return $this->loadSubmission($locked);
        });
    }

    public function assertActiveCustomerAccess(User $actor, int $customerId, bool $lock = false): Customer
    {
        $customer = Customer::where('organization_id', $actor->organization_id)->whereKey($customerId)->firstOrFail();
        if (! $customer->isActive()) {
            throw new AuthorizationException('This customer account is not active for payment processing.');
        }
        $link = CustomerUserLink::where('customer_id', $customer->id)->where('user_id', $actor->id)->where('is_active', true);
        $activeLink = $lock ? $link->lockForUpdate()->first() : $link->first();
        if (! $activeLink) {
            throw new AuthorizationException('You do not have an active link to this customer account.');
        }

        return $customer;
    }

    /** @return array{0: PrivateFile, 1: PrivateFileVersion} */
    protected function lockUsableOwnedProof(User $actor, int $proofFileId): array
    {
        $file = PrivateFile::where('organization_id', $actor->organization_id)->whereKey($proofFileId)->lockForUpdate()->firstOrFail();
        if ($file->purpose !== 'PAYMENT_PROOF' || $file->status !== 'CLEAN') {
            throw ValidationException::withMessages(['proof_file_id' => ['The selected payment proof is not clean and available for review.']]);
        }
        if ($file->owner_id !== $actor->id && $file->uploaded_by !== $actor->id) {
            throw new AuthorizationException('You may submit only a payment proof that you uploaded or own.');
        }

        $version = PrivateFileVersion::where('private_file_id', $file->id)
            ->where('version_number', $file->current_version)->lockForUpdate()->first();
        if (! $version || $version->scan_status !== 'CLEAN') {
            throw ValidationException::withMessages(['proof_file_id' => ['The selected payment proof version is not clean and available for review.']]);
        }

        return [$file, $version];
    }

    /** @param array{expected_invoice_lock_version:int,requested_amount:string} $input */
    protected function assertInvoiceSelectable(Invoice $invoice, Customer $customer, array $input, string $currency): void
    {
        if ($invoice->status !== 'POSTED' || $invoice->currency !== strtoupper($currency)) {
            throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} is not an eligible posted invoice for this customer/currency."]]);
        }
        if (! $this->customerOwnsPostedInvoice($customer, $invoice)) {
            throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} is not an eligible posted invoice for this customer/currency."]]);
        }
        if ($invoice->lock_version !== (int) $input['expected_invoice_lock_version']) {
            throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} changed after it was shown. Refresh your current bills before submitting payment proof."]]);
        }
        $outstanding = $this->outstandingAmount($invoice->id, (string) $invoice->total_charge_amount);
        if (bccomp($outstanding, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} no longer has a payable balance."]]);
        }
        if (bccomp($this->decimal($input['requested_amount']), $outstanding, 2) !== 0) {
            throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} must be submitted for its full current balance of {$outstanding}."]]);
        }
    }

    /**
     * Posted invoices visible to a portal customer: owned directly, or walk-in bills
     * linked to them after claim (invoice.customer_id may remain the shell FK).
     */
    protected function portalVisiblePostedInvoicesQuery(User $actor, Customer $customer)
    {
        $walkInIds = WalkInCustomer::where('organization_id', $actor->organization_id)
            ->where('customer_id', $customer->id)
            ->pluck('id');

        return Invoice::where('organization_id', $actor->organization_id)
            ->where('status', 'POSTED')
            ->where(function ($query) use ($customer, $walkInIds): void {
                $query->where('customer_id', $customer->id);
                if ($walkInIds->isNotEmpty()) {
                    $query->orWhereIn('walk_in_customer_id', $walkInIds);
                }
            });
    }

    protected function customerOwnsPostedInvoice(Customer $customer, Invoice $invoice): bool
    {
        if ((int) $invoice->customer_id === (int) $customer->id) {
            return true;
        }

        if (! $invoice->walk_in_customer_id) {
            return false;
        }

        return WalkInCustomer::whereKey($invoice->walk_in_customer_id)
            ->where('customer_id', $customer->id)
            ->exists();
    }

    /**
     * @param  Collection<int, ReceiptAllocation>|\Illuminate\Database\Eloquent\Collection<int, ReceiptAllocation>  $allocations
     * @return array<int, array<string, mixed>>
     */
    protected function mapReceiptHistory($allocations): array
    {
        return $allocations->map(function (ReceiptAllocation $allocation): array {
            $receipt = $allocation->receipt;
            $artifact = $receipt?->canonicalArtifact;

            return [
                'receipt_id' => $allocation->receipt_id,
                'receipt_number' => $receipt?->receipt_number,
                'receipt_kind' => $receipt?->receipt_kind ?? Receipt::KIND_OFFICIAL,
                'counts_as_official_receipt' => (bool) ($receipt?->counts_as_official_receipt ?? true),
                'business_date' => $receipt?->business_date?->toDateString(),
                'posted_at' => $receipt?->posted_at?->toIso8601String(),
                'applied_amount' => (string) $allocation->applied_amount,
                'pdf_available' => $artifact
                    && in_array($artifact->status, ['RENDERED', 'FAILED'], true)
                    && ($artifact->status === 'FAILED' || $artifact->existsOnDisk()),
            ];
        })->values()->all();
    }

    /** @param Collection<int, ManualPaymentSubmissionItem> $items
     * @param array<int, array<string, mixed>> $approvals
     * @return array<int, array<string, mixed>> */
    protected function postingAllocations(
        $items,
        array $approvals,
        ?string $declaredReference,
        ?string $confirmedReference,
        string $paymentTenderType,
        string $paymentTenderStatus,
    ): array {
        $byInvoice = collect($approvals)->map(function (array $approval): array {
            $approval['invoice_id'] = (int) $approval['invoice_id'];
            $approval['cash_amount'] = $this->decimal((string) $approval['cash_amount']);
            $approval['withholding_applications'] = collect($approval['withholding_applications'] ?? [])
                ->map(fn (array $application) => ['certificate_id' => (int) $application['certificate_id'], 'amount' => $this->decimal((string) $application['amount'])])
                ->sortBy('certificate_id')->values()->all();

            return $approval;
        })->keyBy('invoice_id');
        if ($byInvoice->count() !== count($approvals) || $byInvoice->keys()->sort()->values()->all() !== $items->pluck('invoice_id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['allocations' => ['The teller decision must include each submitted invoice exactly once and no other invoice.']]);
        }

        $totalRecognized = '0.00';
        $allocations = [];
        foreach ($items as $item) {
            $approval = $byInvoice->get($item->invoice_id);
            $withholdingAmount = '0.00';
            foreach ($approval['withholding_applications'] as $application) {
                $withholdingAmount = bcadd($withholdingAmount, $application['amount'], 2);
            }
            $decisionAmount = bcadd($approval['cash_amount'], $withholdingAmount, 2);
            if (bccomp($decisionAmount, (string) $item->requested_amount, 2) > 0) {
                throw ValidationException::withMessages(['allocations' => ["Confirmed funds for invoice {$item->invoice_id} cannot exceed the customer's selected balance."]]);
            }
            $totalRecognized = bcadd($totalRecognized, $decisionAmount, 2);
            $allocations[] = [
                'invoice_id' => $item->invoice_id,
                'expected_invoice_lock_version' => $item->expected_invoice_lock_version,
                'tenders' => [[
                    'type' => $paymentTenderType,
                    'status' => $paymentTenderStatus,
                    'amount' => $approval['cash_amount'],
                    'reference' => $confirmedReference ?? $declaredReference,
                ]],
                'withholding_applications' => $approval['withholding_applications'],
            ];
        }
        if (bccomp($totalRecognized, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['allocations' => ['A teller approval must recognize a positive confirmed cash or withholding amount.']]);
        }

        return $allocations;
    }

    protected function assertAssignedReview(ManualPaymentSubmission $submission, User $teller, int $expectedVersion): void
    {
        if ($submission->status !== ManualPaymentSubmission::STATUS_IN_REVIEW) {
            throw ValidationException::withMessages(['status' => ["Only an in-review proof can be decided. Current status: {$submission->status}."]]);
        }
        if ($submission->assigned_to_user_id !== $teller->id) {
            throw new AuthorizationException('Only the teller assigned through claim-next may decide this payment proof.');
        }
        if ($submission->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['expected_version' => ['This proof changed while you were reviewing it. Refresh before deciding.']]);
        }
    }

    protected function outstandingAmount(int $invoiceId, string $totalCharge): string
    {
        $applied = (string) ReceiptAllocation::where('invoice_id', $invoiceId)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
            ->sum('applied_amount');

        return $this->positiveDifference($totalCharge, $applied);
    }

    protected function positiveDifference(string $total, string $applied): string
    {
        $remaining = bcsub($total, $applied, 2);

        return bccomp($remaining, '0.00', 2) > 0 ? $remaining : '0.00';
    }

    protected function decimal(string $amount): string
    {
        return bcadd($amount, '0.00', 2);
    }

    /** @param array<int, array<string, mixed>> $postingAllocations */
    protected function approvalFingerprint(?string $confirmedReference, array $postingAllocations, string $receiptKind = Receipt::KIND_OFFICIAL): string
    {
        return hash('sha256', (string) json_encode([
            'confirmed_reference' => $confirmedReference,
            'receipt_kind' => strtoupper($receiptKind),
            'allocations' => $postingAllocations,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $metadata */
    protected function event(ManualPaymentSubmission $submission, ?User $actor, string $eventType, ?string $fromStatus, string $toStatus, ?string $notes, array $metadata): void
    {
        ManualPaymentSubmissionEvent::create([
            'manual_payment_submission_id' => $submission->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => Carbon::now(),
        ]);
    }

    protected function audit(ManualPaymentSubmission $submission, User $actor, string $eventType, string $permission, string $reason): void
    {
        AuditEvent::create([
            'organization_id' => $submission->organization_id,
            'event_type' => $eventType,
            'aggregate_type' => 'MANUAL_PAYMENT_SUBMISSION',
            'aggregate_id' => $submission->id,
            'aggregate_version' => $submission->lock_version,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => $permission,
            'occurred_at' => Carbon::now(),
            'reason' => $reason,
            'metadata' => ['status' => $submission->status, 'source_key' => $submission->source_key, 'receipt_id' => $submission->receipt_id],
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function notifyCustomer(ManualPaymentSubmission $submission, string $title, string $body, array $data): void
    {
        $this->notifications->publish(
            (int) $submission->organization_id,
            (int) $submission->submitted_by_user_id,
            'PAYMENT',
            $title,
            $body,
            $data,
        );
    }

    /**
     * Create an authoritative local event after its owning payment operation commits. This
     * helper never calls a provider and deliberately omits proof, bank, amount and teller-note
     * data from the safe SMS template payload.
     *
     * @param  array<string,mixed>  $payload
     */
    protected function dispatchPaymentTransactionalNotice(ManualPaymentSubmission $submission, string $eventKey, array $payload): void
    {
        $occurredAt = Carbon::now('Asia/Manila');
        $dispatch = function () use ($submission, $eventKey, $payload, $occurredAt): void {
            try {
                $event = $this->notificationEvents->record([
                    'organization_id' => $submission->organization_id,
                    'event_key' => $eventKey,
                    'event_source_type' => 'manual_payment_submission',
                    'event_source_id' => $submission->id,
                    'user_id' => $submission->submitted_by_user_id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => $occurredAt,
                ], $this->submissionLocationIds($submission));
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

    /** @return array<int,int> */
    protected function submissionLocationIds(ManualPaymentSubmission $submission): array
    {
        $receiptLocationId = $submission->receipt_id === null
            ? null
            : Receipt::whereKey($submission->receipt_id)->value('location_id');

        if ($receiptLocationId !== null) {
            return [(int) $receiptLocationId];
        }

        if ($submission->payment_group_id === null) {
            return [];
        }

        return PaymentGroupItem::query()
            ->join('invoices', 'invoices.id', '=', 'payment_group_items.invoice_id')
            ->where('payment_group_items.payment_group_id', $submission->payment_group_id)
            ->whereNotNull('invoices.location_id')
            ->pluck('invoices.location_id')
            ->map(static fn ($locationId): int => (int) $locationId)
            ->unique()
            ->values()
            ->all();
    }

    protected function loadSubmission(ManualPaymentSubmission $submission): ManualPaymentSubmission
    {
        return $submission->fresh([
            'customer', 'paymentGroup.items.invoice', 'paymentGroup.policyVersion', 'proofFile.latestVersion', 'receipt.canonicalArtifact', 'submittedBy:id,name,email',
            'assignedTeller:id,name,email', 'reviewer:id,name,email', 'items.invoice',
            'proofVersions.privateFile.latestVersion', 'events.actor:id,name,email',
        ]);
    }
}
