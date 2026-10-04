<?php

namespace Tests\Feature\Periods;

use App\Models\AccountingPeriod;
use App\Models\BackdateAuthorization;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\TariffVersion;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodAndBackdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 9, 20, 10, 0, 0, 'Asia/Manila'));
        $this->seed(DatabaseSeeder::class);
        TariffVersion::whereHas('tariff', fn ($query) => $query->where('tariff_code', 'STEV_DOM'))
            ->update(['effective_from' => now()->subDays(2)]);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->teller = User::create(['organization_id' => $this->admin->organization_id, 'name' => 'Period Teller', 'email' => 'period.teller@example.test', 'password' => 'Password123!', 'status' => 'active']);
        $this->teller->roles()->attach(Role::where('name', 'Teller')->firstOrFail());
        $this->customer = Customer::create(['organization_id' => $this->admin->organization_id, 'account_number' => 'PERIOD-001', 'name' => 'Period Test Customer', 'status' => 'active', 'customer_type' => 'business']);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create(['buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Period Test Customer, Inc.', 'tin' => '111-222-333-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR', 'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'], 'contact_email' => 'periods@example.test', 'contact_phone' => '+639171111113', 'effective_from' => now()->subDay(), 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_backdated_invoice_requires_independent_one_use_authorization_and_captures_period(): void
    {
        $date = now('Asia/Manila')->subDay()->toDateString();
        $request = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/backdate-authorizations', ['document_type' => 'INVOICE', 'business_date' => $date, 'reason' => 'The verified service request arrived after the regular billing cutoff.'])->assertCreated();
        $authorizationId = $request->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/backdate-authorizations/{$authorizationId}/approve", ['decision_notes' => 'Reviewed source evidence and authorized one invoice issuance.', 'expires_at' => now()->addHour()->toIso8601String()])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $invoiceId = $this->draft($date);
        $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", ['expected_version' => 1, 'backdate_authorization_id' => $authorizationId])->assertOk();

        $invoice = Invoice::findOrFail($invoiceId);
        $authorization = BackdateAuthorization::findOrFail($authorizationId);
        $this->assertSame('POSTED', $invoice->status);
        $this->assertSame($date, $invoice->business_date->toDateString());
        $this->assertSame($authorizationId, $invoice->backdate_authorization_id);
        $this->assertSame(BackdateAuthorization::STATUS_CONSUMED, $authorization->status);
        $this->assertSame($invoice->id, $authorization->consumed_document_id);
        $this->assertNotNull($invoice->accounting_period_id);

        $secondInvoiceId = $this->draft($date);
        $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/invoices/drafts/{$secondInvoiceId}/post", ['expected_version' => 1, 'backdate_authorization_id' => $authorizationId])->assertStatus(422)->assertJsonValidationErrors('backdate_authorization_id');
        $this->assertSame('DRAFT', Invoice::findOrFail($secondInvoiceId)->status);
    }

    public function test_prior_receipt_date_cannot_issue_without_approval_and_then_consumes_its_own_authorization(): void
    {
        $invoice = Invoice::findOrFail($this->draft(now('Asia/Manila')->toDateString()));
        $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/invoices/drafts/{$invoice->id}/post", ['expected_version' => 1])->assertOk();
        $invoice = $invoice->fresh();
        $date = now('Asia/Manila')->subDay()->toDateString();
        $payload = $this->receiptPayload($invoice, $date);
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertStatus(422)->assertJsonValidationErrors('backdate_authorization_id');
        $this->assertDatabaseCount('receipts', 0);

        $authorizationId = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/backdate-authorizations', ['document_type' => 'RECEIPT', 'business_date' => $date, 'reason' => 'Verified bank settlement needs its actual prior receipt business date.'])->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/backdate-authorizations/{$authorizationId}/approve", ['decision_notes' => 'Independent receipt-date review is complete.', 'expires_at' => now()->addHour()->toIso8601String()])->assertOk();

        $payload['backdate_authorization_id'] = $authorizationId;
        $response = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/receipts', $payload)->assertOk();
        $receipt = Receipt::findOrFail($response->json('data.id'));
        $this->assertSame($date, $receipt->business_date->toDateString());
        $this->assertSame($authorizationId, $receipt->backdate_authorization_id);
        $this->assertSame(BackdateAuthorization::STATUS_CONSUMED, BackdateAuthorization::findOrFail($authorizationId)->status);
    }

    public function test_periods_do_not_overlap_and_a_closed_period_blocks_issuance(): void
    {
        $period = AccountingPeriod::where('organization_id', $this->admin->organization_id)->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/accounting-periods', ['period_code' => 'OVERLAP', 'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString()])->assertStatus(422)->assertJsonValidationErrors('starts_on');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/accounting-periods/{$period->id}/close", ['reason' => 'Month-end control completed after all accepted settlements were reconciled.'])->assertOk();

        $invoiceId = $this->draft(now('Asia/Manila')->toDateString());
        $this->actingAs($this->teller, 'sanctum')->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", ['expected_version' => 1])->assertStatus(422)->assertJsonValidationErrors('business_date');
        $this->assertSame('DRAFT', Invoice::findOrFail($invoiceId)->status);
    }

    private function draft(string $businessDate): int
    {
        return $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/invoices/drafts', ['customer_id' => $this->customer->id, 'business_date' => $businessDate, ...$this->invoiceShipmentPayload(), 'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]]])->assertCreated()->json('data.id');
    }

    private function receiptPayload(Invoice $invoice, string $businessDate): array
    {
        return ['source_type' => 'MANUAL_BANK_VERIFICATION', 'source_key' => 'PERIOD-RECEIPT-'.$invoice->id, 'customer_id' => $this->customer->id, 'business_date' => $businessDate, 'allocations' => [['invoice_id' => $invoice->id, 'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => $invoice->total_charge_amount, 'reference' => 'PERIOD-RECEIPT-'.$invoice->id]]]]];
    }
}
