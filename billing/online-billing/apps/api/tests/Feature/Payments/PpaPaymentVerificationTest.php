<?php

namespace Tests\Feature\Payments;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PpaPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $ppa;

    protected User $unscopedPpa;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->ppa = User::where('email', 'ppa1@ppa.gov.ph')->firstOrFail();
        $this->unscopedPpa = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Unscoped PPA Officer',
            'email' => 'ppa.unscoped@example.test',
            'password' => 'Password123!',
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->unscopedPpa->roles()->attach(Role::where('name', 'PPA user')->firstOrFail());

        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'PPA-VERIFY-001',
            'name' => 'PPA Verification Customer',
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
            'registered_name' => 'PPA Verification Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Makar Wharf',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'contact_email' => 'ppa-verification@example.test',
            'contact_phone' => '+639171111117',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
    }

    public function test_ppa_can_verify_current_partial_and_paid_status_with_minimal_confirmed_receipt_summaries(): void
    {
        $invoice = $this->postedInvoice();
        $this->postReceipt($invoice->id, '100.00', 'PPA-PARTIAL-001');

        $partial = $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertOk()
            ->assertJsonPath('data.settlement_status', 'PARTIALLY_PAID')
            ->assertJsonPath('data.applied_amount', '100.00')
            ->assertJsonPath('data.confirmed_receipt_count', 1)
            ->assertJsonPath('data.reversed_receipt_count', 0)
            ->assertJsonPath('data.receipts.0.applied_amount', '100.00')
            ->assertJsonPath('data.receipts.0.receipt_status', 'POSTED')
            ->assertJsonPath('data.formal_clearance_issued', false);
        $this->assertArrayNotHasKey('payer_snapshot', $partial->json('data.receipts.0'));
        $this->assertArrayNotHasKey('reference', $partial->json('data.receipts.0'));

        $remaining = bcsub((string) $invoice->total_charge_amount, '100.00', 2);
        $this->postReceipt($invoice->id, $remaining, 'PPA-PAID-002');

        $paid = $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertOk()
            ->assertJsonPath('data.settlement_status', 'PAID')
            ->assertJsonPath('data.outstanding_amount', '0.00')
            ->assertJsonPath('data.confirmed_receipt_count', 2)
            ->assertJsonPath('data.clearance_eligibility', 'FULLY_PAID');
        $this->assertSame(
            Receipt::orderByDesc('id')->value('receipt_number'),
            $paid->json('data.receipts.0.receipt_number'),
        );
        $this->assertDatabaseCount('ppa_verification_events', 2);
    }

    public function test_ppa_sees_reversed_receipt_as_history_without_counting_it_as_paid(): void
    {
        $executor = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'PPA Reversal Executor',
            'email' => 'ppa.reversal.executor@example.test',
            'password' => 'Password123!',
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $executor->roles()->attach(Role::where('name', 'Administrator')->firstOrFail());

        $invoice = $this->postedInvoice();
        $this->postReceipt($invoice->id, (string) $invoice->total_charge_amount, 'PPA-REVERSE-001');
        $receipt = Receipt::where('customer_id', $this->customer->id)->orderByDesc('id')->firstOrFail();

        $paid = $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertOk()
            ->assertJsonPath('data.settlement_status', 'PAID')
            ->assertJsonPath('data.confirmed_receipt_count', 1)
            ->assertJsonPath('data.reversed_receipt_count', 0)
            ->assertJsonPath('data.clearance_eligibility', 'FULLY_PAID');
        $this->assertSame($receipt->receipt_number, $paid->json('data.receipts.0.receipt_number'));
        $this->assertSame('POSTED', $paid->json('data.receipts.0.receipt_status'));

        $requestId = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/document-correction-requests', [
            'document_type' => 'RECEIPT',
            'document_id' => $receipt->id,
            'requested_action' => 'RECEIPT_REVERSAL',
            'reason' => 'Bank credit must be reversed so PPA sees unpaid history.',
        ])->assertCreated()->json('data.id');
        $this->actingAs($executor, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/approve", [
            'decision_notes' => 'Approve settlement-only reversal for PPA verification coverage.',
        ])->assertOk();
        $this->actingAs($executor, 'sanctum')->postJson("/api/v1/document-correction-requests/{$requestId}/execute", [
            'execution_notes' => 'Execute reversal so confirmed settlement no longer applies.',
        ])->assertOk();

        $reversed = $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertOk()
            ->assertJsonPath('data.settlement_status', 'UNPAID')
            ->assertJsonPath('data.applied_amount', '0.00')
            ->assertJsonPath('data.outstanding_amount', (string) $invoice->total_charge_amount)
            ->assertJsonPath('data.confirmed_receipt_count', 0)
            ->assertJsonPath('data.reversed_receipt_count', 1)
            ->assertJsonPath('data.clearance_eligibility', 'NOT_ELIGIBLE')
            ->assertJsonPath('data.formal_clearance_issued', false);
        $this->assertSame([], $reversed->json('data.receipts'));
        $this->assertSame($receipt->receipt_number, $reversed->json('data.reversed_receipts.0.receipt_number'));
        $this->assertSame('REVERSED', $reversed->json('data.reversed_receipts.0.receipt_status'));
        $this->assertSame((string) $invoice->total_charge_amount, $reversed->json('data.reversed_receipts.0.applied_amount'));
        $this->assertArrayNotHasKey('payer_snapshot', $reversed->json('data.reversed_receipts.0'));
        $this->assertArrayNotHasKey('reference', $reversed->json('data.reversed_receipts.0'));
        $this->assertDatabaseCount('ppa_verification_events', 2);
    }

    public function test_ppa_can_view_the_issued_invoice_layout_without_recording_a_settlement_check(): void
    {
        $invoice = $this->postedInvoice();

        $pdf = $this->actingAs($this->ppa, 'sanctum')
            ->get('/api/v1/ppa/bills/'.$invoice->invoice_number.'/layout');

        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->streamedContent());
        $this->assertDatabaseCount('ppa_verification_events', 0);

        $this->actingAs($this->unscopedPpa, 'sanctum')
            ->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/layout')
            ->assertForbidden();
        $this->assertDatabaseCount('ppa_verification_events', 0);
    }

    public function test_ppa_can_search_an_official_receipt_and_view_its_issued_layout(): void
    {
        $invoice = $this->postedInvoice();
        $this->postReceipt($invoice->id, '100.00', 'PPA-OR-SEARCH-001');
        $receipt = Receipt::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();

        $found = $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/ppa/documents/'.$receipt->receipt_number)
            ->assertOk()
            ->assertJsonPath('data.document_kind', 'OFFICIAL_RECEIPT')
            ->assertJsonPath('data.receipt_number', $receipt->receipt_number)
            ->assertJsonPath('data.linked_invoices.0.invoice_number', $invoice->invoice_number);
        $this->assertArrayNotHasKey('payer_snapshot', $found->json('data'));
        $this->assertArrayNotHasKey('reference', $found->json('data.linked_invoices.0'));

        $pdf = $this->actingAs($this->ppa, 'sanctum')
            ->get('/api/v1/ppa/receipts/'.$receipt->receipt_number.'/layout');
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->streamedContent());
        $this->assertDatabaseCount('ppa_verification_events', 0);

        $this->actingAs($this->unscopedPpa, 'sanctum')
            ->getJson('/api/v1/ppa/documents/'.$receipt->receipt_number)
            ->assertForbidden();

        $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/ppa/documents/'.$invoice->invoice_number)
            ->assertOk()
            ->assertJsonPath('data.document_kind', 'INVOICE')
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number);
    }

    public function test_ppa_share_report_lists_only_fully_paid_bills_in_location_scope(): void
    {
        $paid = $this->postedInvoice();
        $this->postReceipt($paid->id, (string) $paid->total_charge_amount, 'PPA-SHARE-PAID-001');
        $partial = $this->postedInvoice();
        $this->postReceipt($partial->id, '10.00', 'PPA-SHARE-PARTIAL-001');
        $date = $paid->business_date->toDateString();

        $report = $this->actingAs($this->ppa, 'sanctum')
            ->getJson("/api/v1/ppa/share-report?date_from={$date}&date_to={$date}")
            ->assertOk();

        $numbers = collect($report->json('data.rows'))->pluck('invoice_number');
        $this->assertTrue($numbers->contains($paid->invoice_number));
        $this->assertFalse($numbers->contains($partial->invoice_number));

        $row = collect($report->json('data.rows'))->firstWhere('invoice_number', $paid->invoice_number);
        $this->assertSame((string) $paid->ppa_amount, $row['ppa_share']);
        $this->assertNotEmpty($row['receipts']);
        $this->assertArrayNotHasKey('reference', $row['receipts'][0]);
        $this->assertArrayNotHasKey('payer_snapshot', $row);

        $hidden = $this->actingAs($this->unscopedPpa, 'sanctum')
            ->getJson("/api/v1/ppa/share-report?date_from={$date}&date_to={$date}")
            ->assertOk();
        $this->assertFalse(collect($hidden->json('data.rows'))->pluck('invoice_number')->contains($paid->invoice_number));

        $this->actingAs(User::where('email', 'teller1@scipsi.test')->firstOrFail(), 'sanctum')
            ->getJson("/api/v1/ppa/share-report?date_from={$date}&date_to={$date}")
            ->assertForbidden();
    }

    public function test_ppa_scope_and_read_only_role_block_foreign_location_and_financial_mutation(): void
    {
        $invoice = $this->postedInvoice();

        $this->actingAs($this->unscopedPpa, 'sanctum')
            ->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertForbidden();
        $this->assertDatabaseCount('ppa_verification_events', 0);

        $this->actingAs($this->ppa, 'sanctum')
            ->postJson('/api/v1/receipts', $this->receiptPayload($invoice->id, '100.00', 'PPA-MUTATION-001'))
            ->assertForbidden();
        $this->actingAs($this->ppa, 'sanctum')
            ->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertForbidden();
        $this->assertDatabaseCount('receipts', 0);
    }

    protected function postedInvoice()
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $this->customer->id,
            ...$this->invoiceShipmentPayload(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ])->assertCreated();
        $invoiceId = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts/'.$invoiceId.'/post', ['expected_version' => 1])
            ->assertOk();

        return Invoice::findOrFail($invoiceId);
    }

    protected function postReceipt(int $invoiceId, string $amount, string $sourceKey): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/receipts', $this->receiptPayload($invoiceId, $amount, $sourceKey))
            ->assertOk();
    }

    /** @return array<string,mixed> */
    protected function receiptPayload(int $invoiceId, string $amount, string $sourceKey): array
    {
        return [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => $sourceKey,
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoiceId,
                'tenders' => [[
                    'type' => 'BANK_TRANSFER',
                    'status' => 'CONFIRMED',
                    'amount' => $amount,
                    'reference' => $sourceKey,
                ]],
            ]],
        ];
    }
}
