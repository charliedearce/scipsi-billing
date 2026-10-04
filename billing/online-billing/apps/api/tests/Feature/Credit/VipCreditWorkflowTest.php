<?php

namespace Tests\Feature\Credit;

use App\Models\BuyerProfileVersion;
use App\Models\CreditPolicyVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerUserLink;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\InvoiceCreditCharge;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\Role;
use App\Models\User;
use App\Models\VipCreditRepaymentSubmission;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VipCreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customerUser;

    protected User $teller;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = $this->vipCustomer('VIP-001', 'VIP Credit Customer');
        $this->customerUser = $this->userWithRole('VIP Portal User', 'vip.portal@example.test', 'Customer');
        CustomerUserLink::create([
            'customer_id' => $this->customer->id, 'user_id' => $this->customerUser->id, 'authority_role' => 'owner',
            'is_active' => true, 'linked_at' => now(), 'approved_by_user_id' => $this->admin->id,
        ]);
        $this->teller = $this->userWithRole('VIP Teller', 'vip.teller@example.test', 'Teller');
        $this->publishedCreditPolicy();
        $this->configureCreditAccount();
    }

    public function test_administrator_can_delete_credit_policy_draft_but_not_published_version(): void
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-policies', [
            'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED',
            'default_credit_limit_amount' => '50000.00',
            'payment_terms_days' => 15,
            'due_date_basis' => 'CREDIT_CHARGE_DATE',
            'overdue_restriction' => 'WARN',
            'overdue_grace_days' => 0,
            'allow_customer_overrides' => true,
            'effective_from' => now('Asia/Manila')->toIso8601String(),
        ])->assertCreated()->json('data');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/v1/admin/credit-policies/'.$draft['id'], [
            'expected_lock_version' => $draft['lock_version'],
        ])->assertOk();
        $this->assertDatabaseMissing('credit_policy_versions', ['id' => $draft['id']]);

        $published = CreditPolicyVersion::where('organization_id', $this->admin->organization_id)
            ->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
            ->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/v1/admin/credit-policies/'.$published->id, [
            'expected_lock_version' => $published->lock_version,
        ])->assertStatus(422)->assertJsonValidationErrors('policy');
        $this->assertDatabaseHas('credit_policy_versions', ['id' => $published->id]);
    }

    public function test_publishing_a_later_credit_policy_closes_the_open_ended_prior_version(): void
    {
        $prior = CreditPolicyVersion::where('organization_id', $this->admin->organization_id)
            ->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
            ->where('version_number', 1)
            ->firstOrFail();
        $this->assertNull($prior->effective_to);

        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-policies', [
            'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED',
            'default_credit_limit_amount' => '200000.00',
            'payment_terms_days' => 45,
            'due_date_basis' => 'CREDIT_CHARGE_DATE',
            'overdue_restriction' => 'BLOCK',
            'overdue_grace_days' => 0,
            'allow_customer_overrides' => true,
            'effective_from' => now('Asia/Manila')->addMinute()->toIso8601String(),
        ])->assertCreated()->json('data');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-policies/'.$draft['id'].'/publish', [
            'expected_lock_version' => $draft['lock_version'],
            'reason' => 'Supersede open-ended version 1 for later terms.',
        ])->assertOk()->assertJsonPath('data.status', 'PUBLISHED');

        $prior->refresh();
        $published = CreditPolicyVersion::findOrFail($draft['id']);
        $this->assertNotNull($prior->effective_to);
        $this->assertTrue($prior->effective_to->equalTo($published->effective_from));
    }

    public function test_publishing_a_draft_with_earlier_effective_from_advances_and_closes_open_prior(): void
    {
        $prior = CreditPolicyVersion::where('organization_id', $this->admin->organization_id)
            ->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
            ->where('version_number', 1)
            ->firstOrFail();

        // Reproduce Admin UI drafts that land before the current published start.
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-policies', [
            'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED',
            'default_credit_limit_amount' => '150000.00',
            'payment_terms_days' => 30,
            'due_date_basis' => 'CREDIT_CHARGE_DATE',
            'overdue_restriction' => 'BLOCK',
            'overdue_grace_days' => 0,
            'allow_customer_overrides' => true,
            'effective_from' => $prior->effective_from->copy()->subDay()->toIso8601String(),
        ])->assertCreated()->json('data');

        $published = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-policies/'.$draft['id'].'/publish', [
            'expected_lock_version' => $draft['lock_version'],
            'reason' => 'Publish despite draft Effective from being earlier than version 1.',
        ])->assertOk()->json('data');

        $prior->refresh();
        $this->assertSame('PUBLISHED', $published['status']);
        $this->assertNotNull($prior->effective_to);
        $this->assertTrue(
            Carbon::parse($published['effective_from'])->greaterThan($prior->effective_from)
        );
        $this->assertTrue($prior->effective_to->equalTo(Carbon::parse($published['effective_from'])));
    }

    public function test_vip_charge_is_all_or_nothing_captures_terms_and_never_creates_a_receipt_or_second_charge(): void
    {
        $first = $this->postedInvoice();
        $second = $this->postedInvoice();

        $response = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id,
            'allocations' => [$this->fullAllocation($first), $this->fullAllocation($second)],
        ])->assertCreated()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.charges.0.payment_terms_days', 30);

        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('invoice_credit_charges', 2);
        $this->assertSame('2128.00', $response->json('data.exposure_amount'));
        $this->assertDatabaseHas('credit_account_events', ['event_type' => 'INVOICE_CHARGED_TO_CREDIT']);

        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id,
            'allocations' => [$this->fullAllocation($first)],
        ])->assertStatus(422)->assertJsonValidationErrors('allocations');
        $this->assertDatabaseCount('invoice_credit_charges', 2);
    }

    public function test_credit_limit_rejects_the_full_batch_without_partial_assignments_and_regular_customer_cannot_charge(): void
    {
        CreditPolicyVersion::where('organization_id', $this->admin->organization_id)->firstOrFail()->update([
            'default_credit_limit_amount' => '1000.00',
        ]);
        $first = $this->postedInvoice();
        $second = $this->postedInvoice();
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id,
            'allocations' => [$this->fullAllocation($first), $this->fullAllocation($second)],
        ])->assertStatus(422)->assertJsonValidationErrors('allocations');
        $this->assertDatabaseCount('invoice_credit_charges', 0);

        $regular = $this->vipCustomer('REG-001', 'Regular Customer', 'business');
        CustomerUserLink::create([
            'customer_id' => $regular->id, 'user_id' => $this->customerUser->id, 'authority_role' => 'viewer',
            'is_active' => true, 'linked_at' => now(), 'approved_by_user_id' => $this->admin->id,
        ]);
        $regularInvoice = $this->postedInvoice($regular);
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $regular->id,
            'allocations' => [$this->fullAllocation($regularInvoice)],
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
    }

    public function test_partial_vip_bank_repayment_posts_one_shared_receipt_and_duplicate_transfer_reference_is_rejected(): void
    {
        $invoice = $this->postedInvoice();
        $this->charge($invoice);
        $proof = $this->paymentProof('first');
        $submission = $this->submitRepayment($invoice, $proof, '500.00');
        $this->assertDatabaseCount('receipts', 0);
        $this->assertSame('SUBMITTED', $submission->status);

        $claimed = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/claim-next')
            ->assertOk()->json('data');
        $approval = [
            'expected_version' => $claimed['lock_version'],
            'confirmed_reference' => 'VIP-BANK-001',
            'allocations' => [['invoice_id' => $invoice->id, 'cash_amount' => '500.00']],
        ];
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/approve', $approval)
            ->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.receipt.status', 'POSTED');
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('receipt_allocations', ['invoice_id' => $invoice->id, 'applied_amount' => '500.00']);
        $this->assertDatabaseHas('bank_transfer_settlement_references', ['normalized_reference' => 'VIP-BANK-001']);
        $this->assertDatabaseHas('credit_account_events', ['event_type' => 'REPAYMENT_POSTED']);

        $this->actingAs($this->customerUser, 'sanctum')->getJson('/api/v1/portal/credit-account?customer_id='.$this->customer->id)
            ->assertOk()->assertJsonPath('data.exposure_amount', '564.00');

        $second = $this->postedInvoice();
        $this->charge($second);
        $secondSubmission = $this->submitRepayment($second, $this->paymentProof('second'), '100.00');
        $secondClaim = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/claim-next')->assertOk()->json('data');
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$secondSubmission->id.'/approve', [
            'expected_version' => $secondClaim['lock_version'], 'confirmed_reference' => 'VIP-BANK-001',
            'allocations' => [['invoice_id' => $second->id, 'cash_amount' => '100.00']],
        ])->assertStatus(422)->assertJsonValidationErrors('confirmed_reference');
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_vip_repayment_submission_times_keep_the_actual_utc_instant(): void
    {
        $invoice = $this->postedInvoice();
        $this->charge($invoice);

        Carbon::setTestNow('2026-10-04 01:23:45 UTC');
        try {
            $submission = $this->submitRepayment($invoice, $this->paymentProof('timestamp-first'), '100.00');
            $this->assertTrue($submission->initial_submitted_at->equalTo(Carbon::now('UTC')));
            $this->assertTrue($submission->submitted_at->equalTo(Carbon::now('UTC')));
            $portalRow = $this->actingAs($this->customerUser, 'sanctum')
                ->getJson('/api/v1/portal/credit-repayments?customer_id='.$this->customer->id)
                ->assertOk()->json('data.0');
            $this->assertTrue(Carbon::parse($portalRow['initial_submitted_at'])->equalTo(Carbon::now('UTC')));

            $claimed = $this->actingAs($this->teller, 'sanctum')
                ->postJson('/api/v1/teller/credit-repayments/claim-next')->assertOk()->json('data');
            $this->assertTrue(Carbon::parse($claimed['assigned_at'])->equalTo(Carbon::now('UTC')));
            $this->actingAs($this->teller, 'sanctum')
                ->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/reject', [
                    'expected_version' => $claimed['lock_version'], 'reason' => 'Please upload a clearer proof.',
                ])->assertOk()->assertJsonPath('data.reviewed_at', Carbon::now('UTC')->toJSON());

            Carbon::setTestNow('2026-10-04 02:23:45 UTC');
            $resubmitted = $this->actingAs($this->customerUser, 'sanctum')
                ->postJson('/api/v1/portal/credit-repayments/'.$submission->id.'/resubmit', [
                    'proof_file_id' => $this->paymentProof('timestamp-retry')->id,
                ])->assertOk()->json('data');
            $this->assertTrue(Carbon::parse($resubmitted['initial_submitted_at'])->equalTo(Carbon::parse('2026-10-04 01:23:45 UTC')));
            $this->assertTrue(Carbon::parse($resubmitted['submitted_at'])->equalTo(Carbon::now('UTC')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_held_vip_account_blocks_new_charges_but_keeps_existing_credit_repayable_and_rejection_keeps_debt(): void
    {
        $invoice = $this->postedInvoice();
        $this->charge($invoice);
        $account = CustomerCreditAccount::where('customer_id', $this->customer->id)->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-accounts/'.$this->customer->id.'/versions', [
            'expected_account_lock_version' => $account->lock_version,
            'status' => 'HELD', 'effective_from' => now()->subSecond()->toIso8601String(),
            'reason' => 'Temporary collections review.',
        ])->assertCreated();
        $newInvoice = $this->postedInvoice();
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id, 'allocations' => [$this->fullAllocation($newInvoice)],
        ])->assertStatus(422)->assertJsonValidationErrors('credit_account');

        $submission = $this->submitRepayment($invoice, $this->paymentProof('held'), '100.00');
        $claimed = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/claim-next')->assertOk()->json('data');
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/reject', [
            'expected_version' => $claimed['lock_version'], 'reason' => 'Bank image does not show the transaction reference.',
        ])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertDatabaseCount('receipts', 0);
        $this->assertSame('1064.00', $this->creditChargeOutstanding($invoice));
    }

    public function test_vip_aging_uses_captured_due_dates_and_effective_receipt_business_dates_for_historical_cutoffs(): void
    {
        $invoice = $this->postedInvoice();
        $this->charge($invoice);
        $today = now('Asia/Manila')->startOfDay();
        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->firstOrFail();
        $charge->update([
            'charged_business_date' => $today->copy()->subDays(100)->toDateString(),
            'due_date' => $today->copy()->subDays(93)->toDateString(),
        ]);

        $submission = $this->submitRepayment($invoice, $this->paymentProof('aging'), '500.00');
        $claimed = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/claim-next')->assertOk()->json('data');
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/approve', [
            'expected_version' => $claimed['lock_version'], 'confirmed_reference' => 'VIP-AGING-001',
            'allocations' => [['invoice_id' => $invoice->id, 'cash_amount' => '500.00']],
        ])->assertOk();
        Receipt::firstOrFail()->update(['business_date' => $today->copy()->addDay()->toDateString()]);

        $beforeReceipt = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/credit-aging?customer_id='.$this->customer->id.'&as_of='.$today->toDateString())
            ->assertOk()->assertJsonPath('data.currencies.0.buckets.91_PLUS', '1064.00');
        $this->assertSame('0.00', $beforeReceipt->json('data.currencies.0.items.0.applied_as_of_amount'));

        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/credit-aging?customer_id='.$this->customer->id.'&as_of='.$today->copy()->addDay()->toDateString())
            ->assertOk()->assertJsonPath('data.currencies.0.buckets.91_PLUS', '564.00')
            ->assertJsonPath('data.currencies.0.items.0.applied_as_of_amount', '500.00');
    }

    public function test_missing_due_date_is_reconciled_as_unclassified_and_ppa_keeps_on_credit_separate_from_paid(): void
    {
        $invoice = $this->postedInvoice();
        $this->charge($invoice);
        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->firstOrFail();
        $charge->update(['charged_business_date' => now('Asia/Manila')->subDay()->toDateString(), 'due_date' => null]);

        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/credit-aging?customer_id='.$this->customer->id)
            ->assertOk()->assertJsonPath('data.currencies.0.buckets.UNCLASSIFIED', '1064.00')
            ->assertJsonPath('data.currencies.0.items.0.due_date_classification', 'UNCLASSIFIED_NEEDS_TERMS_REVIEW');
        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/credit-account?customer_id='.$this->customer->id)
            ->assertOk()->assertJsonPath('data.charges.0.due_date_classification', 'UNCLASSIFIED_NEEDS_TERMS_REVIEW');

        $ppa = User::where('email', 'ppa1@ppa.gov.ph')->firstOrFail();
        $this->actingAs($ppa, 'sanctum')->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertOk()->assertJsonPath('data.settlement_status', 'UNPAID')
            ->assertJsonPath('data.credit_status', 'ON_CREDIT')
            ->assertJsonPath('data.clearance_eligibility', 'NOT_ELIGIBLE')
            ->assertJsonPath('data.formal_clearance_issued', false);

        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/ppa-clearance-policies', [
            'accept_qualifying_vip_credit' => true,
            'effective_from' => now('Asia/Manila')->subSecond()->toIso8601String(),
        ])->assertCreated()->json('data');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/ppa-clearance-policies/'.$draft['id'].'/publish', [
            'expected_lock_version' => $draft['lock_version'], 'reason' => 'Synthetic PPA credit acceptance test.',
        ])->assertOk();

        $this->actingAs($ppa, 'sanctum')->getJson('/api/v1/ppa/bills/'.$invoice->invoice_number.'/settlement')
            ->assertOk()->assertJsonPath('data.settlement_status', 'UNPAID')
            ->assertJsonPath('data.credit_status', 'ON_CREDIT')
            ->assertJsonPath('data.clearance_eligibility', 'ON_CREDIT_ACCEPTED');
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('ppa_verification_events', 2);
    }

    public function test_committed_vip_credit_and_repayment_outcomes_queue_safe_sms_intents_without_provider_dispatch(): void
    {
        $this->verifiedSmsContact();
        $invoice = $this->postedInvoice();
        $this->charge($invoice);

        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->firstOrFail();
        $creditEvent = NotificationEvent::where('event_key', 'VIP_CREDIT_ASSIGNED')
            ->where('event_source_type', 'invoice_credit_charge')->where('event_source_id', $charge->id)->firstOrFail();
        $creditDelivery = NotificationDelivery::where('event_id', $creditEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $creditDelivery->status);
        $this->assertStringContainsString($invoice->invoice_number, $creditDelivery->rendered_body);
        $this->assertStringNotContainsString('1064.00', $creditDelivery->rendered_body);
        $this->assertTrue($creditEvent->locations()->whereKey($invoice->location_id)->exists());
        $this->assertSame(0, NotificationEvent::where('event_key', 'VIP_REPAYMENT_SUBMITTED')->count());

        $submission = $this->submitRepayment($invoice, $this->paymentProof('sms-posted'), '500.00');
        $claimed = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/claim-next')->assertOk()->json('data');
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/approve', [
            'expected_version' => $claimed['lock_version'], 'confirmed_reference' => 'VIP-SMS-POSTED-001',
            'allocations' => [['invoice_id' => $invoice->id, 'cash_amount' => '500.00']],
        ])->assertOk();

        $receipt = Receipt::firstOrFail();
        $settlementEvent = NotificationEvent::where('event_key', 'SETTLEMENT_POSTED')
            ->where('event_source_type', 'vip_credit_repayment_submission')->where('event_source_id', $submission->id)->firstOrFail();
        $settlementDelivery = NotificationDelivery::where('event_id', $settlementEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $settlementDelivery->status);
        $this->assertStringContainsString($receipt->receipt_number, $settlementDelivery->rendered_body);
        $this->assertStringNotContainsString('VIP-SMS-POSTED-001', $settlementDelivery->rendered_body);
        $this->assertStringNotContainsString('500.00', $settlementDelivery->rendered_body);
        $this->assertTrue($settlementEvent->locations()->whereKey($invoice->location_id)->exists());
        $this->assertDatabaseCount('sms_delivery_attempts', 0);

        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/approve', [
            'expected_version' => $claimed['lock_version'], 'confirmed_reference' => 'VIP-SMS-POSTED-001',
            'allocations' => [['invoice_id' => $invoice->id, 'cash_amount' => '500.00']],
        ])->assertOk();
        $this->assertSame(1, NotificationEvent::where('event_key', 'SETTLEMENT_POSTED')
            ->where('event_source_type', 'vip_credit_repayment_submission')->where('event_source_id', $submission->id)->count());
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_immediate_vip_hold_and_repayment_rejection_queue_safe_sms_intents(): void
    {
        $this->verifiedSmsContact();
        $invoice = $this->postedInvoice();
        $this->charge($invoice);
        $account = CustomerCreditAccount::where('customer_id', $this->customer->id)->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-accounts/'.$this->customer->id.'/versions', [
            'expected_account_lock_version' => $account->lock_version,
            'status' => 'HELD', 'effective_from' => now()->subSecond()->toIso8601String(),
            'reason' => 'Temporary collections review.',
        ])->assertCreated();

        $holdEvent = NotificationEvent::where('event_key', 'VIP_CREDIT_ACCOUNT_HELD')
            ->where('event_source_type', 'customer_credit_account_version')->firstOrFail();
        $holdDelivery = NotificationDelivery::where('event_id', $holdEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $holdDelivery->status);
        $this->assertStringNotContainsString('Temporary collections review.', $holdDelivery->rendered_body);

        $submission = $this->submitRepayment($invoice, $this->paymentProof('sms-rejected'), '100.00');
        $this->assertSame(0, NotificationEvent::where('event_key', 'VIP_REPAYMENT_REJECTED')->count());
        $claimed = $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/claim-next')->assertOk()->json('data');
        $this->actingAs($this->teller, 'sanctum')->postJson('/api/v1/teller/credit-repayments/'.$submission->id.'/reject', [
            'expected_version' => $claimed['lock_version'], 'reason' => 'Bank image does not show the transaction reference.',
        ])->assertOk();

        $rejectedEvent = NotificationEvent::where('event_key', 'VIP_REPAYMENT_REJECTED')
            ->where('event_source_type', 'vip_credit_repayment_submission')->where('event_source_id', $submission->id)->firstOrFail();
        $rejectedDelivery = NotificationDelivery::where('event_id', $rejectedEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $rejectedDelivery->status);
        $this->assertStringNotContainsString('Bank image does not show the transaction reference.', $rejectedDelivery->rendered_body);
        $this->assertTrue($rejectedEvent->locations()->whereKey($invoice->location_id)->exists());
        $this->assertDatabaseCount('sms_delivery_attempts', 0);
        $this->assertDatabaseCount('receipts', 0);
    }

    protected function vipCustomer(string $number, string $name, string $type = 'vip'): Customer
    {
        $customer = Customer::create([
            'organization_id' => $this->admin->organization_id, 'account_number' => $number, 'name' => $name,
            'status' => 'active', 'customer_type' => $type,
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => $name.', Inc.',
            'tin' => '111-222-333-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => strtolower(str_replace(' ', '.', $number)).'@example.test', 'contact_phone' => '+639171111118',
            'effective_from' => now()->subDay(), 'status' => 'active',
        ]);

        return $customer;
    }

    protected function verifiedSmsContact(): CustomerContactPoint
    {
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

    protected function publishedCreditPolicy(): void
    {
        CreditPolicyVersion::create([
            'organization_id' => $this->admin->organization_id, 'version_number' => 1, 'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED', 'default_credit_limit_amount' => '100000.00',
            'payment_terms_days' => 30, 'due_date_basis' => 'CREDIT_CHARGE_DATE', 'overdue_restriction' => 'BLOCK',
            'overdue_grace_days' => 0, 'allow_customer_overrides' => true, 'status' => CreditPolicyVersion::STATUS_PUBLISHED,
            'effective_from' => now()->subDay(), 'created_by_user_id' => $this->admin->id,
            'published_by_user_id' => $this->admin->id, 'published_at' => now()->subDay(), 'publication_reason' => 'Synthetic test policy.',
        ]);
    }

    protected function configureCreditAccount(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-accounts/'.$this->customer->id.'/versions', [
            'status' => 'ACTIVE', 'effective_from' => now()->subDay()->toIso8601String(), 'reason' => 'Synthetic VIP account approval.',
        ])->assertCreated();
    }

    protected function postedInvoice(?Customer $customer = null): Invoice
    {
        $customer ??= $this->customer;
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $customer->id, 'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
            ...$this->invoiceShipmentPayload(),
        ])->assertCreated();
        $id = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts/'.$id.'/post', ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($id);
    }

    protected function charge(Invoice $invoice): void
    {
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id, 'allocations' => [$this->fullAllocation($invoice)],
        ])->assertCreated();
    }

    protected function fullAllocation(Invoice $invoice): array
    {
        return ['invoice_id' => $invoice->id, 'expected_invoice_lock_version' => $invoice->lock_version, 'requested_amount' => $invoice->total_charge_amount];
    }

    protected function paymentProof(string $suffix): PrivateFile
    {
        $type = DocumentType::where('code', 'BANK_DEPOSIT_SLIP')->firstOrFail();
        $file = PrivateFile::create([
            'organization_id' => $this->admin->organization_id, 'document_type_id' => $type->id, 'purpose' => 'PAYMENT_PROOF',
            'uploaded_by' => $this->customerUser->id, 'owner_id' => $this->customerUser->id, 'current_version' => 1, 'status' => 'CLEAN',
        ]);
        PrivateFileVersion::create([
            'private_file_id' => $file->id, 'version_number' => 1, 'disk' => 'private',
            'file_path' => 'tests/vip-credit/'.$suffix.'.pdf', 'original_name' => $suffix.'.pdf', 'mime_type' => 'application/pdf',
            'file_size_bytes' => 128, 'sha256_checksum' => hash('sha256', $suffix), 'scan_status' => 'CLEAN',
            'scan_details' => ['scanner' => 'test'], 'uploaded_by' => $this->customerUser->id, 'created_at' => now(),
        ]);

        return $file;
    }

    protected function submitRepayment(Invoice $invoice, PrivateFile $proof, string $amount): VipCreditRepaymentSubmission
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-repayments', [
            'customer_id' => $this->customer->id, 'proof_file_id' => $proof->id, 'declared_reference' => 'DECL-'.$invoice->id,
            'allocations' => [[
                'invoice_id' => $invoice->id, 'expected_invoice_lock_version' => $invoice->lock_version, 'requested_amount' => $amount,
            ]],
        ])->assertCreated();

        return VipCreditRepaymentSubmission::findOrFail($response->json('data.id'));
    }

    protected function creditChargeOutstanding(Invoice $invoice): string
    {
        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->firstOrFail();
        $applied = (string) ReceiptAllocation::where('invoice_id', $invoice->id)->sum('applied_amount');

        return bcsub((string) $charge->charged_amount, $applied, 2);
    }

    protected function userWithRole(string $name, string $email, string $role): User
    {
        $user = User::create(['organization_id' => $this->admin->organization_id, 'name' => $name, 'email' => $email, 'password' => 'Password123!', 'status' => 'active']);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }
}
