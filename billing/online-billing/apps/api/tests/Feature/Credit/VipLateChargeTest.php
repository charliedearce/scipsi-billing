<?php

namespace Tests\Feature\Credit;

use App\Models\BuyerProfileVersion;
use App\Models\CreditPolicyVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerCreditAccountVersion;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\InvoiceCreditCharge;
use App\Models\LateChargeAssessment;
use App\Models\LateChargePolicyVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\LateChargeAssessmentService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VipLateChargeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customerUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = $this->vipCustomer();
        $this->customerUser = $this->userWithRole('Late Charge Portal', 'late.portal@example.test', 'Customer');
        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'owner',
            'is_active' => true,
            'linked_at' => now(),
            'approved_by_user_id' => $this->admin->id,
        ]);
        $this->publishedCreditPolicy();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/credit-accounts/'.$this->customer->id.'/versions', [
            'status' => 'ACTIVE',
            'effective_from' => now()->subDay()->toIso8601String(),
            'reason' => 'Synthetic VIP account approval.',
        ])->assertCreated();
    }

    public function test_late_charge_policy_publish_and_charge_capture_snapshot(): void
    {
        $policy = $this->publishLateChargePolicy();

        $invoice = $this->postedInvoice();
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ])->assertCreated();

        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($policy->id, $charge->late_charge_policy_version_id);
        $this->assertSame($policy->version_number, $charge->late_charge_policy_version_number);
        $this->assertSame('PERCENTAGE', $charge->late_charge_policy_snapshot['basis']);
        $this->assertFalse($charge->late_charge_policy_snapshot['bands'][0]['label'] === null);
        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_scheduler_posts_non_compounding_principal_only_assessment_and_is_idempotent(): void
    {
        $this->publishLateChargePolicy();
        $invoice = $this->postedInvoice();
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ])->assertCreated();

        $charge = InvoiceCreditCharge::where('invoice_id', $invoice->id)->firstOrFail();
        $charge->update(['due_date' => Carbon::now('Asia/Manila')->subDays(45)->toDateString()]);

        $asOf = Carbon::now('Asia/Manila')->toDateString();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-assessments/run', [
            'as_of' => $asOf,
        ])->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.reused', 0);

        $assessment = LateChargeAssessment::firstOrFail();
        $this->assertSame(LateChargeAssessment::STATUS_POSTED, $assessment->status);
        $this->assertSame(LateChargeAssessment::FISCAL_PENDING, $assessment->fiscal_mapping_status);
        $this->assertSame((string) $invoice->total_charge_amount, $assessment->principal_outstanding);
        $this->assertSame('15.96', $assessment->assessed_amount); // 1.5% of 1064.00 truncated
        $this->assertFalse($assessment->calculation_snapshot['compounding']);
        $this->assertFalse($assessment->calculation_snapshot['fiscal_document_created']);
        $this->assertFalse($assessment->calculation_snapshot['invoice_rewritten']);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('invoices', 1);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-assessments/run', [
            'as_of' => $asOf,
        ])->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.reused', 1);
        $this->assertDatabaseCount('late_charge_assessments', 1);

        Artisan::call('credit:assess-late-charges', [
            '--organization' => $this->admin->organization_id,
            '--as-of' => $asOf,
        ]);
        $this->assertDatabaseCount('late_charge_assessments', 1);
    }

    public function test_paid_principal_and_account_hold_suppress_or_hold_assessment(): void
    {
        $this->publishLateChargePolicy();
        $paid = $this->postedInvoice();
        $held = $this->postedInvoice();

        foreach ([$paid, $held] as $invoice) {
            $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
                'customer_id' => $this->customer->id,
                'allocations' => [[
                    'invoice_id' => $invoice->id,
                    'expected_invoice_lock_version' => $invoice->fresh()->lock_version,
                    'requested_amount' => $invoice->total_charge_amount,
                ]],
            ])->assertCreated();
        }

        InvoiceCreditCharge::query()->update([
            'due_date' => Carbon::now('Asia/Manila')->subDays(40)->toDateString(),
        ]);

        // Simulate full principal settlement for late-charge eligibility without involving
        // a second receivable. Zero remaining principal must skip assessment.
        $this->forceFullPrincipalSettlement($paid);

        $accountVersion = CustomerCreditAccountVersion::whereHas('account', fn ($q) => $q->where('customer_id', $this->customer->id))
            ->orderByDesc('version_number')->firstOrFail();
        $accountVersion->update(['status' => CustomerCreditAccountVersion::STATUS_HELD]);

        $result = app(LateChargeAssessmentService::class)->assessOrganization(
            (int) $this->admin->organization_id,
            Carbon::now('Asia/Manila'),
            $this->admin,
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['held']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $heldAssessment = LateChargeAssessment::where('invoice_id', $held->id)->firstOrFail();
        $this->assertSame(LateChargeAssessment::STATUS_ON_HOLD, $heldAssessment->status);
        $this->assertSame('ACCOUNT_HELD', $heldAssessment->hold_reason);
        $this->assertDatabaseMissing('late_charge_assessments', ['invoice_id' => $paid->id]);
    }

    public function test_administrator_can_waive_posted_late_charge_without_rewriting_invoice(): void
    {
        $this->publishLateChargePolicy();
        $invoice = $this->postedInvoice();
        $this->actingAs($this->customerUser, 'sanctum')->postJson('/api/v1/portal/credit-charges', [
            'customer_id' => $this->customer->id,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'expected_invoice_lock_version' => $invoice->lock_version,
                'requested_amount' => $invoice->total_charge_amount,
            ]],
        ])->assertCreated();
        InvoiceCreditCharge::where('invoice_id', $invoice->id)->update([
            'due_date' => Carbon::now('Asia/Manila')->subDays(45)->toDateString(),
        ]);

        $run = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-assessments/run', [
            'as_of' => Carbon::now('Asia/Manila')->toDateString(),
        ])->assertOk();
        $assessmentId = $run->json('data.assessment_ids.0');
        $assessment = LateChargeAssessment::findOrFail($assessmentId);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-assessments/'.$assessment->id.'/waive', [
            'expected_lock_version' => $assessment->lock_version,
            'reason' => 'Customer dispute accepted pending accountant fiscal mapping.',
        ])->assertOk()
            ->assertJsonPath('data.status', LateChargeAssessment::STATUS_WAIVED);

        $invoice->refresh();
        $this->assertSame('POSTED', $invoice->status);
        $this->assertSame((string) $invoice->total_charge_amount, (string) Invoice::findOrFail($invoice->id)->total_charge_amount);
        $this->assertDatabaseHas('late_charge_events', [
            'late_charge_assessment_id' => $assessment->id,
            'event_type' => 'LATE_CHARGE_WAIVED',
        ]);
    }

    public function test_overlapping_bands_and_unauthorized_access_are_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-policies', [
            'currency' => 'PHP',
            'grace_days' => 0,
            'basis' => 'FIXED',
            'fixed_amount' => '100.00',
            'cadence' => 'ONCE',
            'effective_from' => now()->subDay()->toIso8601String(),
            'bands' => [
                ['days_from' => 1, 'days_to' => 30, 'label' => '1-30'],
                ['days_from' => 20, 'days_to' => 60, 'label' => 'overlap'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('bands');

        $this->actingAs($this->customerUser, 'sanctum')->getJson('/api/v1/admin/late-charge-policies')
            ->assertForbidden();
    }

    protected function publishLateChargePolicy(): LateChargePolicyVersion
    {
        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-policies', [
            'currency' => 'PHP',
            'enabled' => true,
            'grace_days' => 0,
            'basis' => 'PERCENTAGE',
            'percentage_rate' => '1.5000',
            'cadence' => 'ONCE',
            'cap_amount' => '500.00',
            'minimum_amount' => '1.00',
            'rounding_mode' => 'TRUNCATE_2',
            'contract_reference' => 'VIP-LC-TEST-1',
            'customer_notice' => 'Late charges are separate from the original invoice.',
            'effective_from' => now()->subDay()->toIso8601String(),
            'bands' => [
                ['days_from' => 1, 'days_to' => 30, 'label' => '1-30'],
                ['days_from' => 31, 'days_to' => null, 'label' => '31+'],
            ],
        ])->assertCreated();

        $id = $created->json('data.id');
        $lock = $created->json('data.lock_version');

        return LateChargePolicyVersion::findOrFail(
            $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/late-charge-policies/'.$id.'/publish', [
                'expected_lock_version' => $lock,
                'reason' => 'Publish synthetic late-charge policy for automated verification.',
            ])->assertOk()->json('data.id')
        );
    }

    protected function forceFullPrincipalSettlement(Invoice $invoice): void
    {
        // Mark outstanding principal as settled for late-charge eligibility without involving
        // the receipt module's full posting path. A zero remaining principal must skip assessment.
        InvoiceCreditCharge::where('invoice_id', $invoice->id)->update([
            'charged_amount' => '0.00',
        ]);
    }

    protected function vipCustomer(): Customer
    {
        $customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'VIP-LC-001',
            'name' => 'Late Charge Customer',
            'status' => 'active',
            'customer_type' => 'vip',
        ]);
        $profile = CustomerBuyerProfile::create([
            'customer_id' => $customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Late Charge Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'late.charge@example.test',
            'contact_phone' => '+639171111120',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);

        return $customer;
    }

    protected function publishedCreditPolicy(): void
    {
        CreditPolicyVersion::create([
            'organization_id' => $this->admin->organization_id,
            'version_number' => 1,
            'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED',
            'default_credit_limit_amount' => '100000.00',
            'payment_terms_days' => 30,
            'due_date_basis' => 'CREDIT_CHARGE_DATE',
            'overdue_restriction' => 'BLOCK',
            'overdue_grace_days' => 0,
            'allow_customer_overrides' => true,
            'status' => CreditPolicyVersion::STATUS_PUBLISHED,
            'effective_from' => now()->subDay(),
            'created_by_user_id' => $this->admin->id,
            'published_by_user_id' => $this->admin->id,
            'published_at' => now()->subDay(),
            'publication_reason' => 'Synthetic test policy.',
        ]);
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

    protected function userWithRole(string $name, string $email, string $roleName): User
    {
        $user = User::create([
            'organization_id' => $this->admin->organization_id,
            'name' => $name,
            'email' => $email,
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
