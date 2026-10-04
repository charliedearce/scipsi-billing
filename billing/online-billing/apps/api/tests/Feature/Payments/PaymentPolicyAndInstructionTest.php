<?php

namespace Tests\Feature\Payments;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\PaymentGroup;
use App\Models\PaymentPolicyVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPolicyAndInstructionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customerUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        PaymentPolicyVersion::query()->delete();
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'POLICY-001',
            'name' => 'Payment Policy Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Payment Policy Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'policy.customer@example.test',
            'contact_phone' => '+639171111119',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
        $this->customerUser = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Policy Portal Customer',
            'email' => 'policy.customer@example.test',
            'password' => 'Password123!',
            'status' => 'active',
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
    }

    public function test_admin_publishes_versioned_manual_policy_and_customer_receives_frozen_instruction(): void
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload([
            'manual_instructions' => 'Deposit to Test Bank account 1234 and include your invoice number in the bank reference.',
            'manual_deadline_hours' => 36,
        ]))->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->json('data');

        $published = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$draft['id'].'/publish', [
            'expected_lock_version' => $draft['lock_version'],
            'reason' => 'Initial bank-payment operating instructions approved.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'PUBLISHED')
            ->json('data');

        $invoice = $this->postedInvoice(10);
        $group = $this->issueGroup($invoice)
            ->assertCreated()
            ->assertJsonPath('data.route', 'MANUAL_BANK')
            ->assertJsonPath('data.payment_policy_version_id', $published['id'])
            ->assertJsonPath('data.manual_deadline_hours_snapshot', 36)
            ->json('data');

        $this->assertDatabaseHas('payment_group_items', [
            'payment_group_id' => $group['id'],
            'invoice_id' => $invoice->id,
            'requested_amount' => $invoice->total_charge_amount,
        ]);
        $this->assertDatabaseHas('payment_group_events', [
            'payment_group_id' => $group['id'],
            'event_type' => 'MANUAL_INSTRUCTION_ISSUED',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'aggregate_type' => 'PAYMENT_GROUP',
            'aggregate_id' => $group['id'],
            'event_type' => 'MANUAL_PAYMENT_INSTRUCTION_ISSUED',
        ]);

        $portalGroups = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/payment-groups?customer_id='.$this->customer->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $group['id'])
            ->json('data');
        $this->assertSame($group['manual_instructions_snapshot'], $portalGroups[0]['manual_instructions_snapshot']);
        $this->assertSame('MANUAL_INSTRUCTION_ISSUED', $portalGroups[0]['status']);
    }

    public function test_customer_cannot_issue_instruction_without_policy_or_for_another_account(): void
    {
        $invoice = $this->postedInvoice();
        $this->issueGroup($invoice)
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_policy');

        $this->publishPolicy();
        $other = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'POLICY-OTHER',
            'name' => 'Other Account',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-groups/manual-instruction', [
            'customer_id' => $other->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ])->assertForbidden();
    }

    public function test_gateway_threshold_uses_strict_less_than_and_never_silently_falls_back_without_provider(): void
    {
        $invoice = $this->postedInvoice();
        $this->publishPolicy([
            'gateway_enabled' => true,
            'gateway_threshold_amount' => $invoice->total_charge_amount,
        ]);

        // Equal to threshold is not strictly less-than, so it remains manual.
        $this->issueGroup($invoice)->assertCreated()->assertJsonPath('data.route', PaymentGroup::ROUTE_MANUAL_BANK);

        $secondInvoice = $this->postedInvoice(9);
        $this->issueGroup($secondInvoice)
            ->assertStatus(422)
            ->assertJsonValidationErrors('allocations');
    }

    public function test_policy_publish_rejects_overlap_and_stale_publish_version(): void
    {
        $first = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload())
            ->assertCreated()->json('data');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$first['id'].'/publish', [
            'expected_lock_version' => $first['lock_version'] + 1,
            'reason' => 'Stale publish request.',
        ])->assertStatus(409);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$first['id'].'/publish', [
            'expected_lock_version' => $first['lock_version'],
            'reason' => 'Valid initial payment policy.',
        ])->assertOk();

        $overlap = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload())
            ->assertCreated()->json('data');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$overlap['id'].'/publish', [
            'expected_lock_version' => $overlap['lock_version'],
            'reason' => 'Overlapping policy should be blocked.',
        ])->assertStatus(422)->assertJsonValidationErrors('effective_from');
    }

    /** @param array<string, mixed> $override */
    protected function policyPayload(array $override = []): array
    {
        return array_merge([
            'currency' => 'PHP',
            'gateway_enabled' => false,
            'manual_instructions' => 'Deposit to the current approved bank account and retain the transaction reference for review.',
            'manual_deadline_hours' => 24,
            'review_target_hours' => 24,
            'clearance_target_hours' => 48,
            'correction_window_hours' => 24,
            'effective_from' => now()->subHour()->toIso8601String(),
        ], $override);
    }

    /** @param array<string, mixed> $override */
    protected function publishPolicy(array $override = []): array
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload($override))
            ->assertCreated()->json('data');

        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$draft['id'].'/publish', [
            'expected_lock_version' => $draft['lock_version'],
            'reason' => 'Synthetic payment policy for focused test.',
        ])->assertOk()->json('data');
    }

    protected function issueGroup(Invoice $invoice)
    {
        return $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-groups/manual-instruction', [
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ]);
    }

    protected function postedInvoice(int $quantity = 10): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $this->customer->id,
            ...$this->invoiceShipmentPayload(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => $quantity]],
        ])->assertCreated();
        $id = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts/'.$id.'/post', ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($id);
    }
}
