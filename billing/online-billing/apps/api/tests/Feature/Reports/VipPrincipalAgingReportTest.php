<?php

namespace Tests\Feature\Reports;

use App\Models\AuditEvent;
use App\Models\CreditPolicyVersion;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerCreditAccountVersion;
use App\Models\Invoice;
use App\Models\InvoiceCreditCharge;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipPrincipalAgingReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected Customer $customer;

    protected CustomerCreditAccount $account;

    protected CreditPolicyVersion $policy;

    protected CustomerCreditAccountVersion $accountVersion;

    protected int $locationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->teller = User::where('email', 'teller1@scipsi.test')->firstOrFail();
        $this->locationId = (int) $this->admin->locations()->value('locations.id');
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'VIP-AGING-001',
            'name' => '=Formula-like VIP customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $this->account = CustomerCreditAccount::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->admin->id,
            'lock_version' => 1,
        ]);
        $this->policy = CreditPolicyVersion::create([
            'organization_id' => $this->admin->organization_id,
            'version_number' => 1,
            'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED',
            'default_credit_limit_amount' => '10000.00',
            'payment_terms_days' => 30,
            'due_date_basis' => 'CREDIT_CHARGE_DATE',
            'overdue_restriction' => 'ALLOW',
            'overdue_grace_days' => 0,
            'allow_customer_overrides' => false,
            'status' => CreditPolicyVersion::STATUS_PUBLISHED,
            'effective_from' => now('Asia/Manila')->subDay(),
            'created_by_user_id' => $this->admin->id,
            'published_by_user_id' => $this->admin->id,
            'published_at' => now('Asia/Manila')->subDay(),
            'publication_reason' => 'Synthetic reporting fixture.',
            'lock_version' => 1,
        ]);
        $this->accountVersion = CustomerCreditAccountVersion::create([
            'customer_credit_account_id' => $this->account->id,
            'version_number' => 1,
            'status' => CustomerCreditAccountVersion::STATUS_ACTIVE,
            'effective_from' => now('Asia/Manila')->subDay(),
            'created_by_user_id' => $this->admin->id,
            'reason' => 'Synthetic reporting fixture.',
            'lock_version' => 1,
        ]);
    }

    public function test_administrator_can_export_formula_safe_vip_principal_aging_with_audit_and_no_late_charges(): void
    {
        $this->creditCharge('SI-VIP-AGING-001', now('Asia/Manila')->subDays(100), now('Asia/Manila')->subDays(93));
        $asOf = now('Asia/Manila')->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/v1/reports/vip-credit-aging/export?as_of='.$asOf)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Customer account', $content);
        $this->assertStringContainsString('VIP-AGING-001', $content);
        $this->assertStringContainsString("'=Formula-like VIP customer", $content);
        $this->assertStringContainsString('91_PLUS', $content);
        $this->assertStringContainsString('1064.00', $content);

        $event = AuditEvent::where('event_type', 'VIP_PRINCIPAL_AGING_EXPORTED')->firstOrFail();
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame('credit_aging:export', $event->permission_snapshot);
        $this->assertSame($asOf, $event->business_date?->toDateString());
        $this->assertSame(1, $event->metadata['account_count']);
        $this->assertSame(1, $event->metadata['row_count']);
        $this->assertFalse($event->metadata['late_charges_included']);
        $this->assertSame(hash('sha256', $content), $event->metadata['content_sha256']);
    }

    public function test_export_is_administrator_only_even_when_a_teller_is_granted_the_named_export_permission(): void
    {
        $this->creditCharge('SI-VIP-AGING-002', now('Asia/Manila')->subDays(10), now('Asia/Manila')->subDays(3));
        $tellerRole = Role::where('name', 'Teller')->whereNull('organization_id')->firstOrFail();
        $exportPermission = Permission::where('name', 'credit_aging:export')->firstOrFail();
        $tellerRole->permissions()->syncWithoutDetaching([$exportPermission->id]);

        $this->assertTrue($this->teller->fresh()->hasPermission('credit_aging:export'));
        $this->actingAs($this->teller, 'sanctum')
            ->get('/api/v1/reports/vip-credit-aging/export')
            ->assertForbidden();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_unauthenticated_export_returns_api_unauthorized_without_a_browser_login_redirect(): void
    {
        $this->getJson('/api/v1/reports/vip-credit-aging/export')
            ->assertUnauthorized();

        $this->assertDatabaseCount('audit_events', 0);
    }

    private function creditCharge(string $invoiceNumber, Carbon $chargedAt, Carbon $dueDate): InvoiceCreditCharge
    {
        $invoice = Invoice::create([
            'organization_id' => $this->admin->organization_id,
            'location_id' => $this->locationId,
            'customer_id' => $this->customer->id,
            'invoice_number' => $invoiceNumber,
            'status' => 'POSTED',
            'business_date' => $chargedAt->toDateString(),
            'currency' => 'PHP',
            'gross_amount' => '1064.00',
            'net_amount' => '1064.00',
            'total_charge_amount' => '1064.00',
            'posted_by_user_id' => $this->admin->id,
            'posted_at' => $chargedAt,
            'lock_version' => 1,
        ]);

        return InvoiceCreditCharge::create([
            'organization_id' => $this->admin->organization_id,
            'customer_credit_account_id' => $this->account->id,
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'credit_policy_version_id' => $this->policy->id,
            'credit_policy_version_number' => $this->policy->version_number,
            'credit_account_version_id' => $this->accountVersion->id,
            'credit_account_version_number' => $this->accountVersion->version_number,
            'currency' => 'PHP',
            'charged_amount' => '1064.00',
            'payment_terms_days_snapshot' => 30,
            'due_date_basis_snapshot' => 'CREDIT_CHARGE_DATE',
            'due_date' => $dueDate->toDateString(),
            'terms_snapshot' => ['payment_terms_days' => 30, 'due_date_basis' => 'CREDIT_CHARGE_DATE'],
            'charged_at' => $chargedAt,
            'charged_business_date' => $chargedAt->toDateString(),
            'charged_by_user_id' => $this->admin->id,
        ]);
    }
}
