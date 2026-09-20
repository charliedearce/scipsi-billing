<?php

namespace Tests\Feature\Audit;

use App\Exceptions\ConcurrencyException;
use App\Models\DocumentCorrectionLink;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\DocumentRevisionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DocumentRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected User $customer;

    protected Organization $org;

    protected Location $loc;

    protected DocumentRevisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->loc = Location::first();
        $this->service = app(DocumentRevisionService::class);

        // Create teller user
        $this->teller = User::create([
            'name' => 'Test Teller',
            'email' => 'teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller->roles()->attach($tellerRole->id);

        // Create customer user
        $this->customer = User::create([
            'name' => 'Port Client User',
            'email' => 'client@shipping.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $customerRole = Role::where('name', 'Customer')->first();
        $this->customer->roles()->attach($customerRole->id);
    }

    public function test_can_create_document_revisions_with_monotonic_numbering_and_sha256_hash(): void
    {
        $rev1 = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            101,
            $this->teller,
            [
                'customer_name' => 'Pacific Shipping Corp',
                'amount' => 12500.50,
                'status' => 'draft',
            ],
            'Initial draft creation',
            1
        );

        $this->assertEquals(1, $rev1->revision_number);
        $this->assertEquals(1, $rev1->lock_version);
        $this->assertNotEmpty($rev1->snapshot_hash);
        $this->assertTrue($this->service->verifyIntegrity($rev1));

        // Create revision 2
        $rev2 = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            101,
            $this->teller,
            [
                'customer_name' => 'Pacific Shipping Corp',
                'amount' => 14000.00,
                'status' => 'draft',
                'remarks' => 'Added berth fees',
            ],
            'Updated line items and amount',
            1 // expected version matches rev1 lock_version
        );

        $this->assertEquals(2, $rev2->revision_number);
        $this->assertEquals(2, $rev2->lock_version);
        $this->assertTrue($this->service->verifyIntegrity($rev2));
        $this->assertContains('amount', $rev2->changed_fields);
        $this->assertContains('remarks', $rev2->changed_fields);
        $this->assertNotEquals($rev1->snapshot_hash, $rev2->snapshot_hash);
    }

    public function test_stale_editor_rejected_with_concurrency_exception(): void
    {
        // Initial revision
        $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            202,
            $this->teller,
            ['amount' => 1000],
            'v1',
            1
        );

        // Editor A creates revision 2 (lock_version becomes 2)
        $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            202,
            $this->teller,
            ['amount' => 2000],
            'v2',
            1
        );

        // Editor B tries to create revision with stale expected version 1
        $this->expectException(ConcurrencyException::class);

        $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            202,
            $this->admin,
            ['amount' => 3000],
            'v3 conflict',
            1 // Stale! Current is 2
        );
    }

    public function test_revision_is_append_only_and_cannot_be_updated_or_deleted(): void
    {
        $rev = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            303,
            $this->teller,
            ['amount' => 5000],
            'initial',
            1
        );

        // Attempt update
        $updateBlocked = false;
        try {
            $rev->reason = 'Malicious update attempt';
            $rev->save();
        } catch (LogicException $e) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'DocumentRevision update should throw LogicException');

        // Attempt delete
        $deleteBlocked = false;
        try {
            $rev->delete();
        } catch (LogicException $e) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'DocumentRevision delete should throw LogicException');
    }

    public function test_sensitive_fields_are_redacted_in_snapshot(): void
    {
        $rev = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\UserProfile',
            404,
            $this->admin,
            [
                'email' => 'user@example.com',
                'password' => 'SuperSecretPass123!',
                'bearer_token' => 'plain_text_token_abc',
                'nested' => [
                    'api_key' => 'live_secret_key_xyz',
                    'normal_info' => 'visible data',
                ],
            ],
            'profile snapshot',
            1
        );

        $this->assertEquals('[REDACTED]', $rev->snapshot['password']);
        $this->assertEquals('[REDACTED]', $rev->snapshot['bearer_token']);
        $this->assertEquals('[REDACTED]', $rev->snapshot['nested']['api_key']);
        $this->assertEquals('visible data', $rev->snapshot['nested']['normal_info']);
    }

    public function test_compare_revisions_endpoint_and_diff_calculation(): void
    {
        $rev1 = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            505,
            $this->teller,
            [
                'vessel_name' => 'MV Oceanic Star',
                'berth_hours' => 24,
                'rate' => 500,
            ],
            'Initial estimate',
            1
        );

        $rev2 = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            505,
            $this->teller,
            [
                'vessel_name' => 'MV Oceanic Star',
                'berth_hours' => 36, // changed
                'rate' => 550,       // changed
            ],
            'Adjusted berth hours and rate',
            1
        );

        $response = $this->actingAs($this->admin)->getJson(
            "/api/v1/document-revisions/compare?from={$rev1->id}&to={$rev2->id}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('changed_field_count', 2)
            ->assertJsonPath('diff.berth_hours.old', 24)
            ->assertJsonPath('diff.berth_hours.new', 36)
            ->assertJsonPath('diff.rate.old', 500)
            ->assertJsonPath('diff.rate.new', 550);
    }

    public function test_cannot_compare_revisions_across_different_documents(): void
    {
        $revDoc1 = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            601,
            $this->teller,
            ['amount' => 100],
            'doc 1',
            1
        );

        $revDoc2 = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            602,
            $this->teller,
            ['amount' => 200],
            'doc 2',
            1
        );

        $response = $this->actingAs($this->admin)->getJson(
            "/api/v1/document-revisions/compare?from={$revDoc1->id}&to={$revDoc2->id}"
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_REVISION_PAIR');
    }

    public function test_document_history_permissions_guard(): void
    {
        $rev = $this->service->createRevision(
            $this->org->id,
            $this->loc->id,
            'App\Models\InvoiceDraft',
            701,
            $this->teller,
            ['amount' => 1500],
            'draft',
            1
        );

        // Teller has document_history:view
        $this->actingAs($this->teller)
            ->getJson("/api/v1/document-revisions/{$rev->id}")
            ->assertStatus(200)
            ->assertJsonPath('integrity_verified', true);

        // Customer does NOT have document_history:view
        $this->actingAs($this->customer)
            ->getJson("/api/v1/document-revisions/{$rev->id}")
            ->assertStatus(403);
    }

    public function test_document_correction_link_creation(): void
    {
        $link = DocumentCorrectionLink::create([
            'organization_id' => $this->org->id,
            'original_document_type' => 'App\Models\Invoice',
            'original_document_id' => 8001,
            'correction_document_type' => 'App\Models\InvoiceCorrection',
            'correction_document_id' => 9001,
            'correction_type' => 'CORRECTION',
            'reason' => 'Corrected VAT classification on bunker fee per accountant review',
            'approved_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('document_correction_links', [
            'id' => $link->id,
            'original_document_id' => 8001,
            'correction_type' => 'CORRECTION',
        ]);
    }
}
