<?php

namespace Tests\Feature\Payments;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\ManualPaymentSubmission;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\PaymentPolicyVersion;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentProofWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customerUser;

    protected User $tellerOne;

    protected User $tellerTwo;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'PROOF-001',
            'name' => 'Payment Proof Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Payment Proof Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'proof.customer@example.test',
            'contact_phone' => '+639171111118',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
        $this->customerUser = $this->userWithRole('Portal Customer', 'portal.customer@example.test', 'Customer');
        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'owner',
            'is_active' => true,
            'linked_at' => now(),
            'approved_by_user_id' => $this->admin->id,
        ]);
        $this->tellerOne = $this->userWithRole('Teller One', 'teller.one@example.test', 'Teller');
        $this->tellerTwo = $this->userWithRole('Teller Two', 'teller.two@example.test', 'Teller');
        PaymentPolicyVersion::updateOrCreate([
            'organization_id' => $this->admin->organization_id,
            'version_number' => 1,
        ], [
            'currency' => 'PHP',
            'gateway_enabled' => false,
            'manual_instructions' => 'Deposit only to the verified SCIPSI test receiving account and retain the bank reference.',
            'manual_deadline_hours' => 24,
            'review_target_hours' => 24,
            'clearance_target_hours' => 48,
            'correction_window_hours' => 24,
            'status' => PaymentPolicyVersion::STATUS_PUBLISHED,
            'effective_from' => now()->subDay(),
            'created_by_user_id' => $this->admin->id,
            'published_by_user_id' => $this->admin->id,
            'published_at' => now()->subDay(),
            'publication_reason' => 'Synthetic payment proof test policy.',
        ]);
    }

    public function test_customer_sees_current_balances_and_submission_does_not_post_receipt_or_allow_proof_reuse(): void
    {
        $firstInvoice = $this->postedInvoice();
        $secondInvoice = $this->postedInvoice();
        $thirdInvoice = $this->postedInvoice();
        $proof = $this->paymentProof();

        $this->actingAs($this->customerUser, 'sanctum')->getJson('/api/v1/portal/bills?customer_id='.$this->customer->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $firstInvoice->id, 'outstanding_amount' => (string) $firstInvoice->total_charge_amount]);

        $payload = $this->submissionPayload([$firstInvoice, $secondInvoice], $proof);
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'SUBMITTED');
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('receipt_allocations', 0);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $this->tellerOne->id, 'type' => 'TELLER_PAYMENT']);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $this->tellerTwo->id, 'type' => 'TELLER_PAYMENT']);
        $this->assertDatabaseHas('manual_payment_submission_items', ['invoice_id' => $firstInvoice->id, 'requested_amount' => $firstInvoice->total_charge_amount]);
        $this->assertDatabaseHas('manual_payment_submission_items', ['invoice_id' => $secondInvoice->id, 'requested_amount' => $secondInvoice->total_charge_amount]);

        $duplicatePayload = $this->submissionPayload([$thirdInvoice], $proof);
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions', $duplicatePayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof_file_id');
        $this->assertDatabaseCount('manual_payment_submissions', 1);
    }

    public function test_customer_can_view_full_posted_bill_detail_by_id(): void
    {
        $invoice = $this->postedInvoice();
        $otherCustomer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'PROOF-DETAIL-OTHER',
            'name' => 'Detail Other Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $otherCustomer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Detail Other Customer, Inc.',
            'tin' => '777-888-999-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Detail Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'effective_from' => now()->subDay(), 'status' => 'active',
        ]);
        $foreignInvoice = $this->postedInvoice($otherCustomer);

        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/bills/'.$invoice->id.'?customer_id='.$this->customer->id)
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.amounts.total_charge_amount', (string) $invoice->total_charge_amount)
            ->assertJsonPath('data.buyer.name', $invoice->buyer_snapshot_name)
            ->assertJsonPath('data.shipment.vessel_name', $invoice->vessel_name)
            ->assertJsonPath('data.shipment.voyage', $invoice->voyage)
            ->assertJsonPath('data.shipment.movement_type', $invoice->movement_type)
            ->assertJsonPath('data.shipment.route_type', $invoice->route_type)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.source_attachments', [])
            ->assertJsonPath('data.timeline.bill_approved_at', $invoice->fresh()->posted_at?->toIso8601String());

        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/bills/'.$foreignInvoice->id.'?customer_id='.$this->customer->id)
            ->assertNotFound();

        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/bills/'.$invoice->id.'?customer_id='.$otherCustomer->id)
            ->assertForbidden();
    }

    public function test_customer_cannot_submit_another_customers_invoice_or_account(): void
    {
        $otherCustomer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'PROOF-OTHER',
            'name' => 'Other Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $otherCustomer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id, 'version' => 1, 'registered_name' => 'Other Customer, Inc.',
            'tin' => '444-555-666-000', 'branch_code' => '00000', 'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Other Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'effective_from' => now()->subDay(), 'status' => 'active',
        ]);
        $invoice = $this->postedInvoice($otherCustomer);
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-groups/manual-instruction', [
            'customer_id' => $otherCustomer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ])->assertForbidden();
        $this->assertDatabaseCount('manual_payment_submissions', 0);
    }

    public function test_rejection_keeps_receipt_unposted_and_new_file_version_resubmits_with_original_queue_priority(): void
    {
        $invoice = $this->postedInvoice();
        $proof = $this->paymentProof();
        $submission = $this->submit($invoice, $proof);
        $initialSubmittedAt = $submission->initial_submitted_at;
        $group = $submission->paymentGroup()->firstOrFail();
        $originalDeadline = $group->payment_deadline_at;
        $firstProofSubmittedAt = $group->first_proof_submitted_at;
        $this->assertTrue((bool) $group->first_proof_was_timely);
        $claimed = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');

        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/reject', [
            'expected_version' => $claimed['lock_version'],
            'reason' => 'The deposit slip amount is not readable.',
        ])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertDatabaseCount('receipts', 0);

        $this->addCleanProofVersion($proof, 2);
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions/'.$submission->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('data.status', 'SUBMITTED')
            ->assertJsonPath('data.resubmission_rounds', 1);
        $fresh = ManualPaymentSubmission::findOrFail($submission->id);
        $freshGroup = $fresh->paymentGroup()->firstOrFail();
        $this->assertTrue($initialSubmittedAt->equalTo($fresh->initial_submitted_at));
        $this->assertTrue($originalDeadline->equalTo($freshGroup->payment_deadline_at));
        $this->assertTrue($firstProofSubmittedAt->equalTo($freshGroup->first_proof_submitted_at));
        $this->assertNotNull($freshGroup->review_due_at);
        $this->assertSame('PROOF_SUBMITTED', $freshGroup->status);
        $this->assertSame(2, \App\Models\InAppNotification::query()->where('user_id', $this->tellerOne->id)->where('type', 'TELLER_PAYMENT')->count());
        $this->assertDatabaseCount('manual_payment_submission_proofs', 2);
        $this->assertDatabaseHas('manual_payment_submission_events', ['manual_payment_submission_id' => $submission->id, 'event_type' => 'REJECTED']);
        $this->assertDatabaseHas('manual_payment_submission_events', ['manual_payment_submission_id' => $submission->id, 'event_type' => 'RESUBMITTED']);
    }

    public function test_expired_correction_window_closes_only_the_instruction_and_preserves_the_unpaid_bill(): void
    {
        $invoice = $this->postedInvoice();
        $proof = $this->paymentProof();
        $submission = $this->submit($invoice, $proof);
        $claimed = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/reject', [
            'expected_version' => $claimed['lock_version'],
            'reason' => 'The proof did not show a traceable bank reference.',
        ])->assertOk();

        $group = $submission->paymentGroup()->firstOrFail()->fresh();
        $this->assertNotNull($group->correction_due_at);
        $group->update(['correction_due_at' => now()->subSecond()]);
        $this->addCleanProofVersion($proof, 2);

        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions/'.$submission->id.'/resubmit')
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_group_id');
        $this->assertSame('EXPIRED', $group->fresh()->status);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseHas('payment_group_events', ['payment_group_id' => $group->id, 'event_type' => 'CORRECTION_WINDOW_EXPIRED']);

        $replacement = $this->issueManualInstruction([$invoice]);
        $this->assertSame('MANUAL_INSTRUCTION_ISSUED', $replacement['status']);
        $this->actingAs($this->customerUser, 'sanctum')->getJson('/api/v1/portal/bills?customer_id='.$this->customer->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $invoice->id, 'outstanding_amount' => (string) $invoice->total_charge_amount]);
    }

    public function test_only_claimed_teller_can_approve_and_approval_posts_one_atomic_receipt(): void
    {
        $invoice = $this->postedInvoice();
        $submission = $this->submit($invoice, $this->paymentProof());
        $claimed = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $this->actingAs($this->tellerTwo, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->assertJsonPath('data', null);

        $approval = $this->approvalPayload($invoice, $claimed['lock_version'], $invoice->total_charge_amount);
        $this->actingAs($this->tellerTwo, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertForbidden();
        $response = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.receipt.status', 'POSTED');
        $receiptId = $response->json('data.receipt_id');
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('receipt_allocations', ['receipt_id' => $receiptId, 'invoice_id' => $invoice->id, 'applied_amount' => $invoice->total_charge_amount]);
        $this->assertDatabaseHas('audit_events', ['aggregate_type' => 'MANUAL_PAYMENT_SUBMISSION', 'aggregate_id' => $submission->id, 'event_type' => 'PAYMENT_PROOF_APPROVED']);

        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertOk()
            ->assertJsonPath('data.receipt_id', $receiptId);
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_teller_can_apply_approved_withholding_with_bank_proof_without_exceeding_selected_bill(): void
    {
        $invoice = $this->postedInvoice();
        $certificate = $this->certificate('100.00');
        $submission = $this->submit($invoice, $this->paymentProof());
        $claimed = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $cashAmount = bcsub((string) $invoice->total_charge_amount, '100.00', 2);
        $approval = $this->approvalPayload($invoice, $claimed['lock_version'], $cashAmount, [[
            'certificate_id' => $certificate->id,
            'amount' => '100.00',
        ]]);

        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');
        $receipt = Receipt::firstOrFail();
        $this->assertSame((string) $invoice->total_charge_amount, $receipt->applied_amount);
        $this->assertSame('100.00', $receipt->withholding_received_amount);
        $this->assertSame('0.00', $certificate->fresh()->remaining_amount);
    }

    public function test_check_deposit_requires_clearance_before_receipt_posting_and_records_each_decision(): void
    {
        $invoice = $this->postedInvoice();
        $group = $this->issueManualInstruction([$invoice], 'CHECK_DEPOSIT');
        $submissionResponse = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions', [
            'payment_group_id' => $group['id'],
            'proof_file_id' => $this->paymentProof()->id,
            'declared_reference' => 'CHECK-DEPOSIT-'.$invoice->id,
        ])->assertCreated();
        $submission = ManualPaymentSubmission::findOrFail($submissionResponse->json('data.id'));
        $claimed = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $this->assertNotNull($submission->paymentGroup()->firstOrFail()->fresh()->clearance_due_at);
        $approval = $this->approvalPayload($invoice, $claimed['lock_version'], $invoice->total_charge_amount);

        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_group_id');
        $this->assertDatabaseCount('receipts', 0);

        $checkGroup = $submission->paymentGroup()->firstOrFail()->fresh();
        $dishonored = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/check-clearance', [
            'expected_submission_version' => $claimed['lock_version'],
            'expected_payment_group_version' => $checkGroup->lock_version,
            'clearance_status' => 'DISHONORED',
            'notes' => 'Bank returned the deposited check unpaid.',
        ])->assertOk();
        $this->assertSame('DISHONORED', $dishonored->json('data.payment_group.check_clearance_status'));

        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_group_id');
        $this->assertDatabaseCount('receipts', 0);

        $clearedGroup = $submission->paymentGroup()->firstOrFail()->fresh();
        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/check-clearance', [
            'expected_submission_version' => $claimed['lock_version'],
            'expected_payment_group_version' => $clearedGroup->lock_version,
            'clearance_status' => 'CLEARED',
            'notes' => 'Bank confirmed cleared funds after reconciliation.',
        ])->assertOk()->assertJsonPath('data.payment_group.check_clearance_status', 'CLEARED');

        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/approve', $approval)
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('receipt_tenders', ['tender_type' => 'CHECK', 'status' => 'CLEARED', 'amount' => $invoice->total_charge_amount]);
        $this->assertDatabaseHas('payment_group_events', ['payment_group_id' => $checkGroup->id, 'event_type' => 'CHECK_DISHONORED']);
        $this->assertDatabaseHas('payment_group_events', ['payment_group_id' => $checkGroup->id, 'event_type' => 'CHECK_CLEARED']);
        $this->assertDatabaseHas('manual_payment_submission_events', ['manual_payment_submission_id' => $submission->id, 'event_type' => 'CHECK_CLEARED']);
    }

    public function test_manual_instruction_and_proof_rejection_queue_safe_sms_intents_only_after_committed_outcomes(): void
    {
        $this->verifiedSmsContact();
        $invoice = $this->postedInvoice();
        $group = $this->issueManualInstruction([$invoice]);

        $instructionEvent = NotificationEvent::where('event_key', 'PAYMENT_INSTRUCTIONS_ISSUED')
            ->where('event_source_type', 'payment_group')->where('event_source_id', $group['id'])->firstOrFail();
        $instructionDelivery = NotificationDelivery::where('event_id', $instructionEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $instructionDelivery->status);
        $this->assertStringNotContainsString('verified SCIPSI test receiving account', $instructionDelivery->rendered_body);
        $this->assertTrue($instructionEvent->locations()->whereKey($invoice->location_id)->exists());

        $submissionResponse = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions', [
            'payment_group_id' => $group['id'],
            'proof_file_id' => $this->paymentProof()->id,
            'declared_reference' => 'MANUAL-SMS-REJECTED-001',
        ])->assertCreated();
        $submission = ManualPaymentSubmission::findOrFail($submissionResponse->json('data.id'));
        $this->assertSame(0, NotificationEvent::where('event_key', 'PAYMENT_PROOF_SUBMITTED')->count());
        $claimed = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$submission->id.'/reject', [
            'expected_version' => $claimed['lock_version'],
            'reason' => 'The bank image does not identify the sender.',
        ])->assertOk();

        $rejectedEvent = NotificationEvent::where('event_key', 'PAYMENT_PROOF_REJECTED')
            ->where('event_source_type', 'manual_payment_submission')->where('event_source_id', $submission->id)->firstOrFail();
        $rejectedDelivery = NotificationDelivery::where('event_id', $rejectedEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $rejectedDelivery->status);
        $this->assertStringNotContainsString('MANUAL-SMS-REJECTED-001', $rejectedDelivery->rendered_body);
        $this->assertStringNotContainsString('The bank image does not identify the sender.', $rejectedDelivery->rendered_body);
        $this->assertTrue($rejectedEvent->locations()->whereKey($invoice->location_id)->exists());
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('sms_delivery_attempts', 0);
    }

    public function test_manual_posted_settlement_and_dishonored_check_queue_safe_sms_intents_without_provider_dispatch(): void
    {
        $this->verifiedSmsContact();
        $bankInvoice = $this->postedInvoice();
        $bankSubmission = $this->submit($bankInvoice, $this->paymentProof());
        $bankClaim = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $approval = $this->approvalPayload($bankInvoice, $bankClaim['lock_version'], $bankInvoice->total_charge_amount);
        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$bankSubmission->id.'/approve', $approval)
            ->assertOk();

        $settlementEvent = NotificationEvent::where('event_key', 'SETTLEMENT_POSTED')
            ->where('event_source_type', 'manual_payment_submission')->where('event_source_id', $bankSubmission->id)->firstOrFail();
        $settlementDelivery = NotificationDelivery::where('event_id', $settlementEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $settlementDelivery->status);
        $this->assertStringNotContainsString('CONFIRMED-BANK-'.$bankInvoice->id, $settlementDelivery->rendered_body);
        $this->assertStringNotContainsString($bankInvoice->total_charge_amount, $settlementDelivery->rendered_body);
        $this->assertTrue($settlementEvent->locations()->whereKey($bankInvoice->location_id)->exists());

        $checkInvoice = $this->postedInvoice();
        $checkGroup = $this->issueManualInstruction([$checkInvoice], 'CHECK_DEPOSIT');
        $checkSubmissionResponse = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions', [
            'payment_group_id' => $checkGroup['id'],
            'proof_file_id' => $this->paymentProof()->id,
            'declared_reference' => 'CHECK-SMS-001',
        ])->assertCreated();
        $checkSubmission = ManualPaymentSubmission::findOrFail($checkSubmissionResponse->json('data.id'));
        $checkClaim = $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/claim-next')
            ->assertOk()->json('data');
        $currentGroup = $checkSubmission->paymentGroup()->firstOrFail()->fresh();
        $this->actingAs($this->tellerOne, 'sanctum')->postJson('/api/v1/teller/payment-submissions/'.$checkSubmission->id.'/check-clearance', [
            'expected_submission_version' => $checkClaim['lock_version'],
            'expected_payment_group_version' => $currentGroup->lock_version,
            'clearance_status' => 'DISHONORED',
            'notes' => 'Bank returned the deposited check unpaid.',
        ])->assertOk();

        $dishonoredEvent = NotificationEvent::where('event_key', 'CHECK_DISHONORED')
            ->where('event_source_type', 'manual_payment_submission')->where('event_source_id', $checkSubmission->id)->firstOrFail();
        $dishonoredDelivery = NotificationDelivery::where('event_id', $dishonoredEvent->id)->firstOrFail();
        $this->assertSame('queued_local', $dishonoredDelivery->status);
        $this->assertStringNotContainsString('CHECK-SMS-001', $dishonoredDelivery->rendered_body);
        $this->assertStringNotContainsString('Bank returned the deposited check unpaid.', $dishonoredDelivery->rendered_body);
        $this->assertTrue($dishonoredEvent->locations()->whereKey($checkInvoice->location_id)->exists());
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseCount('sms_delivery_attempts', 0);
    }

    protected function userWithRole(string $name, string $email, string $role): User
    {
        $user = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => $name,
            'email' => $email,
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    protected function postedInvoice(?Customer $customer = null): Invoice
    {
        $customer ??= $this->customer;
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $customer->id,
            ...$this->invoiceShipmentPayload(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ])->assertCreated();
        $id = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/invoices/drafts/{$id}/post", ['expected_version' => 1])->assertOk();

        return Invoice::findOrFail($id);
    }

    protected function paymentProof(): PrivateFile
    {
        $type = DocumentType::where('code', 'BANK_DEPOSIT_SLIP')->firstOrFail();
        $file = PrivateFile::create([
            'organization_id' => $this->admin->organization_id,
            'document_type_id' => $type->id,
            'purpose' => 'PAYMENT_PROOF',
            'uploaded_by' => $this->customerUser->id,
            'owner_id' => $this->customerUser->id,
            'current_version' => 1,
            'status' => 'CLEAN',
        ]);
        $this->addCleanProofVersion($file, 1);

        return $file;
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

    protected function addCleanProofVersion(PrivateFile $file, int $version): void
    {
        PrivateFileVersion::create([
            'private_file_id' => $file->id,
            'version_number' => $version,
            'disk' => 'private',
            'file_path' => "tests/payment-proofs/{$file->id}-{$version}.pdf",
            'original_name' => "payment-proof-{$version}.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 128,
            'sha256_checksum' => hash('sha256', "payment-proof-{$file->id}-{$version}"),
            'scan_status' => 'CLEAN',
            'scan_details' => ['scanner' => 'test'],
            'uploaded_by' => $this->customerUser->id,
            'created_at' => now(),
        ]);
        $file->update(['current_version' => $version, 'status' => 'CLEAN']);
    }

    protected function submit(Invoice $invoice, PrivateFile $proof): ManualPaymentSubmission
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-submissions', $this->submissionPayload([$invoice], $proof))
            ->assertCreated();

        return ManualPaymentSubmission::findOrFail($response->json('data.id'));
    }

    /** @param array<int, Invoice> $invoices */
    protected function submissionPayload(array $invoices, PrivateFile $proof): array
    {
        $group = $this->issueManualInstruction($invoices);

        return [
            'payment_group_id' => $group['id'],
            'proof_file_id' => $proof->id,
            'declared_reference' => 'BANK-REF-'.$invoices[0]->id,
        ];
    }

    /** @param array<int, Invoice> $invoices */
    protected function issueManualInstruction(array $invoices, string $paymentMethod = 'BANK_TRANSFER'): array
    {
        return $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/payment-groups/manual-instruction', [
            'customer_id' => $this->customer->id,
            'payment_method' => $paymentMethod,
            'allocations' => array_map(fn (Invoice $invoice): array => [
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ], $invoices),
        ])->assertCreated()->json('data');
    }

    protected function approvalPayload(Invoice $invoice, int $version, string $cashAmount, array $withholding = []): array
    {
        return [
            'expected_version' => $version,
            'confirmed_reference' => 'CONFIRMED-BANK-'.$invoice->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'cash_amount' => $cashAmount,
                'withholding_applications' => $withholding,
            ]],
        ];
    }

    protected function certificate(string $amount): CustomerWithholdingCertificate
    {
        $type = DocumentType::where('code', 'BIR_2307')->firstOrFail();
        $file = PrivateFile::create([
            'organization_id' => $this->admin->organization_id,
            'document_type_id' => $type->id,
            'purpose' => 'WITHHOLDING_CERTIFICATE',
            'uploaded_by' => $this->customerUser->id,
            'owner_id' => $this->customerUser->id,
            'current_version' => 1,
            'status' => 'CLEAN',
        ]);

        return CustomerWithholdingCertificate::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => $this->customer->id,
            'certificate_no' => '2307-PROOF-001',
            'private_file_id' => $file->id,
            'reviewed_version_number' => 1,
            'payor_tin' => '111-222-333-000',
            'payor_name' => 'Payment Proof Customer',
            'payee_tin' => '000-123-456-000',
            'payee_name' => 'SCIPSI',
            'period_from' => Carbon::now()->startOfMonth(),
            'period_to' => Carbon::now()->endOfMonth(),
            'atc_code' => 'WC100',
            'income_payment_base' => '1000.00',
            'withholding_rate' => '0.0100',
            'certified_amount' => $amount,
            'allocated_amount' => '0.00',
            'remaining_amount' => $amount,
            'status' => 'APPROVED',
        ]);
    }
}
