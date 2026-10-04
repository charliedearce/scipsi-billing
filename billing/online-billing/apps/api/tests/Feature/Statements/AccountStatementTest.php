<?php

namespace Tests\Feature\Statements;

use App\Models\AccountStatement;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = Customer::create(['organization_id' => $this->admin->organization_id, 'account_number' => 'SOA-001', 'name' => 'Statement Test Customer', 'status' => 'active', 'customer_type' => 'business']);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create(['buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Statement Test Customer, Inc.', 'tin' => '111-222-333-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR', 'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'], 'contact_email' => 'statements@example.test', 'contact_phone' => '+639171111114', 'effective_from' => now()->subDay(), 'status' => 'active']);
    }

    public function test_statement_is_an_immutable_as_of_snapshot_of_outstanding_posted_invoices(): void
    {
        $invoice = $this->postInvoice();
        $partial = bcdiv((string) $invoice->total_charge_amount, '2', 2);
        $this->postReceipt($invoice, $partial, 'SOA-PARTIAL');

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/account-statements', ['customer_id' => $this->customer->id, 'as_of_date' => now('Asia/Manila')->toDateString()])->assertCreated();
        $statement = AccountStatement::with('items')->findOrFail($response->json('data.id'));
        $this->assertSame('GENERATED', $statement->status);
        $this->assertCount(1, $statement->items);
        $this->assertSame((string) $invoice->total_charge_amount, $statement->invoice_total);
        $this->assertSame($partial, $statement->payment_total);
        $this->assertSame(bcsub((string) $invoice->total_charge_amount, $partial, 2), $statement->outstanding_total);

        $snapshot = DocumentSnapshot::where('document_type', 'ACCOUNT_STATEMENT')
            ->where('document_id', $statement->id)
            ->sole();
        $artifact = DocumentArtifact::where('document_type', 'ACCOUNT_STATEMENT')
            ->where('document_id', $statement->id)
            ->where('artifact_type', 'CANONICAL_PDF')
            ->sole();
        $this->assertSame('ACCOUNT_STATEMENT', $snapshot->document_kind);
        $this->assertSame((string) $statement->outstanding_total, data_get($snapshot->payload_snapshot, 'totals.outstanding_total'));
        $this->assertTrue((bool) data_get($snapshot->routing_metadata, 'non_fiscal'));
        $this->assertSame('RENDERED', $artifact->status);
        Storage::disk('private')->assertExists($artifact->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($artifact->file_path));
        $this->assertTrue($artifact->verifyIntegrity());
        $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/account-statements/{$statement->id}/artifact/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $originalHash = $artifact->sha256_hash;

        $this->postReceipt($invoice, bcsub((string) $invoice->total_charge_amount, $partial, 2), 'SOA-SETTLE');
        $statement->refresh();
        $this->assertSame($partial, $statement->payment_total);
        $this->assertSame(bcsub((string) $invoice->total_charge_amount, $partial, 2), $statement->outstanding_total);
        $this->assertSame($originalHash, $artifact->fresh()->sha256_hash);
        $this->assertSame((string) $statement->outstanding_total, data_get($snapshot->fresh()->payload_snapshot, 'totals.outstanding_total'));
        $this->assertDatabaseHas('audit_events', ['aggregate_type' => 'ACCOUNT_STATEMENT', 'aggregate_id' => $statement->id, 'event_type' => 'ACCOUNT_STATEMENT_GENERATED']);
    }

    public function test_statement_requires_an_outstanding_posted_invoice(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/account-statements', ['customer_id' => $this->customer->id, 'as_of_date' => now('Asia/Manila')->toDateString()])->assertStatus(422)->assertJsonValidationErrors('customer_id');
    }

    private function postInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', ['customer_id' => $this->customer->id, ...$this->invoiceShipmentPayload(), 'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]]])->assertCreated();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts/'.$draft->json('data.id').'/post', ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($draft->json('data.id'));
    }

    private function postReceipt(Invoice $invoice, string $amount, string $key): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', ['source_type' => 'MANUAL_BANK_VERIFICATION', 'source_key' => $key, 'customer_id' => $this->customer->id, 'allocations' => [['invoice_id' => $invoice->id, 'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => $amount, 'reference' => $key]]]]])->assertOk();
    }
}
