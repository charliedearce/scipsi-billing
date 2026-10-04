<?php

namespace Tests\Feature\Invoices;

use App\Models\AuditEvent;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentNumber;
use App\Models\DocumentRevision;
use App\Models\DocumentSeries;
use App\Models\Invoice;
use App\Models\InvoiceOutbox;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePostingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Customer $customer;

    protected CustomerBuyerProfile $buyerProfile;

    protected BuyerProfileVersion $profileVersion;

    protected DocumentSeries $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->series = DocumentSeries::where('series_code', 'SI-GENSAN-2026')->first();

        // Setup active customer with complete valid buyer profile
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-POST-001',
            'name' => 'General Tuna Corporation',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $this->buyerProfile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        $this->profileVersion = BuyerProfileVersion::create([
            'buyer_profile_id' => $this->buyerProfile->id,
            'version' => 1,
            'registered_name' => 'General Tuna Corporation',
            'trade_name' => 'GenTuna Phil',
            'tin' => '000-123-456-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Fishport Complex, Tambler',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'contact_email' => 'accounting@gentuna.test',
            'contact_phone' => '+639171234567',
            'effective_from' => Carbon::now()->subDays(10),
            'status' => 'active',
        ]);
    }

    public function test_can_post_valid_draft_invoice_and_receives_unique_number(): void
    {
        // 1. Create a draft invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);

        $draftRes->assertStatus(201);
        $invoiceId = $draftRes->json('data.id');

        // 2. Post the draft invoice
        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
                'series_id' => $this->series->id,
            ]);

        $postRes->assertStatus(200)
            ->assertJsonPath('data.status', 'POSTED')
            ->assertJsonPath('data.invoice_number', 'SI-0000000001')
            ->assertJsonPath('data.lock_version', 2)
            ->assertJsonPath('data.buyer_snapshot.tin', '000-123-456-000')
            ->assertJsonPath('data.buyer_snapshot.name', 'General Tuna Corporation')
            ->assertJsonPath('data.buyer_snapshot.trade_name', 'GenTuna Phil')
            ->assertJsonPath('data.buyer_snapshot.branch_code', '00000');

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals('POSTED', $invoice->status);
        $this->assertEquals('SI-0000000001', $invoice->invoice_number);
        $this->assertNotNull($invoice->posted_at);
        $this->assertEquals($this->admin->id, $invoice->posted_by_user_id);

        // Verify DocumentNumber created and marked ISSUED
        $docNum = DocumentNumber::where('series_id', $this->series->id)
            ->where('sequence_number', 1)
            ->first();

        $this->assertNotNull($docNum);
        $this->assertEquals('ISSUED', $docNum->status);
        $this->assertEquals($invoice->id, $docNum->document_id);

        // Verify DocumentRevision recorded (Decision W35)
        $revision = DocumentRevision::where('document_type', 'INVOICE')
            ->where('document_id', $invoice->id)
            ->where('revision_number', 2)
            ->first();

        $this->assertNotNull($revision);
        $this->assertEquals(2, $revision->lock_version);
        $this->assertStringContainsString('SI-0000000001', $revision->reason);

        // Verify AuditEvent recorded
        $audit = AuditEvent::where('aggregate_type', 'INVOICE')
            ->where('aggregate_id', $invoice->id)
            ->where('event_type', 'INVOICE_POSTED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('billing:post', $audit->permission_snapshot);

        // Verify InvoiceOutbox created
        $outbox = InvoiceOutbox::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($outbox);
        $this->assertEquals('PENDING', $outbox->status);
        $this->assertEquals('SI-0000000001', $outbox->payload['invoice_number']);
    }

    public function test_immutable_buyer_snapshot_is_preserved_after_customer_profile_update(): void
    {
        // 1. Post initial invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 5],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals('General Tuna Corporation', $invoice->buyer_snapshot['name']);
        $this->assertEquals('000-123-456-000', $invoice->buyer_snapshot['tin']);

        // 2. Customer updates buyer profile to a new version with different name/TIN
        $this->profileVersion->update(['status' => 'superseded']);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $this->buyerProfile->id,
            'version' => 2,
            'registered_name' => 'Allied Tuna Industries Inc.', // Changed name
            'trade_name' => 'Allied Tuna',
            'tin' => '999-888-777-000', // Changed TIN
            'branch_code' => '00001',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'New Wharf Road',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'effective_from' => Carbon::now(),
            'status' => 'active',
        ]);

        $this->buyerProfile->update(['current_version' => 2]);

        // 3. Reload invoice: buyer snapshot must remain 100% frozen with original issuance values!
        $freshInvoice = Invoice::find($invoiceId);
        $this->assertEquals('General Tuna Corporation', $freshInvoice->buyer_snapshot['name']);
        $this->assertEquals('000-123-456-000', $freshInvoice->buyer_snapshot['tin']);
        $this->assertEquals('GenTuna Phil', $freshInvoice->buyer_snapshot['trade_name']);
        $this->assertEquals('00000', $freshInvoice->buyer_snapshot['branch_code']);
    }

    public function test_posting_allows_blank_optional_buyer_tin(): void
    {
        $incompleteCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-NO-TIN',
            'name' => 'No TIN Logistics',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $incompleteCustomer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'No TIN Logistics',
            'tin' => null,
            'branch_code' => '00000',
            'billing_address' => null,
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $incompleteCustomer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $postRes->assertStatus(200);
        $invoice = Invoice::find($invoiceId);
        $this->assertEquals('POSTED', $invoice->status);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNull($invoice->buyer_snapshot_tin);
        $this->assertEquals('00000', $invoice->buyer_snapshot_branch_code);
    }

    public function test_posting_fails_if_buyer_registered_name_missing(): void
    {
        $incompleteCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-NO-NAME',
            'name' => 'Nameless Buyer',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $incompleteCustomer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => '',
            'tin' => null,
            'branch_code' => '00000',
            'billing_address' => null,
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $incompleteCustomer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $postRes->assertStatus(422)
            ->assertJsonValidationErrors(['fiscal_readiness']);

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals('DRAFT', $invoice->status);
        $this->assertNull($invoice->invoice_number);
    }

    public function test_cannot_post_already_posted_invoice(): void
    {
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        // First post succeeds
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        // Second post on same invoice fails
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_bounded_series_exhaustion_rejects_allocation(): void
    {
        // Create a bounded series that can only issue 1 number
        $boundedSeries = DocumentSeries::create([
            'organization_id' => $this->org->id,
            'document_type' => 'SALES_INVOICE',
            'series_code' => 'SI-EXHAUST-TEST',
            'prefix' => 'SI-EX-',
            'current_number' => 1,
            'start_number' => 1,
            'end_number' => 1, // Max is 1
            'padding_length' => 4,
            'is_active' => true,
        ]);

        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        // Attempting to allocate from exhausted series fails
        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
                'series_id' => $boundedSeries->id,
            ]);

        $postRes->assertStatus(422)
            ->assertJsonValidationErrors(['series']);
    }

    public function test_posting_is_idempotent_with_idempotency_key(): void
    {
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 8],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');
        $idempotencyKey = 'POST-INV-'.uniqid();

        // Request 1
        $res1 = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $res1->assertStatus(200);
        $invoiceNumber1 = $res1->json('data.invoice_number');

        // Request 2 with same idempotency key (network retry simulation)
        $res2 = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $res2->assertStatus(200);
        $invoiceNumber2 = $res2->json('data.invoice_number');

        // Replayed request must return the exact same invoice number without double-incrementing sequence
        $this->assertEquals($invoiceNumber1, $invoiceNumber2);
        $this->assertEquals(1, DocumentNumber::where('document_id', $invoiceId)->count());
    }

    public function test_unauthorized_user_cannot_post_invoice(): void
    {
        $unauthorizedUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'ReadOnly Encoder',
            'email' => 'encoder@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);

        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($unauthorizedUser, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(403);
    }

    public function test_can_list_active_document_series(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/document-series');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'document_type',
                        'series_code',
                        'prefix',
                        'current_number',
                        'start_number',
                        'padding_length',
                        'is_active',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data'));
    }
}
