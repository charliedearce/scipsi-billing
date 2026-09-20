<?php

namespace Tests\Feature\Uploads;

use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected User $customer;

    protected Organization $org;

    protected Location $loc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->loc = Location::first();

        // Create teller
        $this->teller = User::create([
            'name' => 'Teller User',
            'email' => 'teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller->roles()->attach($tellerRole->id);

        // Create customer
        $this->customer = User::create([
            'name' => 'Port Client',
            'email' => 'client@shipping.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $customerRole = Role::where('name', 'Customer')->first();
        $this->customer->roles()->attach($customerRole->id);
    }

    public function test_default_document_types_and_requirements_are_seeded(): void
    {
        $this->assertDatabaseHas('document_types', [
            'code' => 'BILL_OF_LADING',
            'purpose' => 'BILLING_SUPPORT',
        ]);
        $this->assertDatabaseHas('document_types', [
            'code' => 'BIR_2307',
            'purpose' => 'WITHHOLDING_CERTIFICATE',
        ]);
        $this->assertDatabaseHas('document_types', [
            'code' => 'TAX_EXEMPTION_CERT',
            'purpose' => 'EXEMPTION_EVIDENCE',
        ]);
        $this->assertDatabaseHas('document_types', [
            'code' => 'BANK_DEPOSIT_SLIP',
            'purpose' => 'PAYMENT_PROOF',
        ]);

        $this->assertDatabaseHas('document_requirements', [
            'service_type' => 'GENERAL',
            'is_required' => true,
        ]);
        $this->assertDatabaseHas('document_requirements', [
            'service_type' => 'CARGO_HANDLING',
            'is_required' => true,
        ]);
    }

    public function test_can_list_document_types_filtered_by_purpose(): void
    {
        $response = $this->actingAs($this->teller)
            ->getJson('/api/v1/document-types?purpose=WITHHOLDING_CERTIFICATE');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'BIR_2307');
    }

    public function test_admin_can_create_and_update_document_type(): void
    {
        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/document-types', [
            'code' => 'CUSTOMS_PERMIT',
            'name' => 'Bureau of Customs Clearance Permit',
            'description' => 'Required for import cargo discharge',
            'purpose' => 'BILLING_SUPPORT',
            'allowed_mime_types' => ['application/pdf'],
            'max_file_size_kb' => 10240,
            'max_files' => 1,
            'is_active' => true,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('code', 'CUSTOMS_PERMIT')
            ->assertJsonPath('purpose', 'BILLING_SUPPORT');

        $typeId = $createResponse->json('id');

        $updateResponse = $this->actingAs($this->admin)->putJson("/api/v1/document-types/{$typeId}", [
            'max_file_size_kb' => 20480,
            'description' => 'Updated description with expanded size limit',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('max_file_size_kb', 20480);
    }

    public function test_admin_can_manage_service_document_requirements_with_optimistic_locking(): void
    {
        $docType = DocumentType::where('code', 'BIR_2307')->first();

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/document-requirements', [
            'service_type' => 'SPECIAL_DISCHARGE',
            'document_type_id' => $docType->id,
            'is_required' => true,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('service_type', 'SPECIAL_DISCHARGE')
            ->assertJsonPath('lock_version', 1);

        $reqId = $createResponse->json('id');

        // Update with correct lock_version
        $updateResponse = $this->actingAs($this->admin)->putJson("/api/v1/document-requirements/{$reqId}", [
            'is_required' => false,
            'lock_version' => 1,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('is_required', false)
            ->assertJsonPath('lock_version', 2);

        // Stale update attempt (expect 409 Concurrency Conflict)
        $staleResponse = $this->actingAs($this->admin)->putJson("/api/v1/document-requirements/{$reqId}", [
            'is_required' => true,
            'lock_version' => 1, // Stale!
        ]);

        $staleResponse->assertStatus(409)
            ->assertJsonPath('error.code', 'CONCURRENCY_CONFLICT');
    }

    public function test_customer_forbidden_from_managing_document_requirements(): void
    {
        $response = $this->actingAs($this->customer)->postJson('/api/v1/document-types', [
            'code' => 'FAKE_DOC',
            'name' => 'Fake',
            'purpose' => 'BILLING_SUPPORT',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }
}
