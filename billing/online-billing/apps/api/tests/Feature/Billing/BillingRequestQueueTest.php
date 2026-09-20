<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRequest;
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

        // 4. Repeated call is idempotent without creating errors or multiple entries
        $readyRes2 = $this->actingAs($this->teller1)->postJson("/api/v1/teller/billing-requests/{$req->id}/mark-bill-ready", [
            'invoice_id' => $postedInvoice->id,
        ]);
        $readyRes2->assertStatus(200);
        $this->assertEquals(BillingRequest::STATUS_BILL_READY, $req->fresh()->status);
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
}
