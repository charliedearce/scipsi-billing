<?php

namespace Tests\Feature\Receipts;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentArtifact;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\PrivateFile;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptPostingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Customer $customer;

    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = Customer::create(['organization_id' => $this->admin->organization_id, 'account_number' => 'RCPT-001', 'name' => 'Receipt Test Customer', 'status' => 'active', 'customer_type' => 'business']);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create(['buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Receipt Test Customer, Inc.', 'tin' => '111-222-333-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR', 'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'], 'contact_email' => 'receipts@example.test', 'contact_phone' => '+639171111111', 'effective_from' => now()->subDay(), 'status' => 'active']);
    }

    public function test_posts_atomic_receipt_with_number_history_and_canonical_pdf(): void
    {
        $invoice = $this->postedInvoice();
        $body = $this->receiptPayload($invoice->id, $invoice->total_charge_amount, 'BANK-001');
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $body);
        $response->assertOk()->assertJsonPath('data.status', 'POSTED')->assertJsonPath('data.receipt_number', 'CR-0000000001');
        $receipt = Receipt::firstOrFail();
        $this->assertSame((string) $invoice->total_charge_amount, $receipt->applied_amount);
        $this->assertSame('0.00', $receipt->unapplied_amount);
        $this->assertSame('Receipt Test Customer, Inc.', $receipt->payer_snapshot['registered_name']);
        $this->assertDatabaseCount('receipt_tenders', 1);
        $this->assertDatabaseCount('receipt_allocations', 1);
        $this->assertDatabaseHas('document_revisions', ['document_type' => 'RECEIPT', 'document_id' => $receipt->id, 'revision_number' => 1]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'RECEIPT_POSTED', 'aggregate_id' => $receipt->id]);
        $artifact = DocumentArtifact::where('document_type', 'RECEIPT')->where('document_id', $receipt->id)->firstOrFail();
        $this->assertSame('RENDERED', $artifact->status);
        $this->assertTrue(Storage::disk('private')->exists($artifact->file_path));
        $this->assertStringStartsWith('%PDF-', (string) Storage::disk('private')->get($artifact->file_path));
    }

    public function test_source_key_retry_returns_same_receipt_without_second_number_or_allocation(): void
    {
        $invoice = $this->postedInvoice();
        $payload = $this->receiptPayload($invoice->id, $invoice->total_charge_amount, 'RETRY-001');
        $first = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $second = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseCount('receipt_allocations', 1);
        $this->assertDatabaseCount('document_numbers', 2); // one invoice number and one receipt number
    }

    public function test_rendered_receipt_artifact_queues_one_safe_sms_intent_for_the_verified_customer(): void
    {
        $this->verifiedSmsContact();
        $invoice = $this->postedInvoice();
        $payload = $this->receiptPayload($invoice->id, $invoice->total_charge_amount, 'RECEIPT-SMS-001');

        $first = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $receipt = Receipt::findOrFail($first->json('data.id'));
        $event = NotificationEvent::where('organization_id', $this->admin->organization_id)
            ->where('event_key', 'RECEIPT_ARTIFACT_READY')
            ->where('event_source_type', 'receipt')
            ->where('event_source_id', $receipt->id)
            ->firstOrFail();
        $delivery = NotificationDelivery::where('event_id', $event->id)->firstOrFail();

        $this->assertSame($this->customerUser->id, $event->user_id);
        $this->assertSame($receipt->receipt_number, $event->payload_snapshot['reference_no']);
        $this->assertSame('queued_local', $delivery->status);
        $this->assertStringContainsString($receipt->receipt_number, $delivery->rendered_body);
        $this->assertStringNotContainsString('RECEIPT-SMS-001', $delivery->rendered_body);
        $this->assertStringNotContainsString((string) $receipt->applied_amount, $delivery->rendered_body);
        $this->assertDatabaseCount('sms_delivery_attempts', 0);

        $second = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $this->assertSame($receipt->id, $second->json('data.id'));
        $this->assertSame(1, NotificationEvent::where('event_key', 'RECEIPT_ARTIFACT_READY')
            ->where('event_source_type', 'receipt')->where('event_source_id', $receipt->id)->count());
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_changed_retry_payload_is_rejected_and_pending_check_cannot_post(): void
    {
        $invoice = $this->postedInvoice();
        $payload = $this->receiptPayload($invoice->id, '100.00', 'SOURCE-CHANGED');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $payload['allocations'][0]['tenders'][0]['amount'] = '101.00';
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertStatus(422)->assertJsonValidationErrors('source_key');
        $pending = $this->receiptPayload($invoice->id, '10.00', 'CHECK-PENDING');
        $pending['allocations'][0]['tenders'][0]['type'] = 'CHECK';
        $pending['allocations'][0]['tenders'][0]['status'] = 'PENDING_CLEARANCE';
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $pending)->assertStatus(422);
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_two_distinct_partial_sources_accumulate_without_overwriting_receipt_history(): void
    {
        $invoice = $this->postedInvoice();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $this->receiptPayload($invoice->id, '100.00', 'PARTIAL-001'))->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $this->receiptPayload($invoice->id, '200.00', 'PARTIAL-002'))->assertOk();

        $this->assertDatabaseCount('receipts', 2);
        $this->assertDatabaseCount('receipt_allocations', 2);
        $this->assertSame('300.00', ReceiptAllocation::where('invoice_id', $invoice->id)->sum('applied_amount'));
        $this->assertDatabaseCount('document_revisions', 4); // invoice draft/post plus one immutable revision per receipt
    }

    public function test_withholding_capacity_is_consumed_only_for_amount_actually_applied(): void
    {
        $invoice = $this->postedInvoice();
        $certificate = $this->certificate('1000.00');
        $payload = $this->receiptPayload($invoice->id, $invoice->total_charge_amount, 'CWT-001');
        $payload['allocations'][0]['withholding_applications'] = [['certificate_id' => $certificate->id, 'amount' => '1000.00']];
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $receipt = Receipt::findOrFail($response->json('data.id'));
        $allocation = ReceiptAllocation::where('receipt_id', $receipt->id)->firstOrFail();
        $this->assertSame((string) $invoice->total_charge_amount, $allocation->applied_amount);
        $this->assertSame('0.00', $certificate->fresh()->allocated_amount);
        $this->assertSame('0.00', $receipt->withholding_received_amount);
    }

    protected function postedInvoice()
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', ['customer_id' => $this->customer->id, 'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]]]);
        $draft->assertCreated();
        $id = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/invoices/drafts/{$id}/post", ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($id);
    }

    protected function receiptPayload(int $invoiceId, string $amount, string $sourceKey): array
    {
        return ['source_type' => 'MANUAL_BANK_VERIFICATION', 'source_key' => $sourceKey, 'customer_id' => $this->customer->id, 'allocations' => [['invoice_id' => $invoiceId, 'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => (string) $amount, 'reference' => $sourceKey]]]]];
    }

    protected function verifiedSmsContact(): CustomerContactPoint
    {
        $this->customerUser = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Receipt Portal Customer',
            'email' => 'receipt.portal@example.test',
            'password' => 'password',
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach(Role::where('name', 'Customer')->firstOrFail());
        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'owner',
            'is_active' => true,
            'linked_at' => now(),
            'approved_by_user_id' => $this->admin->id,
        ]);

        return CustomerContactPoint::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'type' => 'mobile',
            'value' => '+639171111119',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
            'version' => 1,
            'lock_version' => 1,
        ]);
    }

    protected function certificate(string $amount): CustomerWithholdingCertificate
    {
        $type = DocumentType::where('code', 'BIR_2307')->firstOrFail();
        $file = PrivateFile::create(['organization_id' => $this->admin->organization_id, 'document_type_id' => $type->id, 'purpose' => 'WITHHOLDING_CERTIFICATE', 'uploaded_by' => $this->admin->id, 'owner_id' => $this->admin->id, 'current_version' => 1, 'status' => 'CLEAN']);

        return CustomerWithholdingCertificate::create(['organization_id' => $this->admin->organization_id, 'customer_id' => $this->customer->id, 'certificate_no' => '2307-RCPT-001', 'private_file_id' => $file->id, 'reviewed_version_number' => 1, 'payor_tin' => '111-222-333-000', 'payor_name' => 'Receipt Test Customer', 'payee_tin' => '000-123-456-000', 'payee_name' => 'SCIPSI', 'period_from' => Carbon::now()->startOfMonth(), 'period_to' => Carbon::now()->endOfMonth(), 'atc_code' => 'WC100', 'income_payment_base' => '1000.00', 'withholding_rate' => '0.0100', 'certified_amount' => $amount, 'allocated_amount' => '0.00', 'remaining_amount' => $amount, 'status' => 'APPROVED']);
    }
}
