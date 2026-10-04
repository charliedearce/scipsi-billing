<?php

namespace Tests\Feature\WalkIn;

use App\Models\BillClaimRequest;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Models\WalkInCustomer;
use App\Services\Billing\BillClaimService;
use App\Services\Billing\WalkInBillingService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P2-09: Walk-in Billing and Verified Portal Invoice Claims
 *
 * Tests the full walk-in billing workflow (teller creates invoice at counter)
 * and the portal invoice-number claim workflow (registered user links a billing
 * number to their account via secure claim-code or teller-review path).
 */
class WalkInBillingAndClaimsTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Location $location;

    protected User $admin;

    protected User $teller;

    protected User $portalUser;

    protected Customer $portalCustomer;

    protected WalkInBillingService $walkInService;

    protected BillClaimService $claimService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->location = Location::first();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();

        $tellerRole = Role::where('name', 'Teller')->first();
        $customerRole = Role::where('name', 'Customer')->first();

        // Create a teller user
        $this->teller = User::create([
            'name' => 'Walk-in Teller',
            'email' => 'walkin_teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->teller->roles()->attach($tellerRole->id);
        $this->teller->locations()->attach($this->location->id, ['is_primary' => true]);

        // Create a portal user with a customer account
        $this->portalCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'CUST-PORTAL-001',
            'name' => 'Verified Portal Customer',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);
        $this->portalUser = User::create([
            'name' => 'Portal User',
            'email' => 'portal_user@customer.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->portalUser->roles()->attach($customerRole->id);
        CustomerUserLink::create([
            'customer_id' => $this->portalCustomer->id,
            'user_id' => $this->portalUser->id,
            'authority_role' => 'BILLING_OFFICER',
            'is_active' => true,
            'linked_at' => Carbon::now(),
        ]);

        $this->walkInService = app(WalkInBillingService::class);
        $this->claimService = app(BillClaimService::class);
    }

    // =========================================================================
    // Walk-in Customer Creation
    // =========================================================================

    /** @test */
    public function test_teller_can_create_walk_in_customer_record(): void
    {
        $walkIn = $this->walkInService->createWalkInCustomer(
            teller: $this->teller,
            organizationId: $this->org->id,
            locationId: $this->location->id,
            buyerData: [
                'buyer_name' => 'Juan dela Cruz',
                'buyer_tin' => '123-456-789-000',
                'buyer_address' => 'P. Sanchez St., Poblacion, GenSan',
                'contact_mobile' => '+63912-000-0001',
            ]
        );

        $this->assertInstanceOf(WalkInCustomer::class, $walkIn);
        $this->assertEquals('Juan dela Cruz', $walkIn->buyer_name);
        $this->assertEquals($this->teller->id, $walkIn->created_by_user_id);

        // A shell Customer record should have been created and stored in shell_customer_id
        $this->assertNotNull($walkIn->shell_customer_id);
        // customer_id stays NULL until a portal user claims the invoice
        $this->assertNull($walkIn->customer_id);
        $this->assertDatabaseHas('customers', [
            'id' => $walkIn->shell_customer_id,
            'customer_type' => 'WALK_IN',
        ]);

        // A BuyerProfile and BuyerProfileVersion should exist
        $customer = Customer::find($walkIn->shell_customer_id);
        $this->assertNotNull($customer->buyerProfile);
        $profile = $customer->buyerProfile;
        $this->assertNotNull($profile->latestVersion()->first());
        $this->assertEquals('Juan dela Cruz', $profile->latestVersion()->first()->registered_name);
    }

    /** @test */
    public function test_walk_in_customer_requires_buyer_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->walkInService->createWalkInCustomer(
            teller: $this->teller,
            organizationId: $this->org->id,
            locationId: $this->location->id,
            buyerData: ['buyer_name' => '   ']
        );
    }

    /** @test */
    public function test_walk_in_customer_can_be_created_with_name_only(): void
    {
        // Mirrors the quick-create Vue payload: optional keys omitted entirely.
        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/teller/walk-in/customers', [
                'location_id' => $this->location->id,
                'buyer_name' => 'Counter Guest',
            ]);

        $response->assertCreated()
            ->assertJsonPath('buyer_name', 'Counter Guest')
            ->assertJsonPath('buyer_tin', null)
            ->assertJsonPath('buyer_address', null);

        $walkIn = WalkInCustomer::findOrFail($response->json('id'));
        $this->assertNull($walkIn->buyer_tin);
        $this->assertNull($walkIn->buyer_address);

        $version = Customer::find($walkIn->shell_customer_id)
            ->buyerProfile
            ->latestVersion()
            ->first();
        $this->assertEquals('Counter Guest', $version->registered_name);
        $this->assertNull($version->tin);
        $this->assertNull($version->billing_address);
    }

    /** @test */
    public function test_walk_in_incomplete_tin_can_be_cleared_before_post(): void
    {
        $walkIn = $this->createWalkIn([
            'buyer_name' => 'Partial TIN Buyer',
            'buyer_tin' => null,
        ]);

        // Simulate a legacy/bad capture that already has a short TIN on disk.
        $walkIn->update(['buyer_tin' => '123123']);
        Customer::find($walkIn->shell_customer_id)
            ->buyerProfile
            ->latestVersion()
            ->first()
            ->update(['tin' => '123123']);

        $response = $this->actingAs($this->teller, 'sanctum')
            ->patchJson("/api/v1/teller/walk-in/customers/{$walkIn->id}", [
                'buyer_tin' => null,
            ]);

        $response->assertOk()->assertJsonPath('buyer_tin', null);

        $walkIn->refresh();
        $this->assertNull($walkIn->buyer_tin);
        $version = Customer::find($walkIn->shell_customer_id)
            ->buyerProfile
            ->latestVersion()
            ->first();
        $this->assertNull($version->tin);
    }

    /** @test */
    public function test_walk_in_create_rejects_incomplete_tin(): void
    {
        $this->expectException(ValidationException::class);

        $this->walkInService->createWalkInCustomer(
            teller: $this->teller,
            organizationId: $this->org->id,
            locationId: $this->location->id,
            buyerData: [
                'buyer_name' => 'Bad TIN',
                'buyer_tin' => '123123',
            ],
        );
    }

    // =========================================================================
    // Walk-in Invoice Draft Creation
    // =========================================================================

    /** @test */
    public function test_walk_in_customer_creates_invoice_draft_with_supplied_buyer_fields(): void
    {
        $walkIn = $this->createWalkIn([
            'buyer_name' => 'Maria Santos',
            'buyer_tin' => '999-888-777-000',
        ]);

        $invoice = $this->walkInService->createWalkInInvoiceDraft(
            teller: $this->teller,
            walkIn: $walkIn,
            businessDate: Carbon::today()->format('Y-m-d'),
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('DRAFT', $invoice->status);
        $this->assertEquals($walkIn->id, $invoice->walk_in_customer_id);
        // Invoice customer_id should point to the shell customer
        $this->assertEquals($walkIn->shell_customer_id, $invoice->customer_id);

        // The walk-in customer is linked via walk_in_customer_id FK
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'walk_in_customer_id' => $walkIn->id,
            'status' => 'DRAFT',
        ]);
    }

    /** @test */
    public function test_walk_in_invoice_can_post_without_buyer_tin(): void
    {
        $walkIn = $this->createWalkIn([
            'buyer_name' => 'No TIN Buyer',
            'buyer_tin' => null,
        ]);

        $invoice = $this->walkInService->createWalkInInvoiceDraft(
            teller: $this->teller,
            walkIn: $walkIn,
            businessDate: Carbon::today('Asia/Manila')->format('Y-m-d'),
        );

        $this->assertEquals('DRAFT', $invoice->status);

        $customer = Customer::find($walkIn->shell_customer_id);
        $version = $customer->buyerProfile->latestVersion()->first();
        $this->assertNull($version->tin);

        $this->actingAs($this->teller, 'sanctum')
            ->putJson("/api/v1/invoices/drafts/{$invoice->id}", [
                'expected_version' => 1,
                'business_date' => Carbon::today('Asia/Manila')->format('Y-m-d'),
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 2],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoice->id}/post", [
                'expected_version' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'POSTED');

        $this->assertEquals('POSTED', $invoice->fresh()->status);
    }

    // =========================================================================
    // Portal Claim Initiation (Security-focused)
    // =========================================================================

    /** @test */
    public function test_portal_user_can_initiate_invoice_number_claim(): void
    {
        // Create a walk-in invoice with a contact matching the portal user's verified contact
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0001'
        );

        // Give the portal user a verified contact with the same mobile
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0001');

        $claim = $this->claimService->initiateClaim(
            requester: $this->portalUser,
            customer: $this->portalCustomer,
            invoiceNumber: $invoice->invoice_number,
        );

        $this->assertInstanceOf(BillClaimRequest::class, $claim);
        $this->assertEquals(BillClaimRequest::STATUS_PENDING_VERIFICATION, $claim->claim_status);
        $this->assertEquals(BillClaimRequest::ROUTE_CLAIM_CODE, $claim->verification_route);
        $this->assertNotNull($claim->code_expires_at);
        // Confirm the raw code is NOT exposed in serialized array/JSON
        $this->assertArrayNotHasKey('code_hash', $claim->toArray());
        $this->assertArrayNotHasKey('code_salt', $claim->toArray());
    }

    /** @test */
    public function test_claim_initiation_is_generic_for_nonexistent_invoice_numbers(): void
    {
        // For a non-existent invoice number, the system should still create a claim record
        // (routing to TELLER_REVIEW) without leaking that the number doesn't exist.
        $claim = $this->claimService->initiateClaim(
            requester: $this->portalUser,
            customer: $this->portalCustomer,
            invoiceNumber: 'INV-FAKE-99999',
        );

        $this->assertInstanceOf(BillClaimRequest::class, $claim);
        // invoice_id is null but no exception was thrown
        $this->assertNull($claim->invoice_id);
        // Should route to teller review since there's no invoice to match contact against
        $this->assertEquals(BillClaimRequest::STATUS_PENDING_TELLER_REVIEW, $claim->claim_status);
    }

    /** @test */
    public function test_claim_code_is_issued_via_sms_intent_on_matching_contact(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0002'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0002');

        $claim = $this->claimService->initiateClaim(
            requester: $this->portalUser,
            customer: $this->portalCustomer,
            invoiceNumber: $invoice->invoice_number,
        );

        $this->assertEquals(BillClaimRequest::ROUTE_CLAIM_CODE, $claim->verification_route);

        // A CODE_ISSUED event should be in the audit trail
        $events = $claim->events->pluck('event_type')->all();
        $this->assertContains('CODE_ISSUED', $events);
        $this->assertContains('CLAIM_INITIATED', $events);
    }

    // =========================================================================
    // Claim Code Verification
    // =========================================================================

    /** @test */
    public function test_correct_claim_code_moves_to_pending_acceptance_then_accept_links_invoice(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0003'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0003');

        $claim = $this->claimService->initiateClaim(
            requester: $this->portalUser,
            customer: $this->portalCustomer,
            invoiceNumber: $invoice->invoice_number,
        );

        $rawClaim = BillClaimRequest::find($claim->id);
        $rawCode = $this->extractRawCode($rawClaim);

        $verified = $this->claimService->verifyClaimCode(
            requester: $this->portalUser,
            claim: $rawClaim,
            rawCode: $rawCode,
        );

        $this->assertEquals(BillClaimRequest::STATUS_PENDING_CUSTOMER_ACCEPTANCE, $verified->claim_status);
        $this->assertEquals($invoice->id, $verified->invoice_id);
        $this->assertNull($verified->resolved_at);

        $preview = $this->claimService->previewClaim($this->portalUser, $verified);
        $this->assertTrue($preview['can_accept']);
        $this->assertEquals($invoice->invoice_number, $preview['invoice']['invoice_number']);

        // Not yet on My Bills before accept.
        $billsBefore = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson('/api/v1/portal/bills?customer_id='.$this->portalCustomer->id)
            ->assertOk()
            ->json('data');
        $this->assertFalse(
            collect($billsBefore)->contains(fn ($bill) => ($bill['invoice_number'] ?? null) === $invoice->invoice_number),
            'Invoice must not appear on My Bills before customer accept.'
        );

        $approved = $this->claimService->acceptClaim($this->portalUser, $verified->fresh());

        $this->assertEquals(BillClaimRequest::STATUS_APPROVED, $approved->claim_status);
        $this->assertNotNull($approved->resolved_at);

        $walkIn->refresh();
        $this->assertEquals($this->portalCustomer->id, $walkIn->customer_id);
        $this->assertNotNull($walkIn->linked_at);

        $bills = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson('/api/v1/portal/bills?customer_id='.$this->portalCustomer->id)
            ->assertOk()
            ->json('data');
        $this->assertTrue(
            collect($bills)->contains(fn ($bill) => ($bill['invoice_number'] ?? null) === $invoice->invoice_number),
            'Claimed walk-in invoice should appear on portal My Bills after accept.'
        );

        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/teller/bill-claims?status=APPROVED')
            ->assertOk()
            ->assertJsonFragment(['invoice_number' => $invoice->invoice_number]);

        // Claimed walk-in invoices keep shell customer_id; payment instructions must still allow them.
        $invoice->update([
            'total_charge_amount' => '250.00',
            'gross_amount' => '250.00',
            'net_amount' => '250.00',
            'currency' => 'PHP',
        ]);
        $invoice->refresh();

        $this->assertNotEquals(
            $this->portalCustomer->id,
            $invoice->customer_id,
            'Regression guard: claimed walk-in invoice must keep shell customer_id.'
        );

        $group = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/portal/payment-groups/manual-instruction', [
                'customer_id' => $this->portalCustomer->id,
                'allocations' => [[
                    'invoice_id' => $invoice->id,
                    'expected_invoice_lock_version' => $invoice->lock_version,
                    'requested_amount' => '250.00',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'MANUAL_INSTRUCTION_ISSUED')
            ->json('data');

        $proofType = DocumentType::where('code', 'BANK_DEPOSIT_SLIP')->firstOrFail();
        $proof = PrivateFile::create([
            'organization_id' => $this->org->id,
            'document_type_id' => $proofType->id,
            'purpose' => 'PAYMENT_PROOF',
            'uploaded_by' => $this->portalUser->id,
            'owner_id' => $this->portalUser->id,
            'current_version' => 1,
            'status' => 'CLEAN',
        ]);
        PrivateFileVersion::create([
            'private_file_id' => $proof->id,
            'version_number' => 1,
            'disk' => 'private',
            'file_path' => "tests/payment-proofs/{$proof->id}-1.pdf",
            'original_name' => 'walk-in-payment-proof.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 128,
            'sha256_checksum' => hash('sha256', 'walk-in-payment-proof'),
            'scan_status' => 'CLEAN',
            'scan_details' => ['scanner' => 'test'],
            'uploaded_by' => $this->portalUser->id,
            'created_at' => now(),
        ]);

        $submission = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/portal/payment-submissions', [
                'payment_group_id' => $group['id'],
                'proof_file_id' => $proof->id,
                'declared_reference' => 'WALKIN-BANK-'.$invoice->id,
            ])
            ->assertCreated()
            ->json('data');

        $claimed = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()
            ->json('data');
        $this->assertSame($submission['id'], $claimed['id']);

        $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/teller/payment-submissions/{$claimed['id']}/approve", [
                'expected_version' => $claimed['lock_version'],
                'confirmed_reference' => 'WALKIN-CONFIRMED-'.$invoice->id,
                'allocations' => [[
                    'invoice_id' => $invoice->id,
                    'cash_amount' => '250.00',
                    'withholding_applications' => [],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('receipts', [
            'customer_id' => $this->portalCustomer->id,
            'status' => 'POSTED',
        ]);
        $this->assertTrue(
            Receipt::where('customer_id', $this->portalCustomer->id)
                ->whereHas('allocations', fn ($query) => $query->where('invoice_id', $invoice->id))
                ->exists(),
            'Teller approve must post a collection receipt against the claimed walk-in invoice.'
        );
    }

    /** @test */
    public function test_customer_can_decline_pending_acceptance_without_linking(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0033'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0033');

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );
        $verified = $this->claimService->verifyClaimCode(
            $this->portalUser,
            $claim,
            $this->extractRawCode($claim)
        );

        $declined = $this->claimService->declineClaim($this->portalUser, $verified, 'Wrong buyer name');

        $this->assertEquals(BillClaimRequest::STATUS_CANCELLED, $declined->claim_status);
        $walkIn->refresh();
        $this->assertNull($walkIn->customer_id);
    }

    /** @test */
    public function test_bill_claim_conversation_opens_with_creating_teller(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0044'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0044');

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );
        $this->claimService->verifyClaimCode($this->portalUser, $claim, $this->extractRawCode($claim));

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->postJson('/api/v1/conversations/for-bill-claim/'.$claim->id)
            ->assertOk()
            ->json('data');

        $this->assertEquals('bill_claim', $response['context_type']);
        $this->assertEquals($claim->id, $response['context_id']);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $response['id'],
            'user_id' => $this->portalUser->id,
            'role' => 'customer',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $response['id'],
            'user_id' => $this->teller->id,
            'role' => 'staff',
        ]);
    }

    /** @test */
    public function test_wrong_claim_code_increments_attempt_count(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0004'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0004');

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );
        $original = $claim->attempt_count;

        try {
            $this->claimService->verifyClaimCode($this->portalUser, $claim, '000000');
        } catch (ValidationException) {
            // Expected
        }

        $claim->refresh();
        $this->assertEquals($original + 1, $claim->attempt_count);
    }

    /** @test */
    public function test_claim_locks_out_after_max_attempts(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0005'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0005');

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );

        // Attempt max+1 times with wrong code
        for ($i = 0; $i < $claim->max_attempts; $i++) {
            try {
                $this->claimService->verifyClaimCode($this->portalUser, $claim, '000000');
            } catch (ValidationException) {
                $claim->refresh();
            }
        }

        $claim->refresh();
        $this->assertEquals(BillClaimRequest::STATUS_REJECTED, $claim->claim_status);
    }

    /** @test */
    public function test_expired_claim_code_returns_expired_status(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0006'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-000-0006');

        $claimId = $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id;
        $claim = BillClaimRequest::find($claimId);

        // Manually expire the code
        $claim->update(['code_expires_at' => Carbon::now()->subMinute()]);
        $claim->refresh();

        $this->assertTrue($claim->isExpired());

        $this->expectException(ValidationException::class);
        $this->claimService->verifyClaimCode($this->portalUser, $claim, '123456');
    }

    // =========================================================================
    // Teller Review Path
    // =========================================================================

    /** @test */
    public function test_no_verified_contact_routes_claim_to_teller_review(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-000-0007'
        );
        // Portal user has NO verified contact matching the walk-in's mobile

        $claim = $this->claimService->initiateClaim(
            requester: $this->portalUser,
            customer: $this->portalCustomer,
            invoiceNumber: $invoice->invoice_number,
        );

        $this->assertEquals(BillClaimRequest::STATUS_PENDING_TELLER_REVIEW, $claim->claim_status);
        $this->assertEquals(BillClaimRequest::ROUTE_TELLER_REVIEW, $claim->verification_route);

        $events = $claim->events->pluck('event_type')->all();
        $this->assertContains('TELLER_REVIEW_QUEUED', $events);
    }

    /** @test */
    public function test_teller_can_approve_pending_teller_review_claim(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-999-0001'
        );

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );

        $this->assertEquals(BillClaimRequest::STATUS_PENDING_TELLER_REVIEW, $claim->claim_status);

        $verified = $this->claimService->staffDecideClaim(
            staff: $this->teller,
            claim: $claim,
            decision: 'APPROVE',
            notes: 'Customer presented valid government ID.',
        );

        $this->assertEquals(BillClaimRequest::STATUS_PENDING_CUSTOMER_ACCEPTANCE, $verified->claim_status);

        $approved = $this->claimService->acceptClaim($this->portalUser, $verified->fresh());
        $this->assertEquals(BillClaimRequest::STATUS_APPROVED, $approved->claim_status);
        $this->assertEquals($this->portalUser->id, $approved->resolved_by_user_id);
    }

    /** @test */
    public function test_teller_can_reject_pending_teller_review_claim(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-999-0002'
        );

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );

        $rejected = $this->claimService->staffDecideClaim(
            staff: $this->teller,
            claim: $claim,
            decision: 'REJECT',
            notes: 'Customer could not verify identity.',
        );

        $this->assertEquals(BillClaimRequest::STATUS_REJECTED, $rejected->claim_status);
        $this->assertEquals('Customer could not verify identity.', $rejected->rejection_reason);
    }

    // =========================================================================
    // Security Invariants
    // =========================================================================

    /** @test */
    public function test_claim_does_not_grant_account_wide_access(): void
    {
        // Create TWO posted invoices for the same walk-in customer
        $walkIn = $this->createWalkIn(['buyer_name' => 'Multi-Invoice Buyer', 'contact_mobile' => '+63912-111-0001']);

        $invoice1 = $this->walkInService->createWalkInInvoiceDraft($this->teller, $walkIn, Carbon::today()->format('Y-m-d'));
        $invoice2 = $this->walkInService->createWalkInInvoiceDraft($this->teller, $walkIn, Carbon::today()->format('Y-m-d'));

        // Manually set invoice numbers and mark as POSTED for the claim lookup
        $invoice1->update(['status' => 'POSTED', 'invoice_number' => 'INV-WI-ACC-001']);
        $invoice2->update(['status' => 'POSTED', 'invoice_number' => 'INV-WI-ACC-002']);

        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-111-0001');

        // Claim only invoice1
        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, 'INV-WI-ACC-001')->id
        );
        $rawCode = $this->extractRawCode($claim);
        $verified = $this->claimService->verifyClaimCode($this->portalUser, $claim, $rawCode);
        $this->claimService->acceptClaim($this->portalUser, $verified);

        // Invoice2 must NOT be auto-linked; a separate claim is required
        $this->assertDatabaseMissing('bill_claim_requests', [
            'user_id' => $this->portalUser->id,
            'invoice_id' => $invoice2->id,
            'claim_status' => BillClaimRequest::STATUS_APPROVED,
        ]);
    }

    /** @test */
    public function test_claim_does_not_alter_issued_invoice_buyer_snapshot(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-222-0001'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-222-0001');

        // Snapshot fields on a POSTED invoice
        $invoice->update([
            'status' => 'POSTED',
            'buyer_snapshot_name' => 'Original Walk-in Buyer Name',
        ]);

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );
        $rawCode = $this->extractRawCode($claim);
        $verified = $this->claimService->verifyClaimCode($this->portalUser, $claim, $rawCode);
        $this->claimService->acceptClaim($this->portalUser, $verified);

        // After approval: the buyer snapshot on the invoice must be unchanged
        $invoice->refresh();
        $this->assertEquals('Original Walk-in Buyer Name', $invoice->buyer_snapshot_name);
    }

    /** @test */
    public function test_registration_otp_cannot_be_reused_as_claim_code(): void
    {
        [$walkIn, $invoice] = $this->createWalkInAndPostedInvoice(
            contactMobile: '+63912-333-0001'
        );
        $this->addVerifiedContact($this->portalUser, $this->portalCustomer, 'mobile', '+63912-333-0001');

        $claim = BillClaimRequest::find(
            $this->claimService->initiateClaim($this->portalUser, $this->portalCustomer, $invoice->invoice_number)->id
        );

        // A registration OTP would have a different purpose prefix (no BILL_CLAIM_ prefix).
        // Attempt to use a raw code with the wrong prefix — verification should fail.
        // (We simulate this by using the code hash from a differently prefixed hash.)
        $fakeOtpCode = '999999';
        $fakeSalt = $claim->code_salt;
        // This hash uses "REGISTRATION_" prefix, not "BILL_CLAIM_"
        $wrongPurposeHash = hash('sha256', 'REGISTRATION_'.$fakeOtpCode.$fakeSalt);

        // Override the stored hash with one from the "wrong purpose" to test cross-purpose protection
        $rawClaim = BillClaimRequest::find($claim->id);

        // The service uses constant-time comparison against SHA-256('BILL_CLAIM_' + code + salt).
        // A code hashed under a different purpose prefix will NEVER match, even if the raw digits are the same.
        $this->expectException(ValidationException::class);

        // Try submitting the raw code that was "issued" for a different purpose
        // (the service will hash it with BILL_CLAIM_ prefix, which won't match wrongPurposeHash).
        $rawClaim->update(['code_hash' => $wrongPurposeHash]);
        $this->claimService->verifyClaimCode($this->portalUser, $rawClaim, $fakeOtpCode);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createWalkIn(array $overrides = []): WalkInCustomer
    {
        return $this->walkInService->createWalkInCustomer(
            teller: $this->teller,
            organizationId: $this->org->id,
            locationId: $this->location->id,
            buyerData: array_merge([
                'buyer_name' => 'Default Walk-in Buyer',
                'buyer_tin' => '111-222-333-000',
                'buyer_address' => 'Test Street, GenSan',
                'contact_mobile' => '+63912-000-9999',
            ], $overrides),
        );
    }

    /**
     * Create a walk-in customer and a POSTED invoice for claim testing.
     *
     * @return array{WalkInCustomer, Invoice}
     */
    private function createWalkInAndPostedInvoice(string $contactMobile): array
    {
        static $counter = 0;
        $counter++;

        $walkIn = $this->createWalkIn([
            'buyer_name' => "Walk-in Buyer {$counter}",
            'contact_mobile' => $contactMobile,
        ]);
        $invoice = $this->walkInService->createWalkInInvoiceDraft(
            teller: $this->teller,
            walkIn: $walkIn,
            businessDate: Carbon::today()->format('Y-m-d'),
        );

        // Mark as POSTED with an invoice number for claim lookup
        $invoice->update([
            'status' => 'POSTED',
            'invoice_number' => 'INV-WI-TST-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
        ]);
        $invoice->refresh();

        return [$walkIn, $invoice];
    }

    /**
     * Add a verified contact point to a portal user's customer account.
     */
    private function addVerifiedContact(User $user, Customer $customer, string $type, string $value): void
    {
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'type' => $type,
            'value' => $value,
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
    }

    /**
     * Recover the raw code from a PENDING_VERIFICATION claim by reversing the hash.
     *
     * In tests, we can't get the raw code from the service return (it's hidden),
     * so we brute-force the 6-digit space against the stored hash using the known purpose prefix.
     */
    private function extractRawCode(BillClaimRequest $claim): string
    {
        $claim->refresh(); // make sure we have code_hash and code_salt
        $salt = $claim->code_salt;

        for ($i = 0; $i <= 999999; $i++) {
            $candidate = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $hash = hash('sha256', 'BILL_CLAIM_'.$candidate.$salt);
            if (hash_equals($hash, $claim->code_hash)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not recover raw code from stored hash. Test setup issue.');
    }
}
