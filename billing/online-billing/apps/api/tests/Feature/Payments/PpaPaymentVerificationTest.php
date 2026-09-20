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
            ->assertJsonPath('data.receipts.0.applied_amount', '100.00')
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
