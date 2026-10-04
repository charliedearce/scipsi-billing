<?php

namespace Tests\Feature\Registration;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentType;
use App\Models\PrivateFile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->adminUser = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customerUser = User::create([
            'organization_id' => $this->adminUser->organization_id,
            'name' => 'Portal Customer',
            'email' => 'portal-customer@example.test',
            'phone' => '+639171110001',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach(Role::where('name', 'Customer')->firstOrFail());
    }

    public function test_unauthenticated_create_returns_401(): void
    {
        $this->postJson('/api/v1/admin/customers', [
            'account_number' => 'ACC-401',
            'name' => 'Unauthenticated Co',
        ])->assertStatus(401);
    }

    public function test_customer_role_create_returns_403(): void
    {
        $this->actingAs($this->customerUser)
            ->postJson('/api/v1/admin/customers', [
                'account_number' => 'ACC-403',
                'name' => 'Forbidden Co',
            ])
            ->assertStatus(403);
    }

    public function test_admin_create_requires_name(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonMissingValidationErrors(['account_number']);
    }

    public function test_non_vip_create_defaults_account_number_to_walk_in_code(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'name' => 'Cash Walk-in Buyer',
            ])
            ->assertStatus(201)
            ->assertJsonPath('customer.account_number', '11001-0000')
            ->assertJsonPath('customer.customer_type', 'business');
    }

    public function test_vip_create_rejects_walk_in_account_number(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'is_vip' => true,
                'account_number' => '11001-0000',
                'name' => 'VIP Shipping',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);
    }

    public function test_vip_create_requires_own_account_number(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'is_vip' => true,
                'name' => 'VIP Shipping',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);
    }

    public function test_vip_create_stores_unique_account_number(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'is_vip' => true,
                'account_number' => 'VIP-2001',
                'name' => 'VIP Shipping',
            ])
            ->assertStatus(201)
            ->assertJsonPath('customer.account_number', 'VIP-2001')
            ->assertJsonPath('customer.customer_type', 'vip');
    }

    public function test_admin_create_rejects_invalid_account_number(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'account_number' => 'bad account',
                'name' => 'Invalid Number Co',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);
    }

    public function test_admin_creates_customer_with_account_number_and_no_portal_link(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'account_number' => '  SCIPSI-1001  ',
                'name' => '  Andres Shipping Corp.  ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.account_number', 'SCIPSI-1001')
            ->assertJsonPath('customer.name', 'Andres Shipping Corp.')
            ->assertJsonPath('customer.status', 'active')
            ->assertJsonPath('customer.customer_type', 'business');

        $customer = Customer::where('account_number', 'SCIPSI-1001')->firstOrFail();
        $this->assertSame($this->adminUser->organization_id, $customer->organization_id);
        $this->assertCount(0, $customer->userLinks);
        $this->assertNotNull($customer->buyerProfile);
        $this->assertSame(1, $customer->buyerProfile->current_version);
        $this->assertSame('Andres Shipping Corp.', $customer->buyerProfile->versions()->first()?->registered_name);
        $this->assertSame(0, $customer->userLinks()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer.created',
            'auditable_id' => (string) $customer->id,
        ]);
    }

    public function test_admin_create_rejects_duplicate_account_number(): void
    {
        Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'SCIPSI-1001',
            'name' => 'Existing Co',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'account_number' => 'SCIPSI-1001',
                'name' => 'Duplicate Co',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);
    }

    public function test_admin_updates_account_number_without_changing_buyer_profile_version(): void
    {
        $created = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/customers', [
                'account_number' => 'SCIPSI-1001',
                'name' => 'Andres Shipping Corp.',
            ])
            ->assertStatus(201)
            ->json('customer');

        $customerId = $created['id'];
        $profileId = Customer::findOrFail($customerId)->buyerProfile?->id;

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/customers/{$customerId}", [
                'account_number' => 'SCIPSI-1001A',
                'name' => 'Andres Shipping Corp.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('customer.account_number', 'SCIPSI-1001A');

        $customer = Customer::findOrFail($customerId);
        $this->assertSame('SCIPSI-1001A', $customer->account_number);
        $this->assertSame(2, $customer->lock_version);
        $this->assertSame($profileId, $customer->buyerProfile?->id);
        $this->assertSame(1, $customer->buyerProfile?->current_version);
        $this->assertSame(1, $customer->buyerProfile?->versions()->count());
        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'customer.identity_updated')
                ->where('auditable_id', (string) $customerId)
                ->exists()
        );
    }

    public function test_customer_role_update_returns_403(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'SCIPSI-LOCK',
            'name' => 'Locked Co',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $this->actingAs($this->customerUser)
            ->putJson("/api/v1/admin/customers/{$customer->id}", [
                'account_number' => 'SCIPSI-HACK',
                'name' => 'Locked Co',
            ])
            ->assertStatus(403);

        $this->assertSame('SCIPSI-LOCK', $customer->fresh()->account_number);
    }

    public function test_admin_list_and_show_include_tax_verification_status(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'TAX-CUST-001',
            'name' => 'Tax Status Shipping',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $docType = DocumentType::query()->firstOrFail();
        $file = PrivateFile::create([
            'organization_id' => $this->adminUser->organization_id,
            'document_type_id' => $docType->id,
            'purpose' => 'WITHHOLDING_CERTIFICATE',
            'uploaded_by' => $this->adminUser->id,
            'owner_id' => $this->adminUser->id,
            'current_version' => 1,
            'status' => 'CLEAN',
        ]);

        CustomerWithholdingCertificate::create([
            'organization_id' => $this->adminUser->organization_id,
            'customer_id' => $customer->id,
            'certificate_no' => '2307-ADMIN-LIST-001',
            'private_file_id' => $file->id,
            'reviewed_version_number' => 1,
            'payor_tin' => '111-222-333-000',
            'payor_name' => 'Tax Status Shipping',
            'payee_tin' => '000-123-456-000',
            'payee_name' => 'SCIPSI',
            'period_from' => now('Asia/Manila')->startOfMonth()->toDateString(),
            'period_to' => now('Asia/Manila')->endOfMonth()->toDateString(),
            'atc_code' => 'WC100',
            'income_payment_base' => '1000.00',
            'withholding_rate' => '0.0100',
            'certified_amount' => '10.00',
            'allocated_amount' => '0.00',
            'remaining_amount' => '10.00',
            'status' => CustomerWithholdingCertificate::STATUS_PENDING_REVIEW,
            'lock_version' => 1,
        ]);

        $exemptionFile = PrivateFile::create([
            'organization_id' => $this->adminUser->organization_id,
            'document_type_id' => $docType->id,
            'purpose' => 'EXEMPTION_EVIDENCE',
            'uploaded_by' => $this->adminUser->id,
            'owner_id' => $this->adminUser->id,
            'current_version' => 1,
            'status' => 'CLEAN',
        ]);

        CustomerTaxExemption::create([
            'organization_id' => $this->adminUser->organization_id,
            'customer_id' => $customer->id,
            'exemption_type' => CustomerTaxExemption::TYPE_VAT_EXEMPT,
            'legal_basis' => 'NIRC Sec. 109',
            'ruling_or_cert_no' => 'PEZA-ADMIN-001',
            'covered_services' => ['ALL'],
            'valid_from' => now('Asia/Manila')->startOfYear()->toDateString(),
            'valid_to' => null,
            'private_file_id' => $exemptionFile->id,
            'reviewed_version_number' => 1,
            'status' => CustomerTaxExemption::STATUS_APPROVED,
            'lock_version' => 1,
        ]);

        $list = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/customers?search=Tax%20Status%20Shipping')
            ->assertOk();

        $row = collect($list->json('data'))->firstWhere('id', $customer->id);
        $this->assertNotNull($row);
        $this->assertSame('PENDING_REVIEW', $row['tax_verification']['withholding']['display_status']);
        $this->assertSame(1, $row['tax_verification']['withholding']['pending_count']);
        $this->assertSame('APPROVED', $row['tax_verification']['exemption']['display_status']);
        $this->assertArrayNotHasKey('requests', $row['tax_verification']['withholding']);

        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('customer.tax_verification.withholding.display_status', 'PENDING_REVIEW')
            ->assertJsonPath('customer.tax_verification.exemption.display_status', 'APPROVED')
            ->assertJsonPath('customer.tax_verification.withholding.requests.0.certificate_no', '2307-ADMIN-LIST-001')
            ->assertJsonPath('customer.tax_verification.withholding.requests.0.private_file_id', $file->id)
            ->assertJsonPath('customer.tax_verification.exemption.requests.0.ruling_or_cert_no', 'PEZA-ADMIN-001')
            ->assertJsonPath('customer.tax_verification.exemption.requests.0.private_file_id', $exemptionFile->id);
    }

    public function test_next_account_number_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/customers/next-account-number')->assertStatus(401);
    }

    public function test_customer_role_cannot_request_next_account_number(): void
    {
        $this->actingAs($this->customerUser)
            ->getJson('/api/v1/admin/customers/next-account-number')
            ->assertStatus(403);
    }

    public function test_next_account_number_starts_at_vip_000001(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/customers/next-account-number')
            ->assertOk()
            ->assertJsonPath('account_number', 'VIP-000001');
    }

    public function test_next_account_number_skips_existing_and_gap_holes(): void
    {
        Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'VIP-000002',
            'name' => 'VIP Two',
            'status' => 'active',
            'customer_type' => 'vip',
            'lock_version' => 1,
        ]);
        Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'VIP-000007',
            'name' => 'VIP Seven Manual',
            'status' => 'active',
            'customer_type' => 'vip',
            'lock_version' => 1,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/customers/next-account-number')
            ->assertOk()
            ->assertJsonPath('account_number', 'VIP-000001');

        Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'VIP-000001',
            'name' => 'VIP One',
            'status' => 'active',
            'customer_type' => 'vip',
            'lock_version' => 1,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/customers/next-account-number')
            ->assertOk()
            ->assertJsonPath('account_number', 'VIP-000003');
    }
}
