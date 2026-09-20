<?php

namespace Tests\Feature\Uploads;

use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PrivateFileVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class PrivateUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected User $customer1;

    protected User $customer2;

    protected Organization $org;

    protected Location $loc;

    protected DocumentType $blDocType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Storage::fake('local_private');

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->loc = Location::first();
        $this->blDocType = DocumentType::where('code', 'BILL_OF_LADING')->first();

        // Teller
        $this->teller = User::create([
            'name' => 'Reviewer Teller',
            'email' => 'teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller->roles()->attach($tellerRole->id);

        // Customer 1
        $this->customer1 = User::create([
            'name' => 'Shipping Corp 1',
            'email' => 'client1@shipping.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $customerRole = Role::where('name', 'Customer')->first();
        $this->customer1->roles()->attach($customerRole->id);

        // Customer 2
        $this->customer2 = User::create([
            'name' => 'Shipping Corp 2',
            'email' => 'client2@shipping.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customer2->roles()->attach($customerRole->id);
    }

    public function test_customer_can_upload_private_file(): void
    {
        $file = UploadedFile::fake()->create('bill_of_lading.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $file,
            'document_type_id' => $this->blDocType->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'CLEAN')
            ->assertJsonPath('current_version', 1)
            ->assertJsonPath('latest_version.original_name', 'bill_of_lading.pdf')
            ->assertJsonPath('latest_version.scan_status', 'CLEAN');

        $this->assertDatabaseHas('private_files', [
            'id' => $response->json('id'),
            'uploaded_by' => $this->customer1->id,
            'current_version' => 1,
        ]);

        $this->assertDatabaseHas('private_file_versions', [
            'private_file_id' => $response->json('id'),
            'version_number' => 1,
            'original_name' => 'bill_of_lading.pdf',
        ]);
    }

    public function test_disallowed_mime_type_or_prohibited_extension_rejected(): void
    {
        $badFile = UploadedFile::fake()->create('exploit.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $badFile,
            'document_type_id' => $this->blDocType->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_file_replacement_creates_new_version_and_preserves_history(): void
    {
        $fileV1 = UploadedFile::fake()->create('initial_doc.pdf', 300, 'application/pdf');

        $uploadRes = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $fileV1,
            'document_type_id' => $this->blDocType->id,
        ]);
        $fileId = $uploadRes->json('id');

        // Replace with v2
        $fileV2 = UploadedFile::fake()->create('corrected_doc.pdf', 400, 'application/pdf');
        $replaceRes = $this->actingAs($this->customer1)->postJson("/api/v1/files/{$fileId}/replace", [
            'file' => $fileV2,
            'reason' => 'Teller requested corrected manifest figures',
        ]);

        $replaceRes->assertStatus(201)
            ->assertJsonPath('version_number', 2)
            ->assertJsonPath('original_name', 'corrected_doc.pdf')
            ->assertJsonPath('replacement_reason', 'Teller requested corrected manifest figures');

        // Confirm both versions exist in database
        $this->assertDatabaseHas('private_file_versions', [
            'private_file_id' => $fileId,
            'version_number' => 1,
            'original_name' => 'initial_doc.pdf',
        ]);

        $this->assertDatabaseHas('private_file_versions', [
            'private_file_id' => $fileId,
            'version_number' => 2,
            'original_name' => 'corrected_doc.pdf',
        ]);

        // File record reflects current_version = 2
        $this->assertDatabaseHas('private_files', [
            'id' => $fileId,
            'current_version' => 2,
        ]);
    }

    public function test_private_file_version_is_immutable_and_cannot_be_deleted(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 250, 'application/pdf');

        $uploadRes = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $file,
            'document_type_id' => $this->blDocType->id,
        ]);

        $version = PrivateFileVersion::where('private_file_id', $uploadRes->json('id'))->first();

        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_owner_and_teller_reviewer_can_download_file(): void
    {
        $file = UploadedFile::fake()->create('manifest.pdf', 200, 'application/pdf');

        $uploadRes = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $file,
            'document_type_id' => $this->blDocType->id,
        ]);
        $fileId = $uploadRes->json('id');

        // Owner (Customer 1) can download
        $ownerResponse = $this->actingAs($this->customer1)->get("/api/v1/files/{$fileId}/download");
        $ownerResponse->assertStatus(200);

        // Staff reviewer (Teller) can download
        $tellerResponse = $this->actingAs($this->teller)->get("/api/v1/files/{$fileId}/download");
        $tellerResponse->assertStatus(200);
    }

    public function test_unauthorized_customer_cannot_download_other_customer_file(): void
    {
        $file = UploadedFile::fake()->create('secret_evidence.pdf', 200, 'application/pdf');

        $uploadRes = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $file,
            'document_type_id' => $this->blDocType->id,
        ]);
        $fileId = $uploadRes->json('id');

        // Customer 2 attempts download (forbidden)
        $unauthResponse = $this->actingAs($this->customer2)->get("/api/v1/files/{$fileId}/download");
        $unauthResponse->assertStatus(403);
    }

    public function test_quarantined_file_cannot_be_downloaded(): void
    {
        // Create file containing mock test malware signature
        $malwareSignature = 'SAMPLE_TEST_CONTENT_WITH_MALWARE_TEST_SIGNATURE_FOUND_FOR_SCANNING';
        $maliciousFile = UploadedFile::fake()->createWithContent('malware_sample.pdf', $malwareSignature);

        $uploadRes = $this->actingAs($this->customer1)->postJson('/api/v1/files/upload', [
            'file' => $maliciousFile,
            'document_type_id' => $this->blDocType->id,
        ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('status', 'QUARANTINED')
            ->assertJsonPath('latest_version.scan_status', 'QUARANTINED');

        $fileId = $uploadRes->json('id');

        // Download attempt should be blocked with 422
        $downloadResponse = $this->actingAs($this->customer1)->get("/api/v1/files/{$fileId}/download");
        $downloadResponse->assertStatus(422);
    }
}
