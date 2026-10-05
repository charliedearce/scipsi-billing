<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRequest;
use App\Models\BillingRequestDocument;
use App\Models\BillingRequestEvent;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerUserLink;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\TariffVersion;
use App\Models\User;
use App\Services\Billing\BillingRequestQueueService;
use App\Services\Billing\InvoicePostingService;
use App\Services\Uploads\PrivateStorageService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingRequestQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller1;

    protected User $teller2;

    protected User $customerUser1;

    protected User $customerUser2;

    protected Customer $customer1;

    protected Customer $customer2;

    protected Organization $org;

    protected Location $loc;

    protected DocumentType $blDocType;

    protected DocumentRequirement $blRequirement;

    protected PrivateStorageService $storageService;

    protected BillingRequestQueueService $queueService;

    protected InvoicePostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local_private');

        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->loc = Location::first();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();

        $customerRole = Role::where('name', 'Customer')->first();

        // The shared baseline seeder owns these teller identities, roles, and
        // location assignments. Reuse them so this queue fixture remains
        // compatible with the baseline rather than colliding on unique emails.
        $this->teller1 = User::query()->where('email', 'teller1@scipsi.test')->firstOrFail();
        $this->teller2 = User::query()->where('email', 'teller2@scipsi.test')->firstOrFail();

        // Customer 1 & User
        $this->customer1 = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-001',
            'name' => 'Pacific Shipping Lines Inc.',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $bp1 = CustomerBuyerProfile::create([
            'customer_id' => $this->customer1->id,
            'current_version' => 1,
            'is_active' => true,
        ]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $bp1->id,
            'version' => 1,
            'registered_name' => 'Pacific Shipping Lines Inc.',
            'trade_name' => 'Pacific Lines',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'VATABLE',
            'billing_address' => [
                'street' => 'Makar Wharf',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
                'country' => 'Philippines',
            ],
            'contact_email' => 'officer@pacific.test',
            'contact_phone' => '+639170000002',
            'status' => 'active',
            'effective_from' => now()->subDay(),
            'is_tax_exempt' => false,
            'is_zero_rated' => false,
        ]);

        $this->customerUser1 = User::create([
            'name' => 'Pacific Logistics Officer',
            'email' => 'officer@pacific.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser1->roles()->attach($customerRole->id);
        CustomerUserLink::create([
            'customer_id' => $this->customer1->id,
            'user_id' => $this->customerUser1->id,
            'authority_role' => 'BILLING_OFFICER',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        // Customer 2 & User
        $this->customer2 = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-002',
            'name' => 'Southern Cargo Express',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);
        $this->customerUser2 = User::create([
            'name' => 'Southern Agent',
            'email' => 'agent@southern.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser2->roles()->attach($customerRole->id);
        CustomerUserLink::create([
            'customer_id' => $this->customer2->id,
            'user_id' => $this->customerUser2->id,
            'authority_role' => 'BILLING_OFFICER',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        // Setup Document Requirement for 'CARGO_HANDLING'
        DocumentRequirement::where('service_type', 'CARGO_HANDLING')->delete();
        $this->blDocType = DocumentType::where('code', 'BILL_OF_LADING')->first();
        $this->blRequirement = DocumentRequirement::updateOrCreate(
            [
                'organization_id' => $this->org->id,
                'location_id' => $this->loc->id,
                'service_type' => 'CARGO_HANDLING',
                'document_type_id' => $this->blDocType->id,
            ],
            [
                'is_required' => true,
                'effective_from' => now()->subDay(),
                'version' => 1,
                'lock_version' => 1,
            ]
        );

        $this->storageService = app(PrivateStorageService::class);
        $this->queueService = app(BillingRequestQueueService::class);
        $this->postingService = app(InvoicePostingService::class);
    }

    public function test_customer_can_create_draft_and_attach_clean_documents(): void
    {
        // 1. Create draft
        $response = $this->actingAs($this->customerUser1)->postJson('/api/v1/customer/billing-requests', [
            'customer_id' => $this->customer1->id,
            'location_id' => $this->loc->id,
            'service_type' => 'CARGO_HANDLING',
            'notes' => 'Please expedite cargo billing',
        ]);

        $response->assertStatus(201);
        $reqId = $response->json('billing_request.id');
        $this->assertNotNull($reqId);
        $this->assertEquals(BillingRequest::STATUS_DRAFT, $response->json('billing_request.status'));
        $this->assertStringStartsWith('REQ-', $response->json('billing_request.transaction_no'));

        // 2. Upload file
        $fakePdf = UploadedFile::fake()->create('bill_of_lading.pdf', 500, 'application/pdf');
        $file = $this->storageService->storeFile($fakePdf, $this->blDocType, $this->customerUser1);

        // 3. Attach file to draft
        $attachRes = $this->actingAs($this->customerUser1)->postJson("/api/v1/customer/billing-requests/{$reqId}/documents", [
            'document_type_id' => $this->blDocType->id,
            'private_file_id' => $file->id,
            'customer_notes' => 'Latest BL copy from vessel',
        ]);

        $attachRes->assertStatus(200);
        $this->assertDatabaseHas('billing_request_documents', [
            'billing_request_id' => $reqId,
            'document_type_id' => $this->blDocType->id,
            'private_file_id' => $file->id,
            'review_status' => 'PENDING',
        ]);
    }

    public function test_customer_can_remove_attached_document_from_draft(): void
    {
        $req = $this->queueService->createDraft(
            $this->customerUser1,
            $this->customer1,
            $this->loc->id,
            'CARGO_HANDLING'
        );
        $fakePdf = UploadedFile::fake()->create('bill_of_lading.pdf', 500, 'application/pdf');
        $file = $this->storageService->storeFile($fakePdf, $this->blDocType, $this->customerUser1);
        $doc = $this->queueService->attachDocument(
            $req,
            $this->customerUser1,
            $this->blDocType->id,
            $file->id,
            $this->blRequirement->id
        );

        $this->actingAs($this->customerUser1)
            ->deleteJson("/api/v1/customer/billing-requests/{$req->id}/documents/{$doc->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Document removed successfully.');

        $this->assertDatabaseMissing('billing_request_documents', ['id' => $doc->id]);
        $this->assertDatabaseHas('private_files', ['id' => $file->id]);
        $this->assertDatabaseHas('billing_request_events', [
            'billing_request_id' => $req->id,
            'event_type' => 'DOCUMENT_REMOVED',
        ]);

        $queued = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $queuedDoc = $queued->documents()->firstOrFail();
        $this->actingAs($this->customerUser1)
            ->deleteJson("/api/v1/customer/billing-requests/{$queued->id}/documents/{$queuedDoc->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_submission_auto_admits_when_all_requirements_are_met(): void
    {
        // 1. Create draft
        $req = $this->queueService->createDraft(
            $this->customerUser1,
            $this->customer1,
            $this->loc->id,
            'CARGO_HANDLING'
        );

        // 2. Attach required clean document
        $fakePdf = UploadedFile::fake()->create('bl.pdf', 400, 'application/pdf');
        $file = $this->storageService->storeFile($fakePdf, $this->blDocType, $this->customerUser1);
        $this->queueService->attachDocument($req, $this->customerUser1, $this->blDocType->id, $file->id, $this->blRequirement->id);

        // 3. Submit request
        $submitRes = $this->actingAs($this->customerUser1)->postJson("/api/v1/customer/billing-requests/{$req->id}/submit");
        $submitRes->assertStatus(200);
        $submitRes->assertJsonPath('billing_request.status', BillingRequest::STATUS_QUEUED);
        $this->assertNotNull($submitRes->json('billing_request.ticket_number'));
        $this->assertNotNull($submitRes->json('billing_request.initial_submitted_at'));
        $this->assertEquals(0, $submitRes->json('queue_position')); // First in line

        // Check InAppNotification was created
        $this->assertDatabaseHas('in_app_notifications', [
            'organization_id' => $this->org->id,
            'user_id' => $this->customerUser1->id,
            'type' => 'QUEUE',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'organization_id' => $this->org->id,
            'user_id' => $this->teller1->id,
            'type' => 'TELLER_BILLING',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'organization_id' => $this->org->id,
            'user_id' => $this->teller2->id,
            'type' => 'TELLER_BILLING',
        ]);
    }

    public function test_submission_is_rejected_when_mandatory_document_is_missing(): void
    {
        // Create draft with NO attached documents
        $req = $this->queueService->createDraft(
            $this->customerUser1,
            $this->customer1,
            $this->loc->id,
            'CARGO_HANDLING'
        );

        $submitRes = $this->actingAs($this->customerUser1)->postJson("/api/v1/customer/billing-requests/{$req->id}/submit");
        $submitRes->assertStatus(422);
        $submitRes->assertJsonValidationErrors(['requirements']);
        $this->assertEquals(BillingRequest::STATUS_DRAFT, $req->fresh()->status);
    }

    public function test_atomic_claim_next_picks_oldest_eligible_in_fifo_order(): void
    {
        // Create 2 queued requests at distinct timestamps
        Carbon::setTestNow('2026-09-19 09:00:00');
        $req1 = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        Carbon::setTestNow('2026-09-19 09:15:00');
        $req2 = $this->createQueuedRequest($this->customerUser2, $this->customer2);

        Carbon::setTestNow('2026-09-19 09:30:00');

        // Teller 1 claims next
        $claim1 = $this->actingAs($this->teller1)->postJson('/api/v1/teller/queue/claim-next', [
            'location_id' => $this->loc->id,
        ]);
        $claim1->assertStatus(200);
        $this->assertEquals($req1->id, $claim1->json('claimed.id'));
        $this->assertEquals(BillingRequest::STATUS_IN_REVIEW, $req1->fresh()->status);
        $this->assertEquals($this->teller1->id, $req1->fresh()->assigned_to_user_id);

        // Teller 2 claims next -> gets req2
        $claim2 = $this->actingAs($this->teller2)->postJson('/api/v1/teller/queue/claim-next', [
            'location_id' => $this->loc->id,
        ]);
        $claim2->assertStatus(200);
        $this->assertEquals($req2->id, $claim2->json('claimed.id'));

        // Teller 1 attempts claim again -> no requests left
        $claim3 = $this->actingAs($this->teller1)->postJson('/api/v1/teller/queue/claim-next', [
            'location_id' => $this->loc->id,
        ]);
        $claim3->assertStatus(200);
        $this->assertNull($claim3->json('claimed'));

        Carbon::setTestNow();
    }

    public function test_teller_heartbeat_extends_lease(): void
    {
        Carbon::setTestNow('2026-09-19 10:00:00');
        $req = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);

        Carbon::setTestNow('2026-09-19 10:05:00');
        $hbRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$claimed->id}/heartbeat");
        $hbRes->assertStatus(200);

        $this->assertEquals('2026-09-19 10:05:00', $claimed->fresh()->assignment_heartbeat_at->toDateTimeString());
        Carbon::setTestNow();
    }

    public function test_request_correction_and_resubmission_retains_original_priority(): void
    {
        // 1. Req 1 queued at 08:00
        Carbon::setTestNow('2026-09-19 08:00:00');
        $req1 = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $initialPriorityTime = $req1->initial_submitted_at;
        $originalTicket = $req1->ticket_number;

        // 2. Teller claims Req 1 at 08:10
        Carbon::setTestNow('2026-09-19 08:10:00');
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertEquals($req1->id, $claimed->id);

        // 3. Teller returns Req 1 for correction at 08:15
        Carbon::setTestNow('2026-09-19 08:15:00');
        $corrRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req1->id}/request-correction", [
            'notes' => 'Bill of Lading page 2 is blurry. Please upload clear scan.',
            'file_remarks' => [
                [
                    'document_type_id' => $this->blDocType->id,
                    'rejection_reason' => 'Blurry copy, missing seal',
                ],
            ],
        ]);
        $corrRes->assertStatus(200);
        $this->assertEquals(BillingRequest::STATUS_NEEDS_CORRECTION, $req1->fresh()->status);
        $this->assertEquals(1, $req1->fresh()->correction_rounds);
        $this->assertNull($req1->fresh()->assigned_to_user_id);

        // 4. Meanwhile, Req 2 arrives at 08:20
        Carbon::setTestNow('2026-09-19 08:20:00');
        $req2 = $this->createQueuedRequest($this->customerUser2, $this->customer2);

        // 5. Customer 1 uploads replacement file version at 08:30 and resubmits
        Carbon::setTestNow('2026-09-19 08:30:00');
        $doc = $req1->documents()->first();
        $replacementPdf = UploadedFile::fake()->create('bl_clear.pdf', 600, 'application/pdf');
        $this->storageService->replaceFile($doc->privateFile, $replacementPdf, $this->customerUser1, 'Uploaded high-res scan');

        $resubmitRes = $this->actingAs($this->customerUser1)->postJson("/api/v1/customer/billing-requests/{$req1->id}/resubmit", [
            'notes' => 'Attached high-res scan with seal',
        ]);
        $resubmitRes->assertStatus(200);

        // 6. VERIFY FAIRNESS INVARIANT (Decision W22):
        // initial_submitted_at and ticket_number MUST BE PRESERVED!
        $req1Fresh = $req1->fresh();
        $this->assertEquals(BillingRequest::STATUS_QUEUED, $req1Fresh->status);
        $this->assertEquals($initialPriorityTime->toDateTimeString(), $req1Fresh->initial_submitted_at->toDateTimeString());
        $this->assertEquals($originalTicket, $req1Fresh->ticket_number);

        // 7. When teller claims next, Req 1 MUST BE CLAIMED BEFORE Req 2!
        $nextClaim = $this->actingAs($this->teller2)->postJson('/api/v1/teller/queue/claim-next', [
            'location_id' => $this->loc->id,
        ]);
        $nextClaim->assertStatus(200);
        $this->assertEquals($req1->id, $nextClaim->json('claimed.id'), 'Resubmitted request with earlier initial priority must be claimed before newer requests');

        Carbon::setTestNow();
    }

    public function test_cross_day_carryover_maintains_fifo_order_ahead_of_today_submissions(): void
    {
        // Req 1 submitted yesterday (Day 1)
        Carbon::setTestNow('2026-09-18 17:00:00');
        $yesterdayReq = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        // Req 2 submitted this morning (Day 2)
        Carbon::setTestNow('2026-09-19 08:00:00');
        $todayReq = $this->createQueuedRequest($this->customerUser2, $this->customer2);

        // Teller claims on Day 2
        Carbon::setTestNow('2026-09-19 08:05:00');
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);

        $this->assertEquals($yesterdayReq->id, $claimed->id, 'Cross-day carried over request must retain FIFO priority');
        Carbon::setTestNow();
    }

    public function test_stale_assignment_is_recovered_without_losing_customer_priority(): void
    {
        Carbon::setTestNow('2026-09-19 10:00:00');
        $req = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $initialPriority = $req->initial_submitted_at;

        // Claimed by teller 1
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertEquals(BillingRequest::STATUS_IN_REVIEW, $claimed->status);

        // 20 minutes pass without heartbeat
        Carbon::setTestNow('2026-09-19 10:20:00');

        $recoverRes = $this->actingAs($this->teller2)->postJson('/api/v1/teller/queue/recover-stale', [
            'stale_minutes' => 15,
        ]);
        $recoverRes->assertStatus(200);
        $this->assertEquals(1, $recoverRes->json('recovered_count'));

        // Verify request returned to QUEUED with priority intact
        $reqFresh = $req->fresh();
        $this->assertEquals(BillingRequest::STATUS_QUEUED, $reqFresh->status);
        $this->assertNull($reqFresh->assigned_to_user_id);
        $this->assertEquals($initialPriority->toDateTimeString(), $reqFresh->initial_submitted_at->toDateTimeString());

        // Teller 2 can now claim it
        $claimRes = $this->actingAs($this->teller2)->postJson('/api/v1/teller/queue/claim-next', [
            'location_id' => $this->loc->id,
        ]);
        $claimRes->assertStatus(200);
        $this->assertEquals($req->id, $claimRes->json('claimed.id'));

        Carbon::setTestNow();
    }

    public function test_prepare_draft_and_bill_ready_completion_links_posted_invoice_idempotently(): void
    {
        $req = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);

        // 1. Prepare billing draft
        $draftRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/prepare-draft");
        $draftRes->assertStatus(200);
        $invoiceId = $draftRes->json('invoice.id');
        $this->assertNotNull($invoiceId);
        $this->assertEquals(BillingRequest::STATUS_BILLING_IN_PROGRESS, $req->fresh()->status);
        $acceptedDocument = $req->documents()->with('privateFile.latestVersion')->firstOrFail();
        $this->assertSame(BillingRequestDocument::STATUS_ACCEPTED, $acceptedDocument->review_status);
        $this->assertSame(
            $acceptedDocument->privateFile->latestVersion->version_number,
            $acceptedDocument->reviewed_version_number
        );

        // 2. Populate and post the invoice using InvoicePostingService
        $invoice = Invoice::findOrFail($invoiceId);
        $tariffVersion = TariffVersion::where('status', 'effective')->firstOrFail();

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'tariff_version_id' => $tariffVersion->id,
            'description' => 'Cargo stevedoring charges',
            'quantity' => '10.0000',
            'unit_rate' => '150.0000',
            'base_gross_amount' => '1500.00',
            'fuel_surcharge_amount' => '0.00',
            'gross_amount' => '1500.00',
            'ppa_amount' => '0.00',
            'discount_amount' => '0.00',
            'net_amount' => '1500.00',
            'tax_amount' => '180.00',
            'total_charge_amount' => '1680.00',
        ]);

        $invoice->update([
            ...$this->invoiceShipmentPayload($this->org->id),
            'base_gross_amount' => '1500.00',
            'gross_amount' => '1500.00',
            'net_amount' => '1500.00',
            'tax_amount' => '180.00',
            'total_charge_amount' => '1680.00',
            'is_fiscal_ready' => true,
        ]);

        // Post the invoice
        $postedInvoice = $this->postingService->postInvoice($invoice, $this->teller1, $invoice->lock_version);
        $this->assertEquals('POSTED', $postedInvoice->status);

        // 3. Mark request bill-ready
        $readyRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/mark-bill-ready", [
            'invoice_id' => $postedInvoice->id,
        ]);
        $readyRes->assertStatus(200);
        $this->assertEquals(BillingRequest::STATUS_BILL_READY, $req->fresh()->status);
        $this->assertEquals($postedInvoice->id, $req->fresh()->invoice_id);
        $this->assertNull($req->fresh()->assigned_to_user_id);
        $this->assertNull($req->fresh()->assignment_heartbeat_at);
        $this->assertDatabaseHas('billing_request_invoices', [
            'billing_request_id' => $req->id,
            'invoice_id' => $postedInvoice->id,
        ]);

        $queue = $this->actingAs($this->teller1)->getJson('/api/v1/teller/queue?location_id='.$this->loc->id)
            ->assertOk();
        $this->assertNull($queue->json('my_active_assignment'));
        $this->assertTrue(collect($queue->json('completed_tracking'))->contains(fn ($row) => (int) $row['id'] === $req->id));

        // 4. Repeated call is idempotent without creating errors or multiple entries
        $readyRes2 = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/mark-bill-ready", [
            'invoice_id' => $postedInvoice->id,
        ]);
        $readyRes2->assertStatus(200);
        $this->assertEquals(BillingRequest::STATUS_BILL_READY, $req->fresh()->status);
        $this->assertNull($req->fresh()->assigned_to_user_id);
        $this->assertEquals(1, $req->fresh()->invoiceLinks()->count());
    }

    public function test_multi_bill_request_keeps_claim_until_complete_true(): void
    {
        $req = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);

        $draftRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/prepare-draft");
        $draftRes->assertOk();
        $firstInvoice = $this->populateAndPostInvoice(
            Invoice::findOrFail($draftRes->json('invoice.id')),
            $this->teller1
        );

        $partialReady = $this->actingAs($this->teller1)->postJson(
            "/api/v1/teller/billing-requests/{$req->id}/mark-bill-ready",
            ['invoice_id' => $firstInvoice->id, 'complete' => false]
        );
        $partialReady->assertOk();
        $fresh = $req->fresh();
        $this->assertEquals(BillingRequest::STATUS_BILL_READY, $fresh->status);
        $this->assertEquals($this->teller1->id, $fresh->assigned_to_user_id);
        $this->assertNull($fresh->draft_invoice_id);
        $this->assertEquals($firstInvoice->id, $fresh->invoice_id);
        $this->assertEquals(1, $fresh->invoiceLinks()->count());

        $queue = $this->actingAs($this->teller1)->getJson('/api/v1/teller/queue?location_id='.$this->loc->id)
            ->assertOk();
        $this->assertEquals($req->id, $queue->json('my_active_assignment.id'));
        $this->assertFalse(
            collect($queue->json('completed_tracking'))->contains(fn ($row) => (int) $row['id'] === $req->id)
        );

        $secondDraftRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/prepare-draft");
        $secondDraftRes->assertOk();
        $secondDraftId = (int) $secondDraftRes->json('invoice.id');
        $this->assertNotEquals($firstInvoice->id, $secondDraftId);
        $this->assertEquals(BillingRequest::STATUS_BILLING_IN_PROGRESS, $req->fresh()->status);
        $this->actingAs($this->teller1)->getJson("/api/v1/teller/billing-requests/{$req->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.progress.current_step', 'bill_ready')
            ->assertJsonPath('billing_request.progress.steps.3.status', 'complete');

        $secondInvoice = $this->populateAndPostInvoice(Invoice::findOrFail($secondDraftId), $this->teller1);
        $finish = $this->actingAs($this->teller1)->postJson(
            "/api/v1/teller/billing-requests/{$req->id}/mark-bill-ready",
            ['invoice_id' => $secondInvoice->id, 'complete' => true]
        );
        $finish->assertOk();

        $done = $req->fresh(['invoices']);
        $this->assertEquals(BillingRequest::STATUS_BILL_READY, $done->status);
        $this->assertNull($done->assigned_to_user_id);
        $this->assertEquals($firstInvoice->id, $done->invoice_id, 'Primary invoice_id stays the first ready bill');
        $this->assertEquals(2, $done->invoices->count());
        $this->assertEqualsCanonicalizing(
            [$firstInvoice->id, $secondInvoice->id],
            $done->invoices->pluck('id')->all()
        );

        $customerView = $this->actingAs($this->customerUser1)->getJson("/api/v1/customer/billing-requests/{$req->id}")
            ->assertOk();
        $this->assertCount(2, $customerView->json('billing_request.invoices'));
        $this->assertEquals(2, $customerView->json('billing_request.invoice_count'));
    }

    public function test_customer_cannot_view_or_manipulate_foreign_billing_requests(): void
    {
        $req1 = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        // Customer 2 attempts to view Customer 1's request
        $viewRes = $this->actingAs($this->customerUser2)->getJson("/api/v1/customer/billing-requests/{$req1->id}");
        $viewRes->assertStatus(403);

        // Customer 2 attempts to cancel Customer 1's request
        $cancelRes = $this->actingAs($this->customerUser2)->postJson("/api/v1/customer/billing-requests/{$req1->id}/cancel", [
            'reason' => 'Malicious cancellation',
        ]);
        $cancelRes->assertStatus(403);
    }

    public function test_teller_release_preserves_priority_and_cancel_is_terminal(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');
        $req1 = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $req2 = $this->createQueuedRequest($this->customerUser2, $this->customer2);
        $originalPriority = $req1->initial_submitted_at->toDateTimeString();

        Carbon::setTestNow('2026-09-21 09:05:00');
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertEquals($req1->id, $claimed->id);

        // Release so the teller can process another waiting request.
        $releaseRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req1->id}/release", [
            'reason' => 'Customer stepped away; taking next ticket',
        ]);
        $releaseRes->assertOk()
            ->assertJsonPath('billing_request.status', BillingRequest::STATUS_QUEUED);
        $this->assertNull($req1->fresh()->assigned_to_user_id);
        $this->assertEquals($originalPriority, $req1->fresh()->initial_submitted_at->toDateTimeString());

        // Oldest eligible after release is still req1.
        $reclaimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertEquals($req1->id, $reclaimed->id);

        $cancelRes = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req1->id}/cancel", [
            'reason' => 'Duplicate submission for the same cargo',
        ]);
        $cancelRes->assertOk()
            ->assertJsonPath('billing_request.status', BillingRequest::STATUS_CANCELLED);

        $cancelled = $req1->fresh();
        $this->assertEquals(BillingRequest::STATUS_CANCELLED, $cancelled->status);
        $this->assertNull($cancelled->assigned_to_user_id);
        $this->assertEquals('Duplicate submission for the same cargo', $cancelled->correction_notes);
        $this->assertTrue(
            $cancelled->events()->where('event_type', 'CANCELLED')->exists()
        );
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->customerUser1->id,
            'title' => 'Billing Request Cancelled',
        ]);

        // Next claim skips the cancelled ticket.
        $next = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertEquals($req2->id, $next->id);

        // Cancelled requests cannot be released or cancelled again.
        $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req1->id}/cancel", [
            'reason' => 'Second cancel attempt',
        ])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_shared_progress_shows_owner_and_next_step_through_payment(): void
    {
        Storage::fake('private');
        $req = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $customerShow = fn () => $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/customer/billing-requests/{$req->id}")
            ->assertOk();
        $tellerShow = fn () => $this->actingAs($this->teller1)
            ->getJson("/api/v1/teller/billing-requests/{$req->id}")
            ->assertOk();
        $stepStatuses = fn ($response) => collect($response->json('billing_request.progress.steps'))
            ->pluck('status', 'key')
            ->all();

        $customerShow()
            ->assertJsonPath('billing_request.progress.current_step', 'queued')
            ->assertJsonPath('billing_request.progress.owner.type', 'none');

        $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertSame('IN_REVIEW', $req->fresh()->status);
        $customerShow()
            ->assertJsonPath('billing_request.progress.current_step', 'review')
            ->assertJsonPath('billing_request.progress.owner.type', 'teller')
            ->assertJsonPath('billing_request.progress.owner.name', $this->teller1->name);

        $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/request-correction", [
            'notes' => 'Upload a clearer Bill of Lading.',
        ])->assertOk();
        $req->update(['internal_notes' => 'Staff-only follow-up note']);

        $correction = $customerShow()
            ->assertJsonPath('billing_request.progress.state', 'attention')
            ->assertJsonPath('billing_request.progress.owner.type', 'customer')
            ->assertJsonPath('billing_request.correction_notes', 'Upload a clearer Bill of Lading.');
        $this->assertArrayNotHasKey('events', $correction->json('billing_request'));
        $this->assertArrayNotHasKey('internal_notes', $correction->json('billing_request'));
        $this->assertArrayNotHasKey('draft_invoice_id', $correction->json('billing_request'));
        $this->assertNotEmpty($tellerShow()->json('billing_request.events'));

        $doc = $req->documents()->firstOrFail();
        $this->storageService->replaceFile(
            $doc->privateFile,
            UploadedFile::fake()->create('bl_clear.pdf', 300, 'application/pdf'),
            $this->customerUser1,
            'Clear scan'
        );
        $this->queueService->resubmit($req->fresh(), $this->customerUser1);
        $customerShow()
            ->assertJsonPath('billing_request.progress.current_step', 'queued')
            ->assertJsonPath('billing_request.progress.required_action', 'Wait for a teller. The request kept its original queue place.');

        $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $draft = $this->actingAs($this->teller1)
            ->postJson("/api/v1/teller/billing-requests/{$req->id}/prepare-draft")
            ->assertOk();
        $tellerShow()
            ->assertJsonPath('billing_request.progress.current_step', 'billing')
            ->assertJsonPath('billing_request.progress.owner.type', 'teller');

        $posted = $this->populateAndPostInvoice(Invoice::findOrFail($draft->json('invoice.id')), $this->teller1);
        $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/mark-bill-ready", [
            'invoice_id' => $posted->id,
        ])->assertOk();

        $unpaid = $customerShow()
            ->assertJsonPath('billing_request.progress.current_step', 'paid')
            ->assertJsonPath('billing_request.progress.owner.type', 'customer');
        $this->assertSame([
            'submitted' => 'complete',
            'queued' => 'complete',
            'review' => 'complete',
            'billing' => 'complete',
            'bill_ready' => 'complete',
            'paid' => 'current',
        ], $stepStatuses($unpaid));
        $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/portal/bills/{$posted->id}?customer_id={$this->customer1->id}")
            ->assertOk()
            ->assertJsonPath('data.request_progress.current_step', 'paid')
            ->assertJsonPath('data.request_progress.owner.type', 'customer');

        $this->actingAs($this->admin)->postJson('/api/v1/receipts', [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => 'PROGRESS-PAID-001',
            'customer_id' => $this->customer1->id,
            'allocations' => [[
                'invoice_id' => $posted->id,
                'tenders' => [[
                    'type' => 'BANK_TRANSFER',
                    'status' => 'CONFIRMED',
                    'amount' => (string) $posted->total_charge_amount,
                    'reference' => 'PROGRESS-PAID-001',
                ]],
            ]],
        ])->assertOk();

        $paid = $customerShow()
            ->assertJsonPath('billing_request.progress.state', 'complete')
            ->assertJsonPath('billing_request.progress.owner.type', 'none');
        $this->assertSame('complete', $stepStatuses($paid)['paid']);
        $this->assertNotNull($paid->json('billing_request.progress.steps.5.at'));
        $this->assertSame('POSTED', $posted->fresh()->status);
    }

    public function test_cancelled_request_progress_is_terminal_without_a_current_step(): void
    {
        $req = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        $this->actingAs($this->customerUser1)
            ->postJson("/api/v1/customer/billing-requests/{$req->id}/cancel", ['reason' => 'Filed twice'])
            ->assertOk()
            ->assertJsonMissingPath('billing_request.internal_notes');

        $response = $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/customer/billing-requests/{$req->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.progress.state', 'cancelled')
            ->assertJsonPath('billing_request.progress.current_step', null);

        $statuses = collect($response->json('billing_request.progress.steps'))->pluck('status', 'key');
        $this->assertSame('complete', $statuses['queued']);
        $this->assertFalse($statuses->contains('current'));
    }

    public function test_queue_estimate_is_unavailable_without_enough_completed_bills(): void
    {
        Carbon::setTestNow('2026-09-29 10:00:00');

        foreach ([10, 20, 30, 40] as $index => $minutes) {
            $this->seedHandlingSample($minutes, Carbon::parse('2026-09-28 09:00:00')->addHours($index));
        }
        $cancelled = $this->seedHandlingSample(
            15,
            Carbon::parse('2026-09-28 15:00:00'),
            BillingRequest::STATUS_CANCELLED
        );

        $waiting = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/customer/billing-requests/{$waiting->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.queue_estimate.estimate_basis', 'unavailable')
            ->assertJsonPath('billing_request.queue_estimate.estimated_wait_minutes', null)
            ->assertJsonPath('billing_request.queue_estimate.estimated_ready_minutes', null)
            ->assertJsonPath('billing_request.queue_estimate.requests_ahead', 0);

        $this->actingAs($this->customerUser2)
            ->getJson("/api/v1/customer/billing-requests/{$cancelled->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.queue_estimate', null);

        Carbon::setTestNow();
    }

    public function test_queue_estimate_uses_one_active_teller_when_two_requests_are_ahead(): void
    {
        Carbon::setTestNow('2026-09-29 10:00:00');
        $this->seedMedianTwentyMinutes();

        $otherLocation = Location::create([
            'organization_id' => $this->org->id,
            'code' => 'EST-OTHER',
            'name' => 'Other pier',
            'is_active' => true,
        ]);
        $this->seedHandlingSample(
            240,
            Carbon::parse('2026-09-28 16:00:00'),
            BillingRequest::STATUS_BILL_READY,
            $otherLocation->id
        );

        $earlyClaim = BillingRequest::query()->where('transaction_no', 'REQ-EST-1')->firstOrFail();
        BillingRequestEvent::query()->create([
            'billing_request_id' => $earlyClaim->id,
            'actor_id' => $this->teller1->id,
            'event_type' => 'CLAIMED',
            'from_status' => BillingRequest::STATUS_QUEUED,
            'to_status' => BillingRequest::STATUS_IN_REVIEW,
            'metadata' => [],
            'created_at' => Carbon::parse('2026-09-28 07:00:00'),
        ]);

        $fresh = $this->createQueuedRequest($this->customerUser2, $this->customer2);
        $fresh->update([
            'status' => BillingRequest::STATUS_IN_REVIEW,
            'assigned_to_user_id' => $this->teller1->id,
            'assigned_at' => now(),
            'assignment_heartbeat_at' => now(),
            'initial_submitted_at' => now()->subMinutes(20),
        ]);

        Carbon::setTestNow('2026-09-29 10:05:00');
        $stale = $this->createQueuedRequest($this->customerUser2, $this->customer2);
        $stale->update([
            'status' => BillingRequest::STATUS_IN_REVIEW,
            'assigned_to_user_id' => $this->teller2->id,
            'assigned_at' => now()->subMinutes(30),
            'assignment_heartbeat_at' => now()->subMinutes(20),
            'initial_submitted_at' => now()->subMinutes(10),
        ]);

        Carbon::setTestNow('2026-09-29 10:10:00');
        $waiting = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/customer/billing-requests/{$waiting->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.queue_estimate.requests_ahead', 2)
            ->assertJsonPath('billing_request.queue_estimate.estimate_basis', 'recent_bills')
            ->assertJsonPath('billing_request.queue_estimate.estimated_wait_minutes', 40)
            ->assertJsonPath('billing_request.queue_estimate.estimated_ready_minutes', 60);

        Carbon::setTestNow();
    }

    public function test_queue_estimate_divides_wait_across_two_active_tellers(): void
    {
        Carbon::setTestNow('2026-09-29 10:00:00');
        $this->seedMedianTwentyMinutes();

        $first = $this->createQueuedRequest($this->customerUser2, $this->customer2);
        $first->update([
            'status' => BillingRequest::STATUS_IN_REVIEW,
            'assigned_to_user_id' => $this->teller1->id,
            'assigned_at' => now(),
            'assignment_heartbeat_at' => now(),
            'initial_submitted_at' => now()->subMinutes(20),
        ]);

        Carbon::setTestNow('2026-09-29 10:05:00');
        $second = $this->createQueuedRequest($this->customerUser2, $this->customer2);
        $second->update([
            'status' => BillingRequest::STATUS_IN_REVIEW,
            'assigned_to_user_id' => $this->teller2->id,
            'assigned_at' => now(),
            'assignment_heartbeat_at' => now(),
            'initial_submitted_at' => now()->subMinutes(10),
        ]);

        Carbon::setTestNow('2026-09-29 10:10:00');
        $waiting = $this->createQueuedRequest($this->customerUser1, $this->customer1);

        $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/customer/billing-requests/{$waiting->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.queue_estimate.requests_ahead', 2)
            ->assertJsonPath('billing_request.queue_estimate.estimate_basis', 'recent_bills')
            ->assertJsonPath('billing_request.queue_estimate.estimated_wait_minutes', 20)
            ->assertJsonPath('billing_request.queue_estimate.estimated_ready_minutes', 40);

        Carbon::setTestNow();
    }

    public function test_queue_estimate_reports_remaining_time_while_a_teller_is_preparing_the_bill(): void
    {
        Carbon::setTestNow('2026-09-29 10:00:00');
        $this->seedMedianTwentyMinutes();
        $waiting = $this->createQueuedRequest($this->customerUser1, $this->customer1);
        $claimed = $this->queueService->claimNextEligible($this->teller1, $this->org->id, $this->loc->id);
        $this->assertNotNull($claimed);
        $this->assertEquals($waiting->id, $claimed->id);

        Carbon::setTestNow('2026-09-29 10:05:00');
        $claimed->update([
            'assigned_at' => now()->subMinutes(5),
            'assignment_heartbeat_at' => now(),
        ]);

        $this->actingAs($this->customerUser1)
            ->getJson("/api/v1/customer/billing-requests/{$waiting->id}")
            ->assertOk()
            ->assertJsonPath('billing_request.status', BillingRequest::STATUS_IN_REVIEW)
            ->assertJsonPath('billing_request.queue_estimate.estimated_wait_minutes', null)
            ->assertJsonPath('billing_request.queue_estimate.estimated_ready_minutes', 15)
            ->assertJsonPath('billing_request.queue_estimate.estimate_basis', 'recent_bills');

        Carbon::setTestNow();
    }

    /**
     * Helper to create and auto-admit a valid queued billing request.
     */
    protected function createQueuedRequest(User $user, Customer $customer): BillingRequest
    {
        $req = $this->queueService->createDraft(
            $user,
            $customer,
            $this->loc->id,
            'CARGO_HANDLING'
        );

        $fakePdf = UploadedFile::fake()->create('bl.pdf', 300, 'application/pdf');
        $file = $this->storageService->storeFile($fakePdf, $this->blDocType, $user);
        $this->queueService->attachDocument($req, $user, $this->blDocType->id, $file->id, $this->blRequirement->id);

        return $this->queueService->submitRequest($req, $user);
    }

    protected function populateAndPostInvoice(Invoice $invoice, User $teller): Invoice
    {
        $tariffVersion = TariffVersion::where('status', 'effective')->firstOrFail();

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'tariff_version_id' => $tariffVersion->id,
            'description' => 'Cargo stevedoring charges',
            'quantity' => '10.0000',
            'unit_rate' => '150.0000',
            'base_gross_amount' => '1500.00',
            'fuel_surcharge_amount' => '0.00',
            'gross_amount' => '1500.00',
            'ppa_amount' => '0.00',
            'discount_amount' => '0.00',
            'net_amount' => '1500.00',
            'tax_amount' => '180.00',
            'total_charge_amount' => '1680.00',
        ]);

        $invoice->update([
            ...$this->invoiceShipmentPayload($this->org->id),
            'base_gross_amount' => '1500.00',
            'gross_amount' => '1500.00',
            'net_amount' => '1500.00',
            'tax_amount' => '180.00',
            'total_charge_amount' => '1680.00',
            'is_fiscal_ready' => true,
        ]);

        return $this->postingService->postInvoice($invoice->fresh(), $teller, $invoice->fresh()->lock_version);
    }

    protected int $estimateSampleSeq = 0;

    protected function seedMedianTwentyMinutes(): void
    {
        $readyAt = Carbon::parse('2026-09-28 09:00:00');
        foreach ([10, 20, 20, 30, 40] as $minutes) {
            $this->seedHandlingSample($minutes, $readyAt->copy());
            $readyAt->addHour();
        }
    }

    protected function seedHandlingSample(
        int $handlingMinutes,
        Carbon $readyAt,
        string $status = BillingRequest::STATUS_BILL_READY,
        ?int $locationId = null,
    ): BillingRequest {
        $this->estimateSampleSeq++;
        $claimedAt = $readyAt->copy()->subMinutes($handlingMinutes);
        $submittedAt = $claimedAt->copy()->subMinutes(5);
        $request = BillingRequest::query()->create([
            'organization_id' => $this->org->id,
            'location_id' => $locationId ?? $this->loc->id,
            'customer_id' => $this->customer2->id,
            'created_by_user_id' => $this->customerUser2->id,
            'service_type' => 'CARGO_HANDLING',
            'transaction_no' => 'REQ-EST-'.$this->estimateSampleSeq,
            'ticket_number' => 8000 + $this->estimateSampleSeq,
            'status' => $status,
            'initial_submitted_at' => $submittedAt,
            'submitted_at' => $submittedAt,
            'admitted_at' => $submittedAt,
            'lock_version' => 1,
        ]);

        BillingRequestEvent::query()->create([
            'billing_request_id' => $request->id,
            'actor_id' => $this->teller1->id,
            'event_type' => 'CLAIMED',
            'from_status' => BillingRequest::STATUS_QUEUED,
            'to_status' => BillingRequest::STATUS_IN_REVIEW,
            'metadata' => [],
            'created_at' => $claimedAt,
        ]);
        BillingRequestEvent::query()->create([
            'billing_request_id' => $request->id,
            'actor_id' => $this->teller1->id,
            'event_type' => 'BILL_READY',
            'from_status' => BillingRequest::STATUS_BILLING_IN_PROGRESS,
            'to_status' => BillingRequest::STATUS_BILL_READY,
            'metadata' => [],
            'created_at' => $readyAt,
        ]);

        return $request;
    }
}
