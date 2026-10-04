<?php

namespace Tests\Feature\Corrections;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentArtifact;
use App\Models\DocumentCorrectionRequest;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $secondAdmin;

    protected User $teller;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->secondAdmin = User::create(['organization_id' => $this->admin->organization_id, 'name' => 'Independent Approver', 'email' => 'independent.approver@example.test', 'password' => 'Password123!', 'status' => 'active']);
        $this->secondAdmin->roles()->attach(Role::where('name', 'Administrator')->firstOrFail());
        $this->teller = User::create(['organization_id' => $this->admin->organization_id, 'name' => 'Correction Teller', 'email' => 'correction.teller@example.test', 'password' => 'Password123!', 'status' => 'active']);
        $this->teller->roles()->attach(Role::where('name', 'Teller')->firstOrFail());
        $this->customer = Customer::create(['organization_id' => $this->admin->organization_id, 'account_number' => 'CORR-001', 'name' => 'Correction Test Customer', 'status' => 'active', 'customer_type' => 'business']);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create(['buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Correction Test Customer, Inc.', 'tin' => '111-222-333-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR', 'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'], 'contact_email' => 'corrections@example.test', 'contact_phone' => '+639171111112', 'effective_from' => now()->subDay(), 'status' => 'active']);
    }

    public function test_approved_invoice_correction_can_start_draft_edit_and_post_replacement(): void
    {
        $invoice = $this->postedInvoice();
        $originalNumber = $invoice->invoice_number;
        $originalHash = DocumentArtifact::where('document_type', 'INVOICE')->where('document_id', $invoice->id)->value('sha256_hash');
        $originalTotal = (string) $invoice->total_charge_amount;

        $request = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'INVOICE',
            'document_id' => $invoice->id,
            'requested_action' => 'INVOICE_CORRECTION',
            'reason' => 'Wrong quantity was entered on the issued unpaid bill.',
        ])->assertCreated();
        $id = $request->json('data.id');

        $this->actingAs($this->secondAdmin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/approve", [
            'decision_notes' => 'Approved for linked replacement draft; original SI must remain unchanged.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $started = $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/start-draft")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');
        $draftId = $started->json('data.correction_draft_invoice_id');
        $this->assertNotNull($draftId);
        $this->assertSame('DRAFT', Invoice::findOrFail($draftId)->status);

        $draftLock = (int) Invoice::findOrFail($draftId)->lock_version;
        $this->actingAs($this->teller, 'sanctum')->putJson("/api/v1/invoices/drafts/{$draftId}", [
            'expected_version' => $draftLock,
            'customer_id' => $this->customer->id,
            'business_date' => now('Asia/Manila')->format('Y-m-d'),
            ...$this->invoiceShipmentPayload(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 5]],
            'reason' => 'Correction draft quantity adjustment',
        ])->assertOk();

        $draftLock = (int) Invoice::findOrFail($draftId)->lock_version;
        $executed = $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/execute", [
            'execution_notes' => 'Posting linked replacement after approved unpaid correction review.',
            'expected_version' => $draftLock,
        ])->assertOk();

        $this->assertSame('EXECUTED', $executed->json('data.correction_request.status'));
        $replacementId = $executed->json('data.replacement_invoice.id');
        $replacement = Invoice::findOrFail($replacementId);
        $this->assertSame('POSTED', $replacement->status);
        $this->assertNotSame($originalNumber, $replacement->invoice_number);
        $this->assertNotSame($originalTotal, (string) $replacement->total_charge_amount);

        $freshOriginal = $invoice->fresh();
        $this->assertSame('SUPERSEDED', $freshOriginal->status);
        $this->assertSame($replacementId, $freshOriginal->superseded_by_invoice_id);
        $this->assertSame($originalNumber, $freshOriginal->invoice_number);
        $this->assertSame($originalTotal, (string) $freshOriginal->total_charge_amount);
        $this->assertSame($originalHash, DocumentArtifact::where('document_type', 'INVOICE')->where('document_id', $invoice->id)->value('sha256_hash'));
        $this->assertDatabaseHas('audit_events', [
            'aggregate_type' => 'INVOICE',
            'aggregate_id' => $invoice->id,
            'event_type' => 'INVOICE_SUPERSEDED',
        ]);

        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'INVOICE',
            'document_id' => $invoice->id,
            'requested_action' => 'INVOICE_CORRECTION',
            'reason' => 'A superseded original must not re-enter unpaid correction.',
        ])->assertStatus(422)->assertJsonValidationErrors('document');

        $this->assertDatabaseHas('document_correction_links', [
            'original_document_type' => 'INVOICE',
            'original_document_id' => $invoice->id,
            'correction_document_type' => 'INVOICE',
            'correction_document_id' => $replacementId,
            'correction_type' => 'REPLACEMENT',
        ]);
        $this->assertDatabaseHas('document_correction_requests', [
            'id' => $id,
            'status' => 'EXECUTED',
            'replacement_invoice_id' => $replacementId,
        ]);
    }

    public function test_paid_invoice_blocks_correction_draft_start_after_approval(): void
    {
        $invoice = $this->postedInvoice();
        $request = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'INVOICE',
            'document_id' => $invoice->id,
            'requested_action' => 'INVOICE_CORRECTION',
            'reason' => 'Will become paid before draft start.',
        ])->assertCreated();
        $id = $request->json('data.id');
        $this->actingAs($this->secondAdmin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/approve", [
            'decision_notes' => 'Approved while unpaid; settlement will block draft start.',
        ])->assertOk();

        $this->postReceipt($invoice);

        $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/start-draft")
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');
    }

    public function test_administrator_requester_can_approve_own_request_and_it_is_recorded_as_self_review(): void
    {
        $invoice = $this->postedInvoice();
        $artifact = DocumentArtifact::where('document_type', 'INVOICE')->where('document_id', $invoice->id)->firstOrFail();
        $request = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'requested_action' => 'INVOICE_CORRECTION', 'reason' => 'Wrong cargo reference was entered in the issued bill.'])->assertCreated();
        $id = $request->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/approve", ['decision_notes' => 'Administrator reviewed own request against source documents.'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame('POSTED', $invoice->fresh()->status);
        $this->assertSame($invoice->invoice_number, $invoice->fresh()->invoice_number);
        $this->assertSame($artifact->sha256_hash, DocumentArtifact::findOrFail($artifact->id)->sha256_hash);
        $event = DocumentCorrectionRequest::findOrFail($id)->events()->where('event_type', 'APPROVED')->firstOrFail();
        $this->assertTrue($event->metadata['self_review']);
        $this->assertDatabaseHas('audit_events', ['aggregate_type' => 'DOCUMENT_CORRECTION_REQUEST', 'aggregate_id' => $id, 'event_type' => 'CORRECTION_APPROVED', 'metadata->self_review' => true]);
    }

    public function test_independent_approval_is_not_flagged_as_self_review(): void
    {
        $invoice = $this->postedInvoice();
        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'requested_action' => 'INVOICE_CORRECTION', 'reason' => 'Wrong cargo reference was entered in the issued bill.'])->assertCreated()->json('data.id');
        $this->actingAs($this->secondAdmin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/approve", ['decision_notes' => 'Reviewed against source documents by a second Administrator.'])->assertOk();
        $this->assertDatabaseHas('audit_events', ['aggregate_type' => 'DOCUMENT_CORRECTION_REQUEST', 'aggregate_id' => $id, 'event_type' => 'CORRECTION_APPROVED', 'metadata->self_review' => false]);
    }

    public function test_non_administrator_requester_cannot_decide_own_request(): void
    {
        $checkerRole = Role::create(['organization_id' => $this->admin->organization_id, 'name' => 'Correction Checker', 'label' => 'Correction Checker', 'is_system' => false]);
        $checkerRole->permissions()->attach(Permission::whereIn('name', ['corrections:request', 'corrections:approve'])->pluck('id'));
        $checker = User::create(['organization_id' => $this->admin->organization_id, 'name' => 'Checker', 'email' => 'correction.checker@example.test', 'password' => 'Password123!', 'status' => 'active']);
        $checker->roles()->attach($checkerRole);
        $invoice = $this->postedInvoice();
        $id = $this->actingAs($checker, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'requested_action' => 'INVOICE_CORRECTION', 'reason' => 'Wrong cargo reference was entered in the issued bill.'])->assertCreated()->json('data.id');
        $this->actingAs($checker, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/approve", ['decision_notes' => 'Non-administrator self-approval must stay blocked.'])->assertStatus(422)->assertJsonValidationErrors('reviewer');
        $this->assertSame('PENDING', DocumentCorrectionRequest::findOrFail($id)->status);
    }

    public function test_issued_invoice_with_posted_receipt_cannot_enter_unpaid_correction_flow(): void
    {
        $invoice = $this->postedInvoice();
        $this->postReceipt($invoice);
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'requested_action' => 'INVOICE_CORRECTION', 'reason' => 'A received payment means reconciliation is required first.'])->assertStatus(422)->assertJsonValidationErrors('document');
        $this->assertDatabaseCount('document_correction_requests', 0);
    }

    public function test_receipt_reversal_can_be_requested_and_approved_without_editing_receipt_facts(): void
    {
        $invoice = $this->postedInvoice();
        $receipt = $this->postReceipt($invoice);
        $original = $receipt->only(['status', 'receipt_number', 'applied_amount', 'unapplied_amount']);
        $request = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'RECEIPT', 'document_id' => $receipt->id, 'requested_action' => 'RECEIPT_REVERSAL', 'reason' => 'Bank verification was entered under the wrong customer reference.'])->assertCreated();
        $id = $request->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/document-correction-requests/{$id}/approve", ['decision_notes' => 'Approved as a reversal request; no fiscal or settlement execution occurs until approved rules are available.'])->assertOk();
        $this->assertSame($original, Receipt::findOrFail($receipt->id)->only(array_keys($original)));
    }

    public function test_stale_target_cannot_be_approved_and_active_request_is_not_duplicated(): void
    {
        $invoice = $this->postedInvoice();
        $request = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'requested_action' => 'INVOICE_CORRECTION', 'reason' => 'A source-document issue needs correction review.'])->assertCreated();
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', ['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'requested_action' => 'INVOICE_CORRECTION', 'reason' => 'A duplicate active request must be denied.'])->assertStatus(422)->assertJsonValidationErrors('document');
        $invoice->increment('lock_version');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/document-correction-requests/'.$request->json('data.id').'/approve', ['decision_notes' => 'Current document state must be reviewed again.'])->assertStatus(409);
        $this->assertSame(DocumentCorrectionRequest::STATUS_PENDING, DocumentCorrectionRequest::findOrFail($request->json('data.id'))->status);
    }

    public function test_staff_can_request_by_visible_document_number_and_receive_a_minimal_history_response(): void
    {
        $invoice = $this->postedInvoice();
        $request = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'INVOICE',
            'document_number' => $invoice->invoice_number,
            'requested_action' => 'INVOICE_CORRECTION',
            'reason' => 'The printed customer reference needs controlled review.',
        ])->assertCreated()->assertJsonPath('data.target.document_number', $invoice->invoice_number);

        $item = $this->actingAs($this->teller, 'sanctum')->getJson('/api/v1/document-correction-requests?status=PENDING')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->json('data.id'))
            ->assertJsonPath('data.0.events.0.event_type', 'REQUESTED')
            ->json('data.0');

        $this->assertSame('Correction Teller', $item['requested_by']['name']);
        $this->assertArrayNotHasKey('buyer_snapshot_email', $item['target']);
        $this->assertArrayNotHasKey('email', $item['requested_by']);
    }

    protected function postedInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', ['customer_id' => $this->customer->id, ...$this->invoiceShipmentPayload(), 'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]]])->assertCreated();
        $id = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/invoices/drafts/{$id}/post", ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($id);
    }

    protected function postReceipt(Invoice $invoice): Receipt
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', ['source_type' => 'MANUAL_BANK_VERIFICATION', 'source_key' => 'CORR-RECEIPT-'.$invoice->id, 'customer_id' => $this->customer->id, 'allocations' => [['invoice_id' => $invoice->id, 'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => $invoice->total_charge_amount, 'reference' => 'CORR-RECEIPT-'.$invoice->id]]]]])->assertOk();

        return Receipt::findOrFail($response->json('data.id'));
    }
}
