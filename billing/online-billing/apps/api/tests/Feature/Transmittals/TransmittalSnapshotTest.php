<?php

namespace Tests\Feature\Transmittals;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Transmittal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransmittalSnapshotTest extends TestCase
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
        $this->customer = Customer::create(['organization_id' => $this->admin->organization_id, 'account_number' => 'TRN-001', 'name' => 'Transmittal Test Customer', 'status' => 'active', 'customer_type' => 'business']);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create(['buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Transmittal Test Customer, Inc.', 'tin' => '111-222-333-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR', 'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'], 'contact_email' => 'transmittal@example.test', 'contact_phone' => '+639171111115', 'effective_from' => now()->subDay(), 'status' => 'active']);
    }

    public function test_yellow_transmittal_captures_an_immutable_explicit_posted_invoice_membership(): void
    {
        $invoice = $this->postInvoice();
        $asOf = now('Asia/Manila')->toDateString();

        $sources = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/transmittals/eligible-sources?kind=YELLOW_INVOICE&as_of_date='.$asOf)->assertOk();
        $this->assertSame($invoice->id, $sources->json('data.0.id'));

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/transmittals', ['kind' => 'YELLOW_INVOICE', 'as_of_date' => $asOf, 'invoice_ids' => [$invoice->id]])->assertCreated();
        $transmittal = Transmittal::with('yellowItems')->findOrFail($response->json('data.id'));

        $this->assertSame('YELLOW_INVOICE', $transmittal->kind);
        $this->assertSame('GENERATED', $transmittal->status);
        $this->assertSame(1, $transmittal->source_item_count);
        $this->assertStringStartsWith('YTR-', $transmittal->transmittal_number);
        $this->assertSame((string) $invoice->total_charge_amount, $transmittal->summary['invoice_total']);
        $this->assertCount(1, $transmittal->yellowItems);
        $this->assertSame($invoice->id, $transmittal->yellowItems->first()->invoice_id);
        $this->assertSame($invoice->buyer_snapshot_name, $transmittal->yellowItems->first()->buyer_snapshot['name']);
        $this->assertSame('POSTED', $invoice->fresh()->status);
        $snapshot = DocumentSnapshot::where('document_type', 'TRANSMITTAL')->where('document_id', $transmittal->id)->sole();
        $artifact = DocumentArtifact::where('document_type', 'TRANSMITTAL')->where('document_id', $transmittal->id)->where('artifact_type', 'CANONICAL_PDF')->sole();
        $this->assertSame('YELLOW_INVOICE', $snapshot->document_kind);
        $this->assertSame((string) $invoice->total_charge_amount, data_get($snapshot->payload_snapshot, 'summary.primary_total'));
        $this->assertTrue((bool) data_get($snapshot->routing_metadata, 'non_fiscal'));
        $this->assertSame('RENDERED', $artifact->status);
        Storage::disk('private')->assertExists($artifact->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($artifact->file_path));
        $this->assertTrue($artifact->verifyIntegrity());
        $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/transmittals/{$transmittal->id}/artifact/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('audit_events', ['aggregate_type' => 'TRANSMITTAL', 'aggregate_id' => $transmittal->id, 'event_type' => 'TRANSMITTAL_GENERATED']);
    }

    public function test_white_transmittal_captures_receipt_settlement_figures_without_tax_inference(): void
    {
        $invoice = $this->postInvoice();
        $receipt = $this->postReceipt($invoice, (string) $invoice->total_charge_amount, 'WTR-POSTED');
        $asOf = now('Asia/Manila')->toDateString();

        $sources = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/transmittals/eligible-sources?kind=WHITE_RECEIPT&as_of_date='.$asOf)->assertOk();
        $this->assertSame($receipt->id, $sources->json('data.0.id'));

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/transmittals', ['kind' => 'WHITE_RECEIPT', 'as_of_date' => $asOf, 'receipt_ids' => [$receipt->id]])->assertCreated();
        $transmittal = Transmittal::with('whiteItems')->findOrFail($response->json('data.id'));
        $item = $transmittal->whiteItems->sole();

        $this->assertSame('WHITE_RECEIPT', $transmittal->kind);
        $this->assertStringStartsWith('WTR-', $transmittal->transmittal_number);
        $this->assertSame((string) $receipt->applied_amount, $transmittal->summary['applied_total']);
        $this->assertSame($receipt->id, $item->receipt_id);
        $this->assertSame((string) $receipt->cash_received_amount, $item->cash_received_amount);
        $this->assertSame((string) $receipt->withholding_received_amount, $item->withholding_received_amount);
        $this->assertSame('POSTED', $item->source_snapshot['source_status']);
        $this->assertArrayNotHasKey('bir_2307_income', $transmittal->summary);
        $this->assertSame('POSTED', $receipt->fresh()->status);
    }

    public function test_transmittal_rejects_duplicate_and_out_of_date_source_selection(): void
    {
        $invoice = $this->postInvoice();
        $yesterday = now('Asia/Manila')->subDay()->toDateString();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/transmittals', ['kind' => 'YELLOW_INVOICE', 'as_of_date' => now('Asia/Manila')->toDateString(), 'invoice_ids' => [$invoice->id, $invoice->id]])->assertStatus(422)->assertJsonValidationErrors('invoice_ids.0');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/transmittals', ['kind' => 'YELLOW_INVOICE', 'as_of_date' => $yesterday, 'invoice_ids' => [$invoice->id]])->assertStatus(422)->assertJsonValidationErrors('invoice_ids');
        $this->assertDatabaseCount('transmittals', 0);
    }

    private function postInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', ['customer_id' => $this->customer->id, ...$this->invoiceShipmentPayload(), 'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]]])->assertCreated();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts/'.$draft->json('data.id').'/post', ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($draft->json('data.id'));
    }

    private function postReceipt(Invoice $invoice, string $amount, string $key): object
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', ['source_type' => 'MANUAL_BANK_VERIFICATION', 'source_key' => $key, 'customer_id' => $this->customer->id, 'allocations' => [['invoice_id' => $invoice->id, 'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => $amount, 'reference' => $key]]]]])->assertOk();

        return Receipt::findOrFail($response->json('data.id'));
    }
}
