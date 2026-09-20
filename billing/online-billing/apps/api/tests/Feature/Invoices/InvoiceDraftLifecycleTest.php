<?php

namespace Tests\Feature\Invoices;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentRevision;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDraftLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();

        // Setup active customer with valid buyer profile
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-DRAFT-001',
            'name' => 'Davao Ocean Transport',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Davao Ocean Transport Inc.',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Km 10 Sasa Port',
                'city' => 'Davao City',
                'province' => 'Davao del Sur',
            ],
            'effective_from' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);
    }

    public function test_can_create_invoice_draft_with_frozen_pricing_snapshots(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now()->format('Y-m-d'),
                'notes' => 'Test shipment batch A-102',
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 15,
                        'description' => 'Arrastre handling for container 15 TEU',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.is_fiscal_ready', true);

        $invoiceId = $response->json('data.id');
        $invoice = Invoice::with('items.pricingSnapshot')->find($invoiceId);

        $this->assertNotNull($invoice);
        $this->assertNull($invoice->invoice_number); // No number while in draft
        $this->assertCount(1, $invoice->items);

        $item = $invoice->items->first();
        $this->assertNotNull($item->pricingSnapshot);
        $this->assertEquals('ARR_DOM', $item->pricingSnapshot->tariff_code);
        $this->assertEquals('APPLICABLE', $item->pricingSnapshot->fuel_surcharge_applicability);
        $this->assertNotNull($item->pricingSnapshot->fuel_surcharge_percent);

        // Verify document revision recorded (Decision W35)
        $revision = DocumentRevision::where('document_type', 'INVOICE')
            ->where('document_id', $invoice->id)
            ->where('revision_number', 1)
            ->first();

        $this->assertNotNull($revision);
        $this->assertEquals(1, $revision->lock_version);
        $this->assertNotEmpty($revision->snapshot_hash);
    }

    public function test_can_update_draft_with_expected_version_concurrency(): void
    {
        // 1. Create initial draft
        $createRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 10,
                    ],
                ],
            ]);

        $createRes->assertStatus(201);
        $invoiceId = $createRes->json('data.id');

        // 2. Update draft with matching expected_version = 1
        $updateRes = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/invoices/drafts/{$invoiceId}", [
                'expected_version' => 1,
                'reason' => 'Encoder adjusted quantity from 10 to 12',
                'notes' => 'Updated quantity',
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 12,
                    ],
                ],
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.lock_version', 2);

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(2, $invoice->lock_version);

        // Verify revision 2 recorded in document_revisions
        $rev2 = DocumentRevision::where('document_type', 'INVOICE')
            ->where('document_id', $invoiceId)
            ->where('revision_number', 2)
            ->first();

        $this->assertNotNull($rev2);
        $this->assertEquals(2, $rev2->lock_version);
        $this->assertEquals('Encoder adjusted quantity from 10 to 12', $rev2->reason);

        // 3. Attempt stale update with expected_version = 1 -> should trigger 409 Conflict
        $staleRes = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/invoices/drafts/{$invoiceId}", [
                'expected_version' => 1,
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 15,
                    ],
                ],
            ]);

        $staleRes->assertStatus(409);
    }

    public function test_can_view_draft_details_and_list(): void
    {
        $createRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 5],
                ],
            ]);

        $invoiceId = $createRes->json('data.id');

        // Show single draft
        $showRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/invoices/drafts/{$invoiceId}");

        $showRes->assertStatus(200)
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('data.customer.name', 'Davao Ocean Transport');

        // List drafts
        $listRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/invoices/drafts');

        $listRes->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_unauthorized_user_cannot_create_draft(): void
    {
        $unauthorizedUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Restricted Viewer',
            'email' => 'viewer@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($unauthorizedUser, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(403);
    }
}
