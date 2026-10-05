<?php

namespace Tests\Feature\Payments;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerUserLink;
use App\Models\DocumentSeries;
use App\Models\Invoice;
use App\Models\PaymentGroup;
use App\Models\PaymentPolicyVersion;
use App\Models\Receipt;
use App\Models\ReceiptPostingSource;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\CustomerPaymentCreditService;
use App\Services\Billing\AccountStatementService;
use App\Services\Reporting\PpaShareReportService;
use App\Services\Reporting\BillingCollectionsReportService;
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

        $replacement = $this->publishPolicy([
            'manual_instructions' => 'Use the updated receiving bank account for new payment instructions.',
        ]);
        $newGroup = $this->issueGroup($this->postedInvoice(9))->assertCreated()->json('data');
        $this->assertSame($published['id'], PaymentGroup::findOrFail($group['id'])->payment_policy_version_id);
        $this->assertSame($group['manual_instructions_snapshot'], PaymentGroup::findOrFail($group['id'])->manual_instructions_snapshot);
        $this->assertSame($replacement['id'], $newGroup['payment_policy_version_id']);
        $this->assertSame($replacement['manual_instructions'], $newGroup['manual_instructions_snapshot']);
    }

    public function test_checkout_deducts_existing_credit_from_combined_bills(): void
    {
        $this->publishPolicy();
        $first = $this->postedInvoice(10);
        $second = $this->postedInvoice(9);
        $this->fundCredit('100.00');

        $group = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-groups/manual-instruction', [
            'customer_id' => $this->customer->id,
            'allocations' => array_map(fn (Invoice $invoice): array => [
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ], [$first, $second]),
        ])->assertCreated()->json('data');
        $gross = bcadd((string) $first->total_charge_amount, (string) $second->total_charge_amount, 2);

        $this->assertSame('100.00', $group['credit_applied_amount']);
        $this->assertSame(bcsub($gross, '100.00', 2), $group['cash_due_amount']);
        $this->assertSame('0.00', app(CustomerPaymentCreditService::class)->availableForCustomer($this->admin->organization_id, $this->customer->id, 'PHP'));
        $this->assertDatabaseHas('payment_group_items', [
            'payment_group_id' => $group['id'],
            'invoice_id' => $first->id,
            'requested_amount' => bcsub((string) $first->total_charge_amount, '100.00', 2),
        ]);
        $statement = app(AccountStatementService::class)->generate($this->admin, $this->customer->id, now('Asia/Manila')->toDateString());
        $this->assertSame(bcsub($gross, '100.00', 2), (string) $statement->outstanding_total);
        $this->assertSame('100.00', (string) $statement->payment_total);
        $this->assertSame('0.00', (string) $statement->cash_applied_total);
        $collections = app(BillingCollectionsReportService::class)->build($this->admin, [
            'date_from' => now('Asia/Manila')->toDateString(),
            'date_to' => now('Asia/Manila')->toDateString(),
            'customer_id' => $this->customer->id,
        ]);
        $activity = collect($collections['payment_credit_activity_by_currency'])->firstWhere('currency', 'PHP');
        $this->assertSame('100.00', $activity['created_amount']);
        $this->assertSame('100.00', $activity['applied_amount']);
    }

    public function test_credit_only_checkout_settles_without_bank_instruction_or_receipt(): void
    {
        $this->publishPolicy();
        $invoice = $this->postedInvoice(10);
        $this->fundCredit(bcadd((string) $invoice->total_charge_amount, '25.00', 2));

        $group = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-groups/manual-instruction', [
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentGroup::METHOD_CHECK_DEPOSIT,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ])->assertCreated()->json('data');

        $this->assertSame(PaymentGroup::STATUS_SETTLED, $group['status']);
        $this->assertSame(PaymentGroup::ROUTE_CUSTOMER_CREDIT, $group['route']);
        $this->assertSame(PaymentGroup::CHECK_NOT_APPLICABLE, $group['check_clearance_status']);
        $this->assertSame('0.00', $group['cash_due_amount']);
        $this->assertNull($group['payment_deadline_at']);
        $this->assertDatabaseCount('payment_group_items', 0);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame('25.00', app(CustomerPaymentCreditService::class)->availableForCustomer($this->admin->organization_id, $this->customer->id, 'PHP'));
        $this->actingAs($this->customerUser, 'sanctum')->getJson('/api/v1/portal/bills?customer_id='.$this->customer->id)
            ->assertOk()->assertJsonFragment(['id' => $invoice->id, 'outstanding_amount' => '0.00']);
        $report = app(PpaShareReportService::class)->build($this->admin, [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
        $row = collect($report['rows'])->firstWhere('invoice_number', $invoice->invoice_number);
        $this->assertSame((string) $invoice->total_charge_amount, $row['customer_payment_credit_applied_amount']);
    }

    public function test_payment_credit_read_is_scoped_to_the_linked_customer(): void
    {
        $this->fundCredit('25.00');
        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/customers/'.$this->customer->id.'/payment-credit')
            ->assertOk()->assertJsonPath('data.available_amount', '25.00');
        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/customers/'.$this->customer->id.'/payment-credit?currency=USD')
            ->assertOk()->assertJsonPath('data.available_amount', '0.00')->assertJsonCount(0, 'data.history');
        $other = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'POLICY-OTHER-CREDIT',
            'name' => 'Other Credit Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/customers/'.$other->id.'/payment-credit')
            ->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/customers/'.$this->customer->id.'/payment-credit')
            ->assertOk()->assertJsonPath('data.available_amount', '25.00');
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

    public function test_new_policy_supersedes_open_version_and_rejects_stale_publish(): void
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

        $replacement = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload())
            ->assertCreated()->json('data');
        $published = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$replacement['id'].'/publish', [
            'expected_lock_version' => $replacement['lock_version'],
            'reason' => 'Updated manual bank instructions.',
        ])->assertOk()->assertJsonPath('data.status', 'PUBLISHED')->json('data');
        $prior = PaymentPolicyVersion::findOrFail($first['id']);
        $this->assertSame($prior->effective_to->toISOString(), PaymentPolicyVersion::findOrFail($published['id'])->effective_from->toISOString());
        $this->assertGreaterThan($first['lock_version'], $prior->lock_version);
        $this->assertDatabaseHas('audit_events', [
            'aggregate_type' => 'PAYMENT_POLICY_VERSION',
            'aggregate_id' => $prior->id,
            'event_type' => 'PAYMENT_POLICY_SUPERSEDED',
        ]);
    }

    public function test_policy_publish_still_rejects_overlap_with_a_finite_window(): void
    {
        $first = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload([
            'effective_to' => now()->addDay()->toIso8601String(),
        ]))->assertCreated()->json('data');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$first['id'].'/publish', [
            'expected_lock_version' => $first['lock_version'],
            'reason' => 'Initial finite payment policy.',
        ])->assertOk();

        $overlap = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload())
            ->assertCreated()->json('data');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$overlap['id'].'/publish', [
            'expected_lock_version' => $overlap['lock_version'],
            'reason' => 'This finite window must not overlap.',
        ])->assertStatus(422)->assertJsonValidationErrors('effective_from');
    }

    public function test_formatted_bank_instructions_keep_a_private_image_on_the_frozen_snapshot(): void
    {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $html = '<p><strong>Deposit</strong> to the account in the bank image and keep your reference number.</p>'
            .'<p><img src="data:image/png;base64,'.$png.'" alt="BDO account"></p>'
            .'<script>alert(1)</script><p><a href="javascript:alert(1)">Ignore</a></p>';

        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies', $this->policyPayload([
            'manual_instructions' => $html,
        ]))->assertCreated()->json('data');

        $this->assertStringContainsString('payment-policy-image:', $draft['manual_instructions']);
        $this->assertStringNotContainsString('data:image', $draft['manual_instructions']);
        $this->assertStringNotContainsString('<script', $draft['manual_instructions']);
        $this->assertStringNotContainsString('javascript:', $draft['manual_instructions']);
        $this->assertDatabaseCount('payment_policy_images', 1);
        preg_match('/payment-policy-image:(\d+)/', $draft['manual_instructions'], $matches);
        $imageId = (int) $matches[1];

        $this->actingAs($this->admin, 'sanctum')
            ->get('/api/v1/payment-policy-images/'.$imageId)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $published = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/payment-policies/'.$draft['id'].'/publish', [
            'expected_lock_version' => $draft['lock_version'],
            'reason' => 'Bank image instructions approved.',
        ])->assertOk()->json('data');

        $invoice = $this->postedInvoice(10);
        $group = $this->issueGroup($invoice)->assertCreated()->json('data');
        $this->assertSame($published['manual_instructions'], $group['manual_instructions_snapshot']);
        $this->assertStringContainsString('payment-policy-image:'.$imageId, $group['manual_instructions_snapshot']);

        $this->actingAs($this->customerUser, 'sanctum')
            ->get('/api/v1/payment-policy-images/'.$imageId)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $other = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Other Policy Customer',
            'email' => 'other.policy@example.test',
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        $other->roles()->attach(Role::where('name', 'Customer')->firstOrFail());
        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/payment-policy-images/'.$imageId)
            ->assertNotFound();
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

    protected function fundCredit(string $amount): void
    {
        $source = ReceiptPostingSource::create([
            'organization_id' => $this->admin->organization_id,
            'source_type' => 'MANUAL_PAYMENT_PROOF',
            'source_key' => 'credit-fixture-'.$this->customer->id,
            'payload_fingerprint' => str_repeat('b', 64),
        ]);
        $series = DocumentSeries::where('organization_id', $this->admin->organization_id)
            ->where('document_type', 'COLLECTION_RECEIPT')->firstOrFail();
        $receipt = Receipt::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => $this->customer->id,
            'series_id' => $series->id,
            'posting_source_id' => $source->id,
            'status' => 'POSTED',
            'business_date' => now()->toDateString(),
            'currency' => 'PHP',
            'payer_snapshot' => ['customer_id' => $this->customer->id],
            'cash_received_amount' => $amount,
            'applied_amount' => '0.00',
            'unapplied_amount' => $amount,
            'posted_by_user_id' => $this->admin->id,
            'posted_at' => now(),
        ]);
        $source->update(['receipt_id' => $receipt->id]);
        app(CustomerPaymentCreditService::class)->createFromReceipt($receipt, $this->admin);
    }
}
