<?php

namespace Tests\Feature\Receipts;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentArtifact;
use App\Models\DocumentCorrectionLink;
use App\Models\DocumentCorrectionRequest;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\PrivateFile;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $executor;

    protected User $teller;

    protected User $customerUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->executor = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Reversal Executor',
            'email' => 'reversal.executor@example.test',
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        $this->executor->roles()->attach(Role::where('name', 'Administrator')->firstOrFail());
        $this->teller = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Reversal Teller',
            'email' => 'reversal.teller@example.test',
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        $this->teller->roles()->attach(Role::where('name', 'Teller')->firstOrFail());
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'REV-001',
            'name' => 'Reversal Test Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Reversal Test Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'reversal@example.test',
            'contact_phone' => '+639171111121',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
    }

    public function test_approved_receipt_reversal_restores_balance_and_certificate_capacity_exactly_once(): void
    {
        $invoice = $this->postedInvoice();
        $certificate = $this->approvedCertificate('500.00');
        $receipt = $this->postReceiptWithWithholding($invoice, $certificate, '300.00');
        $artifact = DocumentArtifact::where('document_type', 'RECEIPT')->where('document_id', $receipt->id)->firstOrFail();
        $originalNumber = $receipt->receipt_number;
        $originalHash = $artifact->sha256_hash;

        $this->assertSame('0.00', $this->outstanding($invoice));
        $this->assertSame('200.00', $certificate->fresh()->remaining_amount);

        $requestId = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'RECEIPT',
            'document_id' => $receipt->id,
            'requested_action' => 'RECEIPT_REVERSAL',
            'reason' => 'Bank credit was applied to the wrong customer reference.',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/approve", [
            'decision_notes' => 'Independent review confirms the receipt must be reversed for settlement restoration.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Teller lacks receipts:reverse permission.',
        ])->assertForbidden();

        $executed = $this->actingAs($this->executor, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Execute settlement-only reversal; no fiscal replacement document is issued.',
        ])->assertOk();

        $this->assertSame('REVERSED', $executed->json('data.receipt.status'));
        $this->assertSame($originalNumber, $executed->json('data.receipt.receipt_number'));
        $this->assertSame($originalHash, $executed->json('data.receipt.canonical_artifact_hash'));
        $this->assertSame(DocumentCorrectionRequest::STATUS_EXECUTED, DocumentCorrectionRequest::findOrFail($requestId)->status);
        $this->assertSame((string) $invoice->total_charge_amount, $this->outstanding($invoice));
        $this->assertSame('500.00', $certificate->fresh()->remaining_amount);
        $this->assertSame('0.00', $certificate->fresh()->allocated_amount);
        $this->assertDatabaseHas('document_correction_links', [
            'original_document_type' => 'RECEIPT',
            'original_document_id' => $receipt->id,
            'correction_type' => 'REVERSAL',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'RECEIPT_REVERSED',
            'aggregate_type' => 'RECEIPT',
            'aggregate_id' => $receipt->id,
        ]);
        $this->assertSame(1, DocumentCorrectionLink::where('original_document_id', $receipt->id)->where('correction_type', 'REVERSAL')->count());
        $this->assertSame($originalHash, DocumentArtifact::findOrFail($artifact->id)->sha256_hash);
        $this->assertDatabaseCount('receipt_allocations', 1);

        $retry = $this->actingAs($this->executor, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Retry must restore capacity only once.',
        ])->assertOk();
        $this->assertSame($receipt->id, $retry->json('data.receipt.id'));
        $this->assertSame('500.00', $certificate->fresh()->remaining_amount);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame(1, DocumentCorrectionLink::where('original_document_id', $receipt->id)->where('correction_type', 'REVERSAL')->count());
    }

    public function test_reversed_receipt_no_longer_blocks_reallocation_and_administrator_requester_can_execute(): void
    {
        $invoice = $this->postedInvoice();
        $receipt = $this->postReceipt($invoice);
        $requestId = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'RECEIPT',
            'document_id' => $receipt->id,
            'requested_action' => 'RECEIPT_REVERSAL',
            'reason' => 'Duplicate settlement identity was discovered after posting.',
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/approve", [
            'decision_notes' => 'Administrator approved own settlement-only reversal.',
        ])->assertOk();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Administrator requester restores the collectible balance once.',
        ])->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'RECEIPT_REVERSED',
            'aggregate_id' => $receipt->id,
            'metadata->self_execution' => true,
        ]);

        $this->assertSame((string) $invoice->total_charge_amount, $this->outstanding($invoice));
        $reposted = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => 'REV-REPOST-'.$invoice->id,
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'tenders' => [[
                    'type' => 'BANK_TRANSFER',
                    'status' => 'CONFIRMED',
                    'amount' => $invoice->total_charge_amount,
                    'reference' => 'REV-REPOST-'.$invoice->id,
                ]],
            ]],
        ])->assertOk();

        $this->assertSame('POSTED', $reposted->json('data.status'));
        $this->assertSame('0.00', $this->outstanding($invoice));
        $this->assertDatabaseCount('receipts', 2);
        $this->assertSame(1, Receipt::where('status', 'REVERSED')->count());
        $this->assertSame(1, Receipt::where('status', 'POSTED')->count());
    }

    public function test_executed_receipt_reversal_queues_one_safe_sms_intent_without_provider_call(): void
    {
        $this->verifiedSmsContact();
        $invoice = $this->postedInvoice();
        $receipt = $this->postReceipt($invoice);

        $requestId = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'RECEIPT',
            'document_id' => $receipt->id,
            'requested_action' => 'RECEIPT_REVERSAL',
            'reason' => 'Customer reported a misapplied bank credit that must be reversed.',
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/approve", [
            'decision_notes' => 'Independent approval for settlement-only reversal notice.',
        ])->assertOk();

        $this->actingAs($this->executor, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Execute reversal and queue the local customer notice only.',
        ])->assertOk();

        $event = NotificationEvent::where('organization_id', $this->admin->organization_id)
            ->where('event_key', 'RECEIPT_REVERSED')
            ->where('event_source_type', 'receipt')
            ->where('event_source_id', $receipt->id)
            ->firstOrFail();
        $delivery = NotificationDelivery::where('event_id', $event->id)->firstOrFail();

        $this->assertSame($this->customerUser->id, $event->user_id);
        $this->assertSame($receipt->receipt_number, $event->payload_snapshot['reference_no']);
        $this->assertSame('collection receipt reversal', $event->payload_snapshot['action_label']);
        $this->assertSame('queued_local', $delivery->status);
        $this->assertStringContainsString($receipt->receipt_number, $delivery->rendered_body);
        $this->assertStringContainsString('collection receipt reversal', $delivery->rendered_body);
        $this->assertStringNotContainsString((string) $receipt->applied_amount, $delivery->rendered_body);
        $this->assertStringNotContainsString('misapplied bank credit', $delivery->rendered_body);
        $this->assertDatabaseCount('sms_delivery_attempts', 0);

        $this->actingAs($this->executor, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Retry must not create a second SMS intent.',
        ])->assertOk();

        $this->assertSame(1, NotificationEvent::where('event_key', 'RECEIPT_REVERSED')
            ->where('event_source_type', 'receipt')->where('event_source_id', $receipt->id)->count());
        $this->assertSame(1, NotificationDelivery::where('event_id', $event->id)->count());
        $this->assertSame('REVERSED', $receipt->fresh()->status);
        $this->assertSame((string) $invoice->total_charge_amount, $this->outstanding($invoice));
    }

    protected function outstanding(Invoice $invoice): string
    {
        $applied = (string) ReceiptAllocation::where('invoice_id', $invoice->id)
            ->whereHas('receipt', fn ($q) => $q->where('status', 'POSTED'))
            ->sum('applied_amount');

        return bccomp(bcsub((string) $invoice->total_charge_amount, $applied, 2), '0.00', 2) > 0
            ? bcsub((string) $invoice->total_charge_amount, $applied, 2)
            : '0.00';
    }

    protected function postedInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $this->customer->id,
            ...$this->invoiceShipmentPayload(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ])->assertCreated();
        $id = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts/'.$id.'/post', [
            'expected_version' => 1,
        ])->assertOk();

        return Invoice::findOrFail($id);
    }

    protected function postReceipt(Invoice $invoice): Receipt
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => 'REV-RECEIPT-'.$invoice->id.'-'.uniqid(),
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'tenders' => [[
                    'type' => 'BANK_TRANSFER',
                    'status' => 'CONFIRMED',
                    'amount' => $invoice->total_charge_amount,
                    'reference' => 'REV-RECEIPT-'.$invoice->id,
                ]],
            ]],
        ])->assertOk();

        return Receipt::findOrFail($response->json('data.id'));
    }

    protected function postReceiptWithWithholding(Invoice $invoice, CustomerWithholdingCertificate $certificate, string $withholdingAmount): Receipt
    {
        $cash = bcsub((string) $invoice->total_charge_amount, $withholdingAmount, 2);
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => 'REV-WH-'.$invoice->id.'-'.uniqid(),
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'tenders' => [[
                    'type' => 'BANK_TRANSFER',
                    'status' => 'CONFIRMED',
                    'amount' => $cash,
                    'reference' => 'REV-WH-'.$invoice->id,
                ]],
                'withholding_applications' => [[
                    'certificate_id' => $certificate->id,
                    'amount' => $withholdingAmount,
                ]],
            ]],
        ])->assertOk();

        return Receipt::findOrFail($response->json('data.id'));
    }

    protected function verifiedSmsContact(): CustomerContactPoint
    {
        $this->customerUser = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Reversal Portal Customer',
            'email' => 'reversal.portal@example.test',
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
            'value' => '+639171111122',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
            'version' => 1,
            'lock_version' => 1,
        ]);
    }

    protected function approvedCertificate(string $amount): CustomerWithholdingCertificate
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
            'certificate_no' => '2307-REV-001',
            'private_file_id' => $file->id,
            'reviewed_version_number' => 1,
            'payor_tin' => '111-222-333-000',
            'payor_name' => 'Reversal Test Customer',
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
}
