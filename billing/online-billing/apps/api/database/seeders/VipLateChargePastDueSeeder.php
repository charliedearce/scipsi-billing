<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\BuyerProfileVersion;
use App\Models\CreditPolicyVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerCreditAccountVersion;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\InvoiceCreditCharge;
use App\Models\LateChargeBand;
use App\Models\LateChargePolicyVersion;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\LateChargeAssessmentService;
use App\Services\Billing\LateChargePolicyService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development-only helper: seeds a VIP account with one unpaid past-due credit charge
 * that captured a published late-charge policy, then runs assessment for today.
 *
 * Not called from DatabaseSeeder. Run explicitly:
 *   php artisan db:seed --class=VipLateChargePastDueSeeder
 */
class VipLateChargePastDueSeeder extends Seeder
{
    public const ACCOUNT_NUMBER = 'VIP-LATE-SIM-001';

    public const INVOICE_NUMBER = 'SI-LATE-SIM-0001';

    public const PORTAL_EMAIL = 'vip.late@example.com';

    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        if (! $org) {
            $this->command?->error('No organization found. Run DatabaseSeeder first.');

            return;
        }

        $admin = User::query()
            ->where('organization_id', $org->id)
            ->where('email', 'admin@scipsi.test')
            ->first()
            ?? User::query()->where('organization_id', $org->id)->orderBy('id')->first();
        if (! $admin) {
            $this->command?->error('No admin user found for the organization.');

            return;
        }

        $location = Location::query()->where('organization_id', $org->id)->orderBy('id')->first();
        if (! $location) {
            $this->command?->error('No location found for the organization.');

            return;
        }

        $now = Carbon::now('Asia/Manila');
        $this->ensureOpenPeriod($org->id, $now);
        $creditPolicy = $this->ensureCreditPolicy($org->id, $admin->id, $now);
        $latePolicy = $this->ensureLateChargePolicy($org->id, $admin->id, $now);
        $customer = $this->ensureVipCustomer($org->id, $admin->id);
        $portalUser = $this->ensurePortalUser($org->id, $location->id, $customer, $admin->id);
        [$account, $profile] = $this->ensureCreditAccount($org->id, $customer->id, $admin->id, $now);
        $charge = $this->ensurePastDueCharge(
            $org->id,
            $location->id,
            $customer,
            $account,
            $profile,
            $creditPolicy,
            $latePolicy,
            $portalUser,
            $admin,
            $now,
        );

        $result = app(LateChargeAssessmentService::class)->assessOrganization(
            $org->id,
            $now->copy()->startOfDay(),
            $admin,
        );

