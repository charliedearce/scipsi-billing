<?php

namespace Tests\Feature\Statements;

use App\Models\AccountStatement;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\PrivateFile;
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
        $withholding = bccomp($partial, '10.00', 2) > 0 ? '10.00' : '1.00';
        $cash = bcsub($partial, $withholding, 2);
        $this->postReceipt($invoice, $partial, 'SOA-PARTIAL', $this->approvedCertificate($withholding), $withholding);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/account-statements', ['customer_id' => $this->customer->id, 'as_of_date' => now('Asia/Manila')->toDateString()])->assertCreated();
        $statement = AccountStatement::with('items')->findOrFail($response->json('data.id'));
        $this->assertSame('GENERATED', $statement->status);
        $this->assertCount(1, $statement->items);
        $this->assertSame((string) $invoice->total_charge_amount, $statement->invoice_total);
        $this->assertSame($partial, $statement->payment_total);
        $this->assertSame($cash, $statement->cash_applied_total);
        $this->assertSame($withholding, $statement->withholding_applied_total);
        $this->assertSame(bcsub((string) $invoice->total_charge_amount, $partial, 2), $statement->outstanding_total);
        $line = $statement->items->first();
        $this->assertSame('Statement Test Customer, Inc.', $line->snapshot['buyer_name']);
        $this->assertSame('111-222-333-000', $line->snapshot['buyer_tin']);
        $this->assertSame('HONDURAS', $line->snapshot['vessel_name']);
        $this->assertSame('102', $line->snapshot['voyage']);
        $this->assertSame($this->money($invoice->tax_amount), $line->snapshot['tax_amount']);
        $this->assertSame($cash, $line->snapshot['cash_applied_amount']);
        $this->assertSame($withholding, $line->snapshot['withholding_applied_amount']);
        $this->assertCount(1, $line->snapshot['receipts']);
        $this->assertSame($cash, $line->snapshot['receipts'][0]['cash_applied_amount']);
        $this->assertSame($withholding, $line->snapshot['receipts'][0]['withholding_applied_amount']);

        $snapshot = DocumentSnapshot::where('document_type', 'ACCOUNT_STATEMENT')
            ->where('document_id', $statement->id)
            ->sole();
        $artifact = DocumentArtifact::where('document_type', 'ACCOUNT_STATEMENT')
            ->where('document_id', $statement->id)
            ->where('artifact_type', 'CANONICAL_PDF')
            ->sole();
        $this->assertSame('ACCOUNT_STATEMENT', $snapshot->document_kind);
        $this->assertSame((string) $statement->outstanding_total, data_get($snapshot->payload_snapshot, 'totals.outstanding_total'));
        $this->assertSame($cash, data_get($snapshot->payload_snapshot, 'totals.cash_applied_total'));
        $this->assertSame($withholding, data_get($snapshot->payload_snapshot, 'totals.withholding_applied_total'));
        $this->assertSame($this->money($invoice->tax_amount), data_get($snapshot->payload_snapshot, 'totals.tax_total'));
        $this->assertSame('SOA-001', data_get($snapshot->payload_snapshot, 'customer.account_number'));
        $this->assertNotSame('—', data_get($snapshot->payload_snapshot, 'items.0.settled_by'));
        $this->assertSame('LANDSCAPE', data_get($snapshot->templateVersion->layout_definition, 'page.orientation'));
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
        $this->assertSame($cash, $statement->cash_applied_total);
        $this->assertSame($withholding, $statement->withholding_applied_total);
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

    private function postReceipt(Invoice $invoice, string $amount, string $key, ?CustomerWithholdingCertificate $certificate = null, string $withholding = '0.00'): void
    {
        $allocation = [
            'invoice_id' => $invoice->id,
            'tenders' => [[
                'type' => 'BANK_TRANSFER',
                'status' => 'CONFIRMED',
                'amount' => $certificate ? bcsub($amount, $withholding, 2) : $amount,
                'reference' => $key,
            ]],
        ];
        if ($certificate) {
            $allocation['withholding_applications'] = [[
                'certificate_id' => $certificate->id,
                'amount' => $withholding,
            ]];
        }

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => $key,
            'customer_id' => $this->customer->id,
            'allocations' => [$allocation],
        ])->assertOk();
    }

    private function approvedCertificate(string $amount): CustomerWithholdingCertificate
    {
        $type = DocumentType::where('code', 'BIR_2307')->firstOrFail();
        $file = PrivateFile::create([
            'organization_id' => $this->admin->organization_id,
            'document_type_id' => $type->id,
            'purpose' => 'WITHHOLDING_CERTIFICATE',
            'uploaded_by' => $this->admin->id,
            'owner_id' => $this->admin->id,
            'current_version' => 1,
            'status' => 'CLEAN',
        ]);

        return CustomerWithholdingCertificate::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => $this->customer->id,
            'certificate_no' => '2307-SOA-001',
            'private_file_id' => $file->id,
            'reviewed_version_number' => 1,
            'payor_tin' => '111-222-333-000',
            'payor_name' => 'Statement Test Customer',
            'payee_tin' => '000-123-456-000',
            'payee_name' => 'SCIPSI',
            'period_from' => now('Asia/Manila')->startOfMonth()->toDateString(),
            'period_to' => now('Asia/Manila')->endOfMonth()->toDateString(),
            'atc_code' => 'WC100',
            'income_payment_base' => '1000.00',
            'withholding_rate' => '0.0100',
            'certified_amount' => $amount,
            'allocated_amount' => '0.00',
            'remaining_amount' => $amount,
            'status' => 'APPROVED',
            'reviewed_by_user_id' => $this->admin->id,
            'reviewed_at' => now(),
            'lock_version' => 1,
        ]);
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }
}
