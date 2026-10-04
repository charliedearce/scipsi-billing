<?php

namespace Tests\Feature\Invoices;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PrintAttempt;
use App\Models\User;
use App\Services\DocumentStudio\DocumentStudioService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceIssuanceArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Location $location;

    protected Customer $customer;

    protected DocumentStudioService $studioService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->location = Location::first();
        $this->studioService = app(DocumentStudioService::class);

        // Seed customer with complete fiscal buyer profile
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-DOLE-001',
            'name' => 'Dole Philippines, Inc.',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $buyerProfile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $buyerProfile->id,
            'version' => 1,
            'registered_name' => 'DOLE PHILIPPINES, INCORPORATED',
            'trade_name' => 'DOLE PHILS',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Cannery Site',
                'city' => 'Polomolok',
                'province' => 'South Cotabato',
            ],
            'contact_email' => 'billing@dole.test',
            'contact_phone' => '+639170001111',
            'effective_from' => Carbon::now()->subDays(10),
            'status' => 'active',
        ]);
    }

    public function test_posting_invoice_automatically_generates_canonical_pdf_artifact(): void
    {
        // 1. Create invoice draft
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        // 2. Post invoice draft
        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $postRes->assertStatus(200);
        $invoiceNumber = $postRes->json('data.invoice_number');
        $this->assertNotNull($invoiceNumber);

        // 3. Verify DocumentSnapshot was created
        $snapshot = DocumentSnapshot::where('document_id', $invoiceId)
            ->where('document_type', 'INVOICE')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals('SERVICE', $snapshot->document_kind);
        $this->assertNotEmpty($snapshot->payload_snapshot);
        $this->assertEquals($invoiceNumber, $snapshot->payload_snapshot['invoice']['invoice_number']);
        $this->assertSame('HONDURAS', $snapshot->payload_snapshot['shipment']['vessel_name']);
        $this->assertSame('102', $snapshot->payload_snapshot['shipment']['voyage']);
        $this->assertSame('IN', $snapshot->payload_snapshot['shipment']['movement']);
        $this->assertSame('Domestic', $snapshot->payload_snapshot['shipment']['route']);
        $this->assertSame('Test notes 102', $snapshot->payload_snapshot['shipment']['notes']);
        $this->assertArrayHasKey('vat_amount', $snapshot->payload_snapshot['totals']);
        $this->assertArrayNotHasKey('tax_treatment', $snapshot->payload_snapshot['shipment']);

        // 4. Verify DocumentArtifact was created
        $artifact = DocumentArtifact::where('snapshot_id', $snapshot->id)->first();
        $this->assertNotNull($artifact);
        $this->assertEquals('RENDERED', $artifact->status);
        $this->assertGreaterThan(1000, $artifact->file_size_bytes);
        $this->assertNotEmpty($artifact->sha256_hash);

        // Verify physical file on fake private storage disk
        $this->assertTrue(Storage::disk('private')->exists($artifact->file_path));
        $fileContent = Storage::disk('private')->get($artifact->file_path);
        $this->assertStringStartsWith('%PDF-', (string) $fileContent);
        $this->assertEquals($artifact->sha256_hash, hash('sha256', (string) $fileContent));
    }

    public function test_route_selection_hierarchy_nscl_cargo_code(): void
    {
        // Create dedicated published NSCL template
        $nsclTemplate = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE_NSCL',
            'code' => 'SI-NSCL-LAYOUT-TEST',
            'name' => 'NSCL Layout',
        ]);
        $version = $this->studioService->createDraftVersion(
            $nsclTemplate,
            $this->admin,
            $this->studioService->getDefaultSalesInvoiceLayout()
        );
        $this->studioService->publishVersion($version, $this->admin);
        $this->studioService->activateVersion($version, $this->admin);

        // Create draft invoice with an item having cargo code 'NSCL-001'
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 5],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        // Update item with NSCL cargo code directly
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->items()->first()->update([
            'cargo_code' => 'NSCL-CONTAINER',
        ]);

        // Post invoice
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        // Check snapshot routed to SERVICE_NSCL
        $snapshot = DocumentSnapshot::where('document_id', $invoiceId)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals('SERVICE_NSCL', $snapshot->document_kind);
        $this->assertEquals($version->id, $snapshot->template_version_id);
    }

    public function test_route_selection_hierarchy_ppa_share(): void
    {
        // Create published PPA template
        $ppaTemplate = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'PPA',
            'code' => 'SI-PPA-LAYOUT-TEST',
            'name' => 'PPA Layout',
        ]);
        $version = $this->studioService->createDraftVersion(
            $ppaTemplate,
            $this->admin,
            $this->studioService->getDefaultSalesInvoiceLayout()
        );
        $this->studioService->publishVersion($version, $this->admin);
        $this->studioService->activateVersion($version, $this->admin);

        // Create draft invoice with PPA share (ARR_DOM has 10% PPA share in seeder)
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'surcharge_mode' => 'NONE',
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 20],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        $snapshot = DocumentSnapshot::where('document_id', $invoiceId)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals('PPA', $snapshot->document_kind);
        $this->assertEquals($version->id, $snapshot->template_version_id);
    }

    public function test_historical_reprint_is_immutable_even_if_active_template_changes(): void
    {
        // 1. Post invoice 1
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        $artifact1 = DocumentArtifact::where('document_id', $invoiceId)->first();
        $originalHash = $artifact1->sha256_hash;
        $this->assertNotEmpty($originalHash);

        // 2. Modify template layout: fork new version 2, change layout margin, publish and activate it
        $defaultTemplate = DocumentTemplate::where('code', 'SI-SERVICE-DEFAULT')->first();
        $version2 = $this->studioService->forkNewDraft($defaultTemplate, $this->admin);

        $layout2 = $version2->layout_definition;
        $layout2['page']['margins']['top'] = 30; // completely different margin
        $this->studioService->updateDraft($version2, $layout2);
        $this->studioService->publishVersion($version2, $this->admin);
        $this->studioService->activateVersion($version2, $this->admin);

        // 3. Download / Reprint Invoice 1 artifact
        $downloadRes = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/invoices/{$invoiceId}/artifacts/download");

        $downloadRes->assertStatus(200);
        $downloadContent = $downloadRes->streamedContent();

        // Must match original SHA-256 hash exactly (never re-rendered)
        $this->assertEquals($originalHash, hash('sha256', $downloadContent));
    }

    public function test_failed_render_does_not_abort_posting_and_can_be_retried(): void
    {
        // 1. Create a draft invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        // 2. Activate a broken template layout that throws during rendering
        $brokenTemplate = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-BROKEN-RENDER-TEST',
            'name' => 'Broken Render Test',
        ]);

        $validLayout = $this->studioService->getDefaultSalesInvoiceLayout();
        $brokenVersion = $this->studioService->createDraftVersion($brokenTemplate, $this->admin, $validLayout);
        $this->studioService->publishVersion($brokenVersion, $this->admin);

        // Now maliciously corrupt the published version's layout_definition so Dompdf will fail
        $corruptLayout = $validLayout;
        $corruptLayout['bands'] = 'not-an-array';
        $brokenVersion->update(['layout_definition' => $corruptLayout]);

        $this->studioService->activateVersion($brokenVersion, $this->admin);

        // 3. Post invoice draft
        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        // Invoice is still successfully POSTED (fiscal numbers allocated)
        $postRes->assertStatus(200);
        $this->assertEquals('POSTED', Invoice::find($invoiceId)->status);

        // Artifact is in status FAILED
        $artifact = DocumentArtifact::where('document_id', $invoiceId)->first();
        $this->assertNotNull($artifact);
        $this->assertEquals('FAILED', $artifact->status);
        $this->assertNotNull($artifact->error_message);

        // 4. Fix template layout
        $brokenVersion->update(['layout_definition' => $validLayout]);

        // 5. Authorized staff calls retry endpoint
        $retryRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/{$invoiceId}/artifacts/{$artifact->id}/retry");

        $retryRes->assertStatus(200)
            ->assertJsonPath('data.status', 'RENDERED');

        $this->assertTrue(Storage::disk('private')->exists($artifact->file_path));
        $this->assertGreaterThan(1000, $artifact->fresh()->file_size_bytes);
    }

    public function test_recording_print_attempts_and_audit_history(): void
    {
        // 1. Post invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        // 2. First print (Original)
        $print1 = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/{$invoiceId}/artifacts/print?format=json", [
                'print_type' => 'ORIGINAL',
            ]);

        $print1->assertStatus(200);
        $this->assertEquals(1, PrintAttempt::where('user_id', $this->admin->id)->count());
        $attempt1 = PrintAttempt::first();
        $this->assertEquals('ORIGINAL', $attempt1->print_type);
        $this->assertFalse($attempt1->is_reprint);

        // 3. Second print (Reprint)
        $print2 = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/{$invoiceId}/artifacts/print?format=json", [
                'reason' => 'Customer lost original invoice copy',
            ]);

        $print2->assertStatus(200);
        $this->assertEquals(2, PrintAttempt::where('user_id', $this->admin->id)->count());
        $attempt2 = PrintAttempt::orderBy('id', 'desc')->first();
        $this->assertEquals('REPRINT', $attempt2->print_type);
        $this->assertTrue($attempt2->is_reprint);
        $this->assertEquals('Customer lost original invoice copy', $attempt2->reason);
    }

    public function test_customer_can_only_download_own_invoice_artifact(): void
    {
        // Post invoice for Dole customer
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        // Another customer user
        $otherCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-OTHER-999',
            'name' => 'Other Cargo Client',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $otherBuyerProfile = CustomerBuyerProfile::create([
            'customer_id' => $otherCustomer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $otherBuyerProfile->id,
            'version' => 1,
            'registered_name' => 'Other Cargo Client Co.',
            'trade_name' => 'Other Cargo',
            'tin' => '999-888-777-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Fishport Complex',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'contact_email' => 'client@other.test',
            'contact_phone' => '+639170009999',
            'effective_from' => Carbon::now()->subDays(10),
            'status' => 'active',
        ]);

        $otherCustomerUser = User::create([
            'name' => 'Other Client User',
            'email' => 'client@other.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'phone' => '+639170009999',
            'lock_version' => 1,
        ]);
        $otherCustomerUser->assignRole('Customer');
        $otherCustomerUser->customers()->attach($otherCustomer->id, [
            'authority_role' => 'member',
            'is_active' => true,
            'linked_at' => Carbon::now(),
        ]);

        // Attempting to download Dole's invoice fails with 403 Forbidden
        $this->actingAs($otherCustomerUser, 'sanctum')
            ->get("/api/v1/invoices/{$invoiceId}/artifacts/download")
            ->assertStatus(403);
    }
}
