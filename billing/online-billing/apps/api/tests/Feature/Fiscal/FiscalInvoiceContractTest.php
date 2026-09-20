<?php

namespace Tests\Feature\Fiscal;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\TaxpayerProfileVersion;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiscalInvoiceContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Location $location;

    protected Customer $customer;

    protected CustomerBuyerProfile $buyerProfile;

    protected BuyerProfileVersion $profileVersion;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->location = Location::first();

        // Seed Customer with BIR EOPT compliant buyer profile
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-FISCAL-001',
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
            'registered_name' => 'GENERAL TUNA CORPORATION',
            'trade_name' => 'GEN TUNA PHILS',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Fishport Complex, Tambler',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
                'country' => 'Philippines',
            ],
            'contact_email' => 'finance@gentuna.test',
            'contact_phone' => '+639171234567',
            'effective_from' => Carbon::now()->subDays(10),
            'status' => 'active',
        ]);
    }

    public function test_posting_invoice_captures_both_issuer_and_buyer_immutable_snapshots(): void
    {
        // 1. Create invoice draft
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        // 2. Post invoice
        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $postRes->assertStatus(200);

        $invoice = Invoice::findOrFail($invoiceId);

        // Verify Issuer Snapshot (BIR-02)
        $this->assertNotNull($invoice->taxpayer_profile_version_id);
        $this->assertEquals('SOUTH COTABATO INTEGRATED PORT SERVICES, INC.', $invoice->issuer_snapshot_name);
        $this->assertEquals('000-123-456-000', $invoice->issuer_snapshot_tin);
        $this->assertEquals('00000', $invoice->issuer_snapshot_branch_code);
        $this->assertEquals('BIR-CAS-2026-00129-GENSAN', $invoice->issuer_snapshot_permit_no);
        $this->assertEquals('VAT_REGISTERED', $invoice->issuer_snapshot_tax_classification);
        $this->assertIsArray($invoice->issuer_snapshot_address);

        // Verify Buyer Snapshot (Decision W32 / BIR-06)
        $this->assertEquals('GENERAL TUNA CORPORATION', $invoice->buyer_snapshot_name);
        $this->assertEquals('123-456-789-000', $invoice->buyer_snapshot_tin);
        $this->assertEquals('00000', $invoice->buyer_snapshot_branch_code);
        $this->assertIsArray($invoice->buyer_snapshot_address);
        $this->assertTrue($invoice->is_fiscal_ready);
    }

    public function test_structured_fiscal_data_endpoint_returns_compliant_machine_readable_json(): void
    {
        // 1. Create and post invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        // 2. Fetch structured fiscal data
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoiceId}/fiscal-data");

        $res->assertStatus(200)
            ->assertJsonPath('data.schema_version', '1.0.0')
            ->assertJsonPath('data.standard', 'BIR-EOPT-CAS-INVOICE')
            ->assertJsonPath('data.document_kind', 'SALES_INVOICE')
            ->assertJsonPath('data.issuer.tin', '000-123-456-000')
            ->assertJsonPath('data.issuer.registered_name', 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.')
            ->assertJsonPath('data.buyer.tin', '123-456-789-000')
            ->assertJsonPath('data.buyer.registered_name', 'GENERAL TUNA CORPORATION')
            ->assertJsonPath('data.canonical_artifact.exists', true)
            ->assertJsonPath('data.reconciliation.status', 'RECONCILED')
            ->assertJsonPath('data.reconciliation.is_balanced', true);

        $lineItems = $res->json('data.line_items');
        $this->assertNotEmpty($lineItems);
        $this->assertEquals('VATABLE', $lineItems[0]['tax_treatment']['key']);
        $this->assertEquals('0.1200', $lineItems[0]['tax_treatment']['vat_rate']);
    }

    public function test_mathematical_totals_reconciliation_is_balanced(): void
    {
        // Post invoice with arrastre (ARR_DOM has fuel surcharge and PPA share in seeder)
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 25],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoiceId}/fiscal-data");

        $res->assertStatus(200);
        $reconciliation = $res->json('data.reconciliation');

        $this->assertTrue($reconciliation['is_balanced']);
        $this->assertEquals('RECONCILED', $reconciliation['status']);
        $this->assertEquals('0.00', $reconciliation['charge_variance']);
        $this->assertEquals('0.00', $reconciliation['tax_variance']);
        $this->assertEquals('0.00', $reconciliation['fuel_variance']);
        $this->assertEquals('0.00', $reconciliation['ppa_variance']);
        $this->assertEquals('0.00', $reconciliation['net_variance']);
        $this->assertEquals($reconciliation['line_items_total'], $reconciliation['header_total']);
    }

    public function test_posting_fails_if_no_active_taxpayer_profile_exists(): void
    {
        // Deactivate all taxpayer profile versions for the organization
        TaxpayerProfileVersion::where('organization_id', $this->org->id)
            ->update(['is_active' => false]);

        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 5],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $postRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ]);

        $postRes->assertStatus(422)
            ->assertJsonValidationErrors('fiscal_readiness');
    }

    public function test_active_taxpayer_profile_and_tax_rules_endpoints(): void
    {
        $profileRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/fiscal/taxpayer-profile');

        $profileRes->assertStatus(200)
            ->assertJsonPath('data.tin', '000-123-456-000')
            ->assertJsonPath('data.registered_name', 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.')
            ->assertJsonPath('data.bir_permit_number', 'BIR-CAS-2026-00129-GENSAN');

        $rulesRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/fiscal/tax-rules');

        $rulesRes->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_customer_cannot_access_foreign_invoice_fiscal_data(): void
    {
        // 1. Post Dole invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $invoiceId = $draftRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
                'expected_version' => 1,
            ])
            ->assertStatus(200);

        // 2. Another Customer user
        $otherCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-OTHER-FISCAL',
            'name' => 'Foreign Cargo Inc',
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
            'registered_name' => 'Foreign Cargo Inc',
            'tin' => '999-111-222-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Port Area', 'city' => 'Gensan'],
            'effective_from' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);

        $otherUser = User::create([
            'name' => 'Other Customer User',
            'email' => 'client@foreign.test',
            'password' => bcrypt('Secret123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'phone' => '+639179998888',
            'lock_version' => 1,
        ]);
        $otherUser->assignRole('Customer');
        $otherUser->customers()->attach($otherCustomer->id, [
            'authority_role' => 'member',
            'is_active' => true,
            'linked_at' => Carbon::now(),
        ]);

        // Attempting to access General Tuna's fiscal data fails with 403
        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoiceId}/fiscal-data")
            ->assertStatus(403);
    }
}