        $this->command?->info('VIP late-charge past-due simulation ready.');
        $this->command?->line('  Customer: '.$customer->name.' ('.$customer->account_number.')');
        $this->command?->line('  Portal login: '.self::PORTAL_EMAIL.' / CustomerPassword123!');
        $this->command?->line('  Invoice: '.$charge->invoice?->invoice_number ?? self::INVOICE_NUMBER);
        $this->command?->line('  Due date: '.$charge->due_date?->toDateString().' (45 days before today)');
        $this->command?->line(sprintf(
            '  Assessment run: created=%d reused=%d held=%d skipped=%d',
            $result['created'],
            $result['reused'],
            $result['held'],
            $result['skipped'],
        ));
        $this->command?->line('  Review: Pricing & Tariffs → VIP Credit & Collections → Recent late-charge assessments');
        $this->command?->warn('  Assessments stay PENDING_ACCOUNTANT_REVIEW; no fiscal document is created.');
    }

    protected function ensureOpenPeriod(int $organizationId, Carbon $now): void
    {
        AccountingPeriod::firstOrCreate(
            [
                'organization_id' => $organizationId,
                'period_code' => $now->format('Y-m'),
            ],
            [
                'starts_on' => $now->copy()->startOfMonth()->toDateString(),
                'ends_on' => $now->copy()->endOfMonth()->toDateString(),
                'status' => 'OPEN',
                'notes' => 'Development seed period for late-charge simulation',
            ],
        );
    }

    protected function ensureCreditPolicy(int $organizationId, int $adminId, Carbon $now): CreditPolicyVersion
    {
        $existing = CreditPolicyVersion::query()
            ->where('organization_id', $organizationId)
            ->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
            ->where('effective_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $now))
            ->orderByDesc('version_number')
            ->first();
        if ($existing) {
            return $existing;
        }

        $next = ((int) CreditPolicyVersion::query()->where('organization_id', $organizationId)->max('version_number')) + 1;

        return CreditPolicyVersion::create([
            'organization_id' => $organizationId,
            'version_number' => max(1, $next),
            'currency' => 'PHP',
            'default_credit_limit_mode' => 'CAPPED',
            'default_credit_limit_amount' => '100000.00',
            'payment_terms_days' => 30,
            'due_date_basis' => 'CREDIT_CHARGE_DATE',
            'overdue_restriction' => 'BLOCK',
            'overdue_grace_days' => 0,
            'allow_customer_overrides' => true,
            'status' => CreditPolicyVersion::STATUS_PUBLISHED,
            'effective_from' => $now->copy()->subDays(60),
            'created_by_user_id' => $adminId,
            'published_by_user_id' => $adminId,
            'published_at' => $now->copy()->subDays(60),
            'publication_reason' => 'Development seed for VIP late-charge past-due simulation.',
            'lock_version' => 1,
        ]);
    }

    protected function ensureLateChargePolicy(int $organizationId, int $adminId, Carbon $now): LateChargePolicyVersion
    {
        $existing = LateChargePolicyVersion::query()
            ->where('organization_id', $organizationId)
            ->where('status', LateChargePolicyVersion::STATUS_PUBLISHED)
            ->where('enabled', true)
            ->where('currency', 'PHP')
            ->where('effective_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $now))
            ->with('bands')
            ->orderByDesc('version_number')
            ->first();
        if ($existing && $existing->bands->isNotEmpty()) {
            return $existing;
        }

        $next = ((int) LateChargePolicyVersion::query()->where('organization_id', $organizationId)->max('version_number')) + 1;
        $policy = LateChargePolicyVersion::create([
            'organization_id' => $organizationId,
            'version_number' => max(1, $next),
            'currency' => 'PHP',
            'enabled' => true,
            'allow_customer_overrides' => false,
            'grace_days' => 0,
            'basis' => LateChargePolicyVersion::BASIS_PERCENTAGE,
            'percentage_rate' => '1.5000',
            'cadence' => LateChargePolicyVersion::CADENCE_ONCE,
            'minimum_amount' => '1.00',
            'cap_amount' => '500.00',
            'rounding_mode' => LateChargePolicyVersion::ROUNDING_TRUNCATE_2,
            'allocation_priority' => 'PRINCIPAL_FIRST',
            'affects_available_credit' => false,
            'contract_reference' => 'VIP-LC-SIM-1',
            'customer_notice' => 'Late charges are separate from the original invoice.',
            'status' => LateChargePolicyVersion::STATUS_PUBLISHED,
            'effective_from' => $now->copy()->subDays(60),
            'created_by_user_id' => $adminId,
            'published_by_user_id' => $adminId,
            'published_at' => $now->copy()->subDays(60),
            'publication_reason' => 'Development seed late-charge policy for past-due simulation.',
            'lock_version' => 1,
        ]);

        LateChargeBand::create([
            'late_charge_policy_version_id' => $policy->id,
            'days_from' => 1,
            'days_to' => 30,
            'label' => '1-30',
            'sort_order' => 1,
        ]);
        LateChargeBand::create([
            'late_charge_policy_version_id' => $policy->id,
            'days_from' => 31,
            'days_to' => null,
            'label' => '31+',
            'sort_order' => 2,
        ]);

        return $policy->fresh('bands');
    }

    protected function ensureVipCustomer(int $organizationId, int $adminId): Customer
    {
        $customer = Customer::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'account_number' => self::ACCOUNT_NUMBER,
            ],
            [
                'name' => 'VIP Late Charge Simulation',
                'status' => 'active',
                'customer_type' => 'vip',
                'lock_version' => 1,
            ],
        );

        $buyerProfile = CustomerBuyerProfile::updateOrCreate(
            ['customer_id' => $customer->id],
            ['current_version' => 1, 'is_active' => true],
        );

        BuyerProfileVersion::updateOrCreate(
            [
                'buyer_profile_id' => $buyerProfile->id,
                'version' => 1,
            ],
            [
                'registered_name' => 'VIP Late Charge Simulation, Inc.',
                'tin' => '123-456-789-000',
                'branch_code' => '00000',
                'tax_classification' => 'REGULAR',
                'billing_address' => [
                    'street' => 'Makar Wharf',
                    'city' => 'General Santos City',
                    'province' => 'South Cotabato',
                ],
                'contact_email' => self::PORTAL_EMAIL,
                'contact_phone' => '+639171112233',
                'effective_from' => now('Asia/Manila')->subDays(60),
                'status' => 'active',
                'created_by_user_id' => $adminId,
                'reviewed_by_user_id' => $adminId,
            ],
        );

        return $customer;
    }

    protected function ensurePortalUser(int $organizationId, int $locationId, Customer $customer, int $adminId): User
    {
        $user = User::updateOrCreate(
            ['email' => self::PORTAL_EMAIL],
            [
                'organization_id' => $organizationId,
                'name' => 'VIP Late Charge Portal',
                'password' => Hash::make('CustomerPassword123!'),
                'status' => 'active',
                'phone' => '+639171112233',
                'lock_version' => 1,
            ],
        );

        $customerRole = Role::query()->where('name', 'Customer')->whereNull('organization_id')->first();
        if ($customerRole) {
            $user->roles()->syncWithoutDetaching([$customerRole->id]);
        }
        $user->locations()->syncWithoutDetaching([$locationId => ['is_primary' => true]]);

        CustomerUserLink::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'user_id' => $user->id,
            ],
            [
                'authority_role' => 'owner',
                'is_active' => true,
                'linked_at' => now('Asia/Manila'),
                'approved_by_user_id' => $adminId,
            ],
        );

        foreach (['email' => self::PORTAL_EMAIL, 'mobile' => '+639171112233'] as $type => $value) {
            CustomerContactPoint::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'type' => $type,
                ],
                [
                    'organization_id' => $organizationId,
                    'value' => $value,
                    'is_verified' => true,
                    'verified_at' => now('Asia/Manila'),
                    'status' => 'active',
                    'version' => 1,
                    'lock_version' => 1,
                ],
            );
        }

        return $user;
    }

    /** @return array{0: CustomerCreditAccount, 1: CustomerCreditAccountVersion} */
    protected function ensureCreditAccount(int $organizationId, int $customerId, int $adminId, Carbon $now): array
    {
        $account = CustomerCreditAccount::firstOrCreate(
            [
                'organization_id' => $organizationId,
                'customer_id' => $customerId,
            ],
            [
                'created_by_user_id' => $adminId,
                'lock_version' => 1,
            ],
        );

        $profile = CustomerCreditAccountVersion::query()
            ->where('customer_credit_account_id', $account->id)
            ->where('status', CustomerCreditAccountVersion::STATUS_ACTIVE)
            ->where('effective_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $now))
            ->orderByDesc('version_number')
            ->first();

        if (! $profile) {
            $next = ((int) CustomerCreditAccountVersion::query()
                ->where('customer_credit_account_id', $account->id)
                ->max('version_number')) + 1;
            $profile = CustomerCreditAccountVersion::create([
                'customer_credit_account_id' => $account->id,
                'version_number' => max(1, $next),
                'status' => CustomerCreditAccountVersion::STATUS_ACTIVE,
                'effective_from' => $now->copy()->subDays(60),
                'created_by_user_id' => $adminId,
                'reason' => 'Development seed ACTIVE profile for late-charge past-due simulation.',
                'lock_version' => 1,
            ]);
            $account->update(['lock_version' => $account->lock_version + 1]);
        }

        return [$account, $profile];
    }

    protected function ensurePastDueCharge(
        int $organizationId,
        int $locationId,
        Customer $customer,
        CustomerCreditAccount $account,
        CustomerCreditAccountVersion $profile,
        CreditPolicyVersion $creditPolicy,
        LateChargePolicyVersion $latePolicy,
        User $portalUser,
        User $admin,
        Carbon $now,
    ): InvoiceCreditCharge {
        $chargedAt = $now->copy()->subDays(75);
        $dueDate = $now->copy()->subDays(45);
        $snapshot = app(LateChargePolicyService::class)->snapshot($latePolicy->loadMissing('bands'));

        $invoice = Invoice::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'invoice_number' => self::INVOICE_NUMBER,
            ],
            [
                'location_id' => $locationId,
                'customer_id' => $customer->id,
                'status' => Invoice::STATUS_POSTED,
                'business_date' => $chargedAt->toDateString(),
                'currency' => 'PHP',
                'gross_amount' => '1064.00',
                'net_amount' => '1064.00',
                'tax_amount' => '0.00',
                'total_charge_amount' => '1064.00',
                'buyer_snapshot_name' => $customer->name,
                'posted_by_user_id' => $admin->id,
                'posted_at' => $chargedAt,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
                'lock_version' => 1,
            ],
        );

        $existing = InvoiceCreditCharge::query()->where('invoice_id', $invoice->id)->first();
        if ($existing) {
            $existing->update([
                'late_charge_policy_version_id' => $latePolicy->id,
                'late_charge_policy_version_number' => $latePolicy->version_number,
                'late_charge_policy_snapshot' => $snapshot,
                'charged_amount' => '1064.00',
                'due_date' => $dueDate->toDateString(),
                'charged_at' => $chargedAt,
                'charged_business_date' => $chargedAt->toDateString(),
            ]);

            return $existing->fresh('invoice');
        }

        return InvoiceCreditCharge::create([
            'organization_id' => $organizationId,
            'customer_credit_account_id' => $account->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'credit_policy_version_id' => $creditPolicy->id,
            'credit_policy_version_number' => $creditPolicy->version_number,
            'credit_account_version_id' => $profile->id,
            'credit_account_version_number' => $profile->version_number,
            'late_charge_policy_version_id' => $latePolicy->id,
            'late_charge_policy_version_number' => $latePolicy->version_number,
            'late_charge_policy_snapshot' => $snapshot,
            'currency' => 'PHP',
            'charged_amount' => '1064.00',
            'payment_terms_days_snapshot' => 30,
            'due_date_basis_snapshot' => 'CREDIT_CHARGE_DATE',
            'due_date' => $dueDate->toDateString(),
            'terms_snapshot' => [
                'credit_limit_mode' => $creditPolicy->default_credit_limit_mode,
                'credit_limit_amount' => $creditPolicy->default_credit_limit_amount,
                'payment_terms_days' => $creditPolicy->payment_terms_days,
                'due_date_basis' => $creditPolicy->due_date_basis,
                'overdue_restriction' => $creditPolicy->overdue_restriction,
                'overdue_grace_days' => $creditPolicy->overdue_grace_days,
                'currency' => $creditPolicy->currency,
            ],
            'charged_at' => $chargedAt,
            'charged_business_date' => $chargedAt->toDateString(),
            'charged_by_user_id' => $portalUser->id,
        ])->fresh('invoice');
    }
}
