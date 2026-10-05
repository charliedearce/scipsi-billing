<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\DocumentRequirement;
use App\Models\DocumentSeries;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateActivation;
use App\Models\DocumentTemplateVersion;
use App\Models\DocumentType;
use App\Models\FiscalTaxRuleVersion;
use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargeBand;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Location;
use App\Models\NotificationPolicyVersion;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateVersion;
use App\Models\Organization;
use App\Models\PaymentPolicyVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Models\TaxpayerProfileVersion;
use App\Models\User;
use App\Models\Vessel;
use App\Services\Billing\LegacyTariffCatalog;
use App\Services\DocumentStudio\DocumentStudioService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Permissions Catalog
        $permissions = [
            // Identity & Users
            ['name' => 'users:read', 'category' => 'Identity', 'description' => 'View user list and details'],
            ['name' => 'users:create', 'category' => 'Identity', 'description' => 'Create new user accounts'],
            ['name' => 'users:update', 'category' => 'Identity', 'description' => 'Update user profiles and roles'],
            ['name' => 'users:suspend', 'category' => 'Identity', 'description' => 'Suspend active user accounts'],
            ['name' => 'users:activate', 'category' => 'Identity', 'description' => 'Activate suspended accounts'],
            ['name' => 'users:manage', 'category' => 'Identity', 'description' => 'Manage user credentials and session revocation'],

            // Roles & Permissions
            ['name' => 'roles:read', 'category' => 'Identity', 'description' => 'View available roles and permissions'],
            ['name' => 'roles:manage', 'category' => 'Identity', 'description' => 'Manage organization roles and their permission bundles'],
            ['name' => 'roles:assign', 'category' => 'Identity', 'description' => 'Assign roles to users'],

            // Configuration
            ['name' => 'settings:read', 'category' => 'Configuration', 'description' => 'View application settings'],
            ['name' => 'settings:update', 'category' => 'Configuration', 'description' => 'Update application settings'],
            ['name' => 'document_series:manage', 'category' => 'Billing', 'description' => 'View document number series and update prefixes for future allocations'],
            ['name' => 'periods:view', 'category' => 'Billing', 'description' => 'View accounting periods'],
            ['name' => 'periods:manage', 'category' => 'Billing', 'description' => 'Open and close accounting periods'],
            ['name' => 'backdates:view', 'category' => 'Billing', 'description' => 'View permitted backdate authorization records'],
            ['name' => 'backdates:request', 'category' => 'Billing', 'description' => 'Request a one-use backdate authorization'],
            ['name' => 'backdates:review', 'category' => 'Billing', 'description' => 'Independently approve or reject backdate authorizations'],

            // Audit & Document History (Decision W35 / P1-13)
            ['name' => 'audit:read', 'category' => 'Audit', 'description' => 'View audit logs'],
            ['name' => 'audit:export', 'category' => 'Audit', 'description' => 'Export audit events and logs'],
            ['name' => 'document_history:view', 'category' => 'Audit', 'description' => 'View document revisions and change history'],
            ['name' => 'document_history:compare', 'category' => 'Audit', 'description' => 'Compare document revision diffs'],
            ['name' => 'corrections:request', 'category' => 'Billing', 'description' => 'Request document corrections and adjustments'],
            ['name' => 'corrections:approve', 'category' => 'Billing', 'description' => 'Approve document corrections and adjustments'],
            ['name' => 'reversals:approve', 'category' => 'Receivables', 'description' => 'Approve payment and receipt reversals'],

            // VIP Credit (P1-06 / P3-08)
            ['name' => 'credit:monitor', 'category' => 'Credit', 'description' => 'Monitor customer VIP credit aging and limits'],
            ['name' => 'credit:manage', 'category' => 'Credit', 'description' => 'Manage VIP credit policies and profiles'],
            ['name' => 'credit:review_proof', 'category' => 'Credit', 'description' => 'Review VIP payment proof and credit clearances'],
            ['name' => 'portal.credit.view', 'category' => 'Credit', 'description' => 'View own configured VIP credit account and repayment history'],
            ['name' => 'portal.credit.charge', 'category' => 'Credit', 'description' => 'Charge own eligible bills to configured VIP credit'],
            ['name' => 'credit_accounts:view', 'category' => 'Credit', 'description' => 'View scoped VIP credit accounts and current exposure'],
            ['name' => 'credit_accounts:manage', 'category' => 'Credit', 'description' => 'Publish VIP credit account profile versions and overrides'],
            ['name' => 'credit_policies:view', 'category' => 'Credit', 'description' => 'View versioned VIP credit policies'],
            ['name' => 'credit_policies:manage', 'category' => 'Credit', 'description' => 'Create and publish versioned VIP credit policies'],

            // Locations
            ['name' => 'locations:read', 'category' => 'Organization', 'description' => 'View branch locations'],
            ['name' => 'locations:manage', 'category' => 'Organization', 'description' => 'Manage branch locations'],

            // Billing & Invoices
            ['name' => 'billing:read', 'category' => 'Billing', 'description' => 'View all invoices and drafts'],
            ['name' => 'billing:read_own', 'category' => 'Billing', 'description' => 'View customer own invoices'],
            ['name' => 'billing:draft', 'category' => 'Billing', 'description' => 'Create and edit invoice drafts'],
            ['name' => 'billing:post', 'category' => 'Billing', 'description' => 'Post finalized invoices'],
            ['name' => 'billing:reverse', 'category' => 'Billing', 'description' => 'Request or execute invoice reversals'],

            // Receipts & Collections
            ['name' => 'receipts:read', 'category' => 'Receivables', 'description' => 'View receipts and collections'],
            ['name' => 'receipts:read_own', 'category' => 'Receivables', 'description' => 'View customer own receipts'],
            ['name' => 'receipts:post', 'category' => 'Receivables', 'description' => 'Post official receipts and collections'],
            ['name' => 'receipts:reverse', 'category' => 'Receivables', 'description' => 'Reverse posted receipts'],

            // Payment Proofs
            ['name' => 'proofs:upload', 'category' => 'Payments', 'description' => 'Upload payment proof documents'],
            ['name' => 'proofs:review', 'category' => 'Payments', 'description' => 'Review and approve customer payment proofs'],
            ['name' => 'checks:confirm_clearance', 'category' => 'Payments', 'description' => 'Record deposited-check cleared or dishonored decisions before receipt posting'],
            ['name' => 'payment_policies:view', 'category' => 'Payments', 'description' => 'View versioned payment-route policies and instruction deadlines'],
            ['name' => 'payment_policies:manage', 'category' => 'Payments', 'description' => 'Create and publish versioned payment-route policies'],

            // PPA Verification
            ['name' => 'ppa:verify', 'category' => 'PPA', 'description' => 'Verify cargo billing and clearance payment status'],
            ['name' => 'ppa_clearance_policies:view', 'category' => 'PPA', 'description' => 'View versioned PPA credit-acceptance policies'],
            ['name' => 'ppa_clearance_policies:manage', 'category' => 'PPA', 'description' => 'Create and publish PPA credit-acceptance policies'],

            // VIP Credit Aging
            ['name' => 'credit_aging:view', 'category' => 'Receivables', 'description' => 'View current and historical VIP principal aging'],
            ['name' => 'credit_aging:export', 'category' => 'Receivables', 'description' => 'Export scoped VIP principal aging'],

            // VIP Late Charges (P3-11 / W30)
            ['name' => 'credit_late_charge_policies:view', 'category' => 'Credit', 'description' => 'View versioned VIP late-charge policies'],
            ['name' => 'credit_late_charge_policies:manage', 'category' => 'Credit', 'description' => 'Create and publish versioned VIP late-charge policies'],
            ['name' => 'credit_late_charges:view', 'category' => 'Credit', 'description' => 'View VIP late-charge assessments'],
            ['name' => 'credit_late_charges:preview', 'category' => 'Credit', 'description' => 'Preview VIP late-charge assessments without posting'],
            ['name' => 'credit_late_charges:post', 'category' => 'Credit', 'description' => 'Run or post VIP late-charge assessments'],
            ['name' => 'credit_late_charges:waive', 'category' => 'Credit', 'description' => 'Waive posted or held VIP late charges with a reason'],
            ['name' => 'credit_late_charges:reverse', 'category' => 'Credit', 'description' => 'Reverse posted VIP late charges with a reason'],

            // Customer Portal
            ['name' => 'customer:portal', 'category' => 'Customer', 'description' => 'Access customer online portal'],

            // Document Studio & Reports (Decision W28 / P2-03)
            ['name' => 'templates:read', 'category' => 'Documents', 'description' => 'View document templates and layouts'],
            ['name' => 'templates:view', 'category' => 'Documents', 'description' => 'View document templates and layouts'],
            ['name' => 'templates:draft', 'category' => 'Documents', 'description' => 'Create and edit draft document layouts'],
            ['name' => 'templates:validate', 'category' => 'Documents', 'description' => 'Validate layout geometry and fiscal compliance'],
            ['name' => 'templates:preview', 'category' => 'Documents', 'description' => 'Generate and preview rendered layout PDFs'],
            ['name' => 'templates:publish', 'category' => 'Documents', 'description' => 'Publish immutable document layouts'],
            ['name' => 'templates:activate', 'category' => 'Documents', 'description' => 'Activate and schedule document layout routes'],
            ['name' => 'templates:retire', 'category' => 'Documents', 'description' => 'Retire active document layouts'],
            ['name' => 'templates:assets:manage', 'category' => 'Documents', 'description' => 'Manage organization document branding assets'],
            ['name' => 'reports:read', 'category' => 'Reports', 'description' => 'View scoped billing and collection reports'],
            ['name' => 'reports:export', 'category' => 'Reports', 'description' => 'Export scoped billing and collection report data'],
            ['name' => 'statements:view', 'category' => 'Reports', 'description' => 'View account-statement snapshots'],
            ['name' => 'statements:generate', 'category' => 'Reports', 'description' => 'Generate immutable as-of account-statement snapshots'],
            ['name' => 'transmittals:view', 'category' => 'Reports', 'description' => 'View yellow-invoice and white-receipt transmittal snapshots'],
            ['name' => 'transmittals:generate', 'category' => 'Reports', 'description' => 'Generate immutable yellow-invoice and white-receipt transmittal snapshots'],

            // Document Types, Requirements & Private Uploads (P1-07)
            ['name' => 'documents:read', 'category' => 'Documents', 'description' => 'View document types and requirement sets'],
            ['name' => 'documents:manage', 'category' => 'Documents', 'description' => 'Manage document types and requirement sets'],
            ['name' => 'files:upload', 'category' => 'Documents', 'description' => 'Upload private files for billing, tax, and payments'],
            ['name' => 'files:view', 'category' => 'Documents', 'description' => 'View and download authorized private files'],
            ['name' => 'files:review', 'category' => 'Documents', 'description' => 'Review customer uploaded evidence across the organization'],
            ['name' => 'files:quarantine', 'category' => 'Documents', 'description' => 'Administratively quarantine unsafe files'],

            // Communications & Realtime (Decision W27 / P1-08)
            ['name' => 'conversations:read', 'category' => 'Communications', 'description' => 'View conversation threads and messages'],
            ['name' => 'conversations:create', 'category' => 'Communications', 'description' => 'Create new conversation threads'],
            ['name' => 'conversations:send', 'category' => 'Communications', 'description' => 'Send messages in conversations'],
            ['name' => 'conversations:staff_notes', 'category' => 'Communications', 'description' => 'Create and view internal staff notes in conversations'],
            ['name' => 'conversations:assign', 'category' => 'Communications', 'description' => 'Assign and reassign staff to conversations'],
            ['name' => 'notifications:read', 'category' => 'Communications', 'description' => 'View and manage in-app notifications'],

            // Transactional SMS & Customer Communications (Decision W31 / P1-09)
            ['name' => 'notification_templates:view', 'category' => 'Communications', 'description' => 'View notification templates and versions'],
            ['name' => 'notification_templates:draft', 'category' => 'Communications', 'description' => 'Create and edit draft notification templates'],
            ['name' => 'notification_templates:preview', 'category' => 'Communications', 'description' => 'Preview notification templates with synthetic data'],
            ['name' => 'notification_templates:publish', 'category' => 'Communications', 'description' => 'Publish validated notification templates'],
            ['name' => 'notification_templates:activate', 'category' => 'Communications', 'description' => 'Activate published notification templates'],
            ['name' => 'notification_policies:manage', 'category' => 'Communications', 'description' => 'Manage notification dispatch policies and quiet hours'],
            ['name' => 'sms_provider:view', 'category' => 'Communications', 'description' => 'View SMS provider health and statistics'],
            ['name' => 'sms_provider:configure', 'category' => 'Communications', 'description' => 'Configure SMS provider credentials'],
            ['name' => 'sms_provider:enable', 'category' => 'Communications', 'description' => 'Enable or disable SMS provider sends'],
            ['name' => 'sms_deliveries:view', 'category' => 'Communications', 'description' => 'View transactional SMS outbox deliveries'],
            ['name' => 'sms_deliveries:view_content', 'category' => 'Communications', 'description' => 'View restricted transactional SMS message content'],
            ['name' => 'sms_deliveries:reconcile', 'category' => 'Communications', 'description' => 'Manually trigger delivery status reconciliation'],
            ['name' => 'sms_deliveries:resend', 'category' => 'Communications', 'description' => 'Resend failed transactional SMS deliveries'],
            ['name' => 'sms_deliveries:export', 'category' => 'Communications', 'description' => 'Export SMS delivery logs'],
            ['name' => 'notification_preferences:manage', 'category' => 'Communications', 'description' => 'Manage recipient notification preferences'],

            // Customer Registration, Buyer Profile & Verified Contacts (Decision W32 / P1-10)
            ['name' => 'customer_accounts:view', 'category' => 'Customer', 'description' => 'View customer business accounts and profiles'],
            ['name' => 'customer_accounts:manage', 'category' => 'Customer', 'description' => 'Manage customer business accounts and link associations'],
            ['name' => 'buyer_profiles:view', 'category' => 'Customer', 'description' => 'View buyer profile versions and tax snapshots'],
            ['name' => 'buyer_profiles:edit', 'category' => 'Customer', 'description' => 'Edit buyer profile draft and information'],
            ['name' => 'buyer_profiles:review', 'category' => 'Customer', 'description' => 'Review and verify customer buyer profile submissions'],
            ['name' => 'customer_contacts:view', 'category' => 'Customer', 'description' => 'View customer contact points and verification statuses'],
            ['name' => 'customer_contacts:manage', 'category' => 'Customer', 'description' => 'Manage customer verified contact points'],
            ['name' => 'customer_registration:review', 'category' => 'Customer', 'description' => 'Review and approve customer self-registrations'],

            // In-App Announcements (Decision W34 / P1-12)
            ['name' => 'announcements:view', 'category' => 'Communications', 'description' => 'View active and visible in-app announcements'],
            ['name' => 'announcements:draft', 'category' => 'Communications', 'description' => 'Create and edit draft in-app announcements'],
            ['name' => 'announcements:publish', 'category' => 'Communications', 'description' => 'Publish and schedule in-app announcements'],
            ['name' => 'announcements:retire', 'category' => 'Communications', 'description' => 'Retire active in-app announcements with reason'],
            ['name' => 'announcements:history', 'category' => 'Communications', 'description' => 'View announcement revision and retirement history'],
            ['name' => 'announcements:audience', 'category' => 'Communications', 'description' => 'Manage audience targeting for in-app announcements'],

            // Tariffs & Surcharges (Decision W29 / P2-01 / P2-10)
            ['name' => 'tariffs:view', 'category' => 'Billing', 'description' => 'View tariffs and pricing versions'],
            ['name' => 'tariffs:manage', 'category' => 'Billing', 'description' => 'Manage tariffs and versioned pricing rules'],
            ['name' => 'fuel_surcharges:view', 'category' => 'Billing', 'description' => 'View fuel price observations and surcharge schedules'],
            ['name' => 'fuel_surcharges:manage', 'category' => 'Billing', 'description' => 'Manage fuel price observations and surcharge policy schedules'],

            // Fiscal Compliance & Structured Data (P2-06)
            ['name' => 'fiscal:read', 'category' => 'Fiscal', 'description' => 'View fiscal issuer profile, tax rules, and structured data'],
            ['name' => 'fiscal:manage', 'category' => 'Fiscal', 'description' => 'Manage taxpayer profile versions and fiscal tax rules'],

            // Bill Claims & Walk-in Invoicing (Decision W25 / P2-09)
            ['name' => 'bill_claims:review', 'category' => 'Billing', 'description' => 'Review and decide customer bill claim requests and link walk-in invoices'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // 2. Seed Organization
        $org = Organization::firstOrCreate(
            ['code' => 'SCIPSI'],
            ['name' => 'South Cotabato Integrated Port Services, Inc.', 'is_active' => true]
        );

        $this->seedVessels($org);

        // 3. Seed Primary Location
        $loc = Location::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'GENSAN'],
            [
                'name' => 'Makar Wharf, General Santos City',
                'address' => 'Port Area, Makar Wharf, General Santos City, South Cotabato',
                'is_active' => true,
            ]
        );

        // 4. Seed 4 Base Roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'Administrator', 'organization_id' => null],
            ['label' => 'System Administrator', 'is_system' => true]
        );
        $adminRole->permissions()->sync(Permission::pluck('id'));

        $tellerRole = Role::firstOrCreate(
            ['name' => 'Teller', 'organization_id' => null],
            ['label' => 'Billing Encoder and Cashier', 'is_system' => true]
        );
        $tellerPermissions = Permission::whereIn('name', [
            'users:read', 'roles:read', 'locations:read', 'settings:read',
            'billing:read', 'billing:draft', 'billing:post',
            'periods:view', 'backdates:view', 'backdates:request',
            'receipts:read', 'receipts:post', 'proofs:review', 'checks:confirm_clearance', 'reports:read', 'reports:export',
            'statements:view', 'statements:generate',
            'transmittals:view', 'transmittals:generate',
            'document_history:view', 'corrections:request', 'credit:monitor', 'credit:review_proof', 'credit_accounts:view', 'credit_aging:view',
            'credit_late_charges:view',
            'documents:read', 'files:upload', 'files:view', 'files:review',
            'conversations:read', 'conversations:create', 'conversations:send',
            'conversations:staff_notes', 'conversations:assign', 'notifications:read',
            'sms_deliveries:view',
            'customer_accounts:view', 'buyer_profiles:view', 'customer_contacts:view',
            'announcements:view',
            'tariffs:view', 'fuel_surcharges:view',
            'bill_claims:review',
        ])->pluck('id');
        $tellerRole->permissions()->sync($tellerPermissions);

        $ppaRole = Role::firstOrCreate(
            ['name' => 'PPA user', 'organization_id' => null],
            ['label' => 'Philippine Ports Authority Officer', 'is_system' => true]
        );
        $ppaPermissions = Permission::whereIn('name', [
            'ppa:verify', 'billing:read', 'receipts:read', 'locations:read',
            'document_history:view', 'document_history:compare',
            'documents:read', 'files:view', 'notifications:read',
            'announcements:view',
        ])->pluck('id');
        $ppaRole->permissions()->sync($ppaPermissions);

        $customerRole = Role::firstOrCreate(
            ['name' => 'Customer', 'organization_id' => null],
            ['label' => 'Registered Customer / Port Client', 'is_system' => true]
        );
        $customerPermissions = Permission::whereIn('name', [
            'customer:portal', 'billing:read_own', 'receipts:read_own', 'proofs:upload', 'portal.credit.view', 'portal.credit.charge',
            'documents:read', 'files:upload', 'files:view',
            'conversations:read', 'conversations:create', 'conversations:send', 'notifications:read',
            'notification_preferences:manage',
            'buyer_profiles:view', 'buyer_profiles:edit', 'customer_contacts:view', 'customer_contacts:manage',
            'announcements:view',
        ])->pluck('id');
        $customerRole->permissions()->sync($customerPermissions);

        // 5. Seed Initial System Administrator User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@scipsi.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('AdminPassword123!'),
                'organization_id' => $org->id,
                'status' => 'active',
                'phone' => '+639170000001',
                'lock_version' => 1,
            ]
        );

        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
        $adminUser->locations()->syncWithoutDetaching([$loc->id => ['is_primary' => true]]);

        // --- Sample Teller Users ---
        $teller1 = User::firstOrCreate(
            ['email' => 'teller1@scipsi.test'],
            [
                'name' => 'Maria Santos',
                'password' => Hash::make('TellerPassword123!'),
                'organization_id' => $org->id,
                'status' => 'active',
                'phone' => '+639170000010',
                'lock_version' => 1,
            ]
        );
        $teller1->roles()->syncWithoutDetaching([$tellerRole->id]);
        $teller1->locations()->syncWithoutDetaching([$loc->id => ['is_primary' => true]]);

        $teller2 = User::firstOrCreate(
            ['email' => 'teller2@scipsi.test'],
            [
                'name' => 'Jose Reyes',
                'password' => Hash::make('TellerPassword123!'),
                'organization_id' => $org->id,
                'status' => 'active',
                'phone' => '+639170000011',
                'lock_version' => 1,
            ]
        );
        $teller2->roles()->syncWithoutDetaching([$tellerRole->id]);
        $teller2->locations()->syncWithoutDetaching([$loc->id => ['is_primary' => true]]);

        // --- Sample PPA Users ---
        $ppa1 = User::firstOrCreate(
            ['email' => 'ppa1@ppa.gov.ph'],
            [
                'name' => 'Ricardo Dela Cruz',
                'password' => Hash::make('PpaPassword123!'),
                'organization_id' => $org->id,
                'status' => 'active',
                'phone' => '+639170000020',
                'lock_version' => 1,
            ]
        );
        $ppa1->roles()->syncWithoutDetaching([$ppaRole->id]);
        $ppa1->locations()->syncWithoutDetaching([$loc->id => ['is_primary' => true]]);

        $ppa2 = User::firstOrCreate(
            ['email' => 'ppa2@ppa.gov.ph'],
            [
                'name' => 'Luzviminda Flores',
                'password' => Hash::make('PpaPassword123!'),
                'organization_id' => $org->id,
                'status' => 'active',
                'phone' => '+639170000021',
                'lock_version' => 1,
            ]
        );
        $ppa2->roles()->syncWithoutDetaching([$ppaRole->id]);
        $ppa2->locations()->syncWithoutDetaching([$loc->id => ['is_primary' => true]]);

        // --- Sample Customer User ---
        $customer1 = User::firstOrCreate(
            ['email' => 'customer1@example.com'],
            [
                'name' => 'Andres Shipping Corp.',
                'password' => Hash::make('CustomerPassword123!'),
                'organization_id' => $org->id,
                'status' => 'active',
                'phone' => '+639170000030',
                'lock_version' => 1,
            ]
        );
        $customer1->roles()->syncWithoutDetaching([$customerRole->id]);
        $customer1->locations()->syncWithoutDetaching([$loc->id => ['is_primary' => true]]);

        $customerAccount = Customer::updateOrCreate(
            ['account_number' => 'SCIPSI-DEMO-0001'],
            [
                'organization_id' => $org->id,
                'name' => 'Andres Shipping Corp.',
                'status' => 'active',
                'customer_type' => 'business',
                'lock_version' => 1,
            ]
        );

        CustomerUserLink::updateOrCreate(
            [
                'customer_id' => $customerAccount->id,
                'user_id' => $customer1->id,
            ],
            [
                'authority_role' => 'owner',
                'is_active' => true,
                'linked_at' => now(),
                'approved_by_user_id' => $adminUser->id,
            ]
        );

        $buyerProfile = CustomerBuyerProfile::updateOrCreate(
            ['customer_id' => $customerAccount->id],
            [
                'current_version' => 1,
                'is_active' => true,
            ]
        );

        BuyerProfileVersion::updateOrCreate(
            [
                'buyer_profile_id' => $buyerProfile->id,
                'version' => 1,
            ],
            [
                'registered_name' => 'Andres Shipping Corp.',
                'tin' => '000-000-000-000',
                'branch_code' => '00000',
                'tax_classification' => 'REGULAR',
                'billing_address' => [
                    'street' => 'Makar Wharf',
                    'city' => 'General Santos City',
                    'province' => 'South Cotabato',
                ],
                'contact_email' => $customer1->email,
                'contact_phone' => $customer1->phone,
                'effective_from' => now(),
                'status' => 'active',
                'created_by_user_id' => $adminUser->id,
                'reviewed_by_user_id' => $adminUser->id,
            ]
        );

        foreach ([
            'email' => $customer1->email,
            'mobile' => $customer1->phone,
        ] as $type => $value) {
            CustomerContactPoint::updateOrCreate(
                [
                    'customer_id' => $customerAccount->id,
                    'user_id' => $customer1->id,
                    'type' => $type,
                ],
                [
                    'organization_id' => $org->id,
                    'value' => $value,
                    'is_verified' => true,
                    'verified_at' => now(),
                    'status' => 'active',
                    'version' => 1,
                    'lock_version' => 1,
                ]
            );
        }

        // 6. Seed Default Named Document Types (W21 / P1-07)
        $docTypes = [
            [
                'code' => 'BILL_OF_LADING',
                'name' => 'Bill of Lading / Sea Waybill',
                'description' => 'Official shipping cargo document detailing consignment quantities and port of origin.',
                'purpose' => 'BILLING_SUPPORT',
                'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_file_size_kb' => 10240,
                'max_files' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'CARGO_MANIFEST',
                'name' => 'Inward/Outward Foreign Cargo Manifest',
                'description' => 'Customs and port clearance manifest of cargo handled at the wharf.',
                'purpose' => 'BILLING_SUPPORT',
                'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_file_size_kb' => 15360,
                'max_files' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'BIR_2307',
                'name' => 'BIR Form 2307 (Certificate of Creditable Tax Withheld)',
                'description' => 'Official BIR certificate supporting withholding tax deductions against billing.',
                'purpose' => 'WITHHOLDING_CERTIFICATE',
                'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_file_size_kb' => 5120,
                'max_files' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'TAX_EXEMPTION_CERT',
                'name' => 'Tax Exemption / Zero-Rating Certificate',
                'description' => 'Government agency (PEZA, BOI, Diplomatic) zero-rating or VAT exemption ruling.',
                'purpose' => 'EXEMPTION_EVIDENCE',
                'allowed_mime_types' => ['application/pdf'],
                'max_file_size_kb' => 5120,
                'max_files' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'BANK_DEPOSIT_SLIP',
                'name' => 'Bank Deposit Slip / Fund Transfer Advice',
                'description' => 'Official bank transaction proof for manual settlement.',
                'purpose' => 'PAYMENT_PROOF',
                'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_file_size_kb' => 5120,
                'max_files' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'PROFILE_AVATAR',
                'name' => 'Portal Profile Picture',
                'description' => 'Optional customer portal profile photograph (JPEG/PNG/WebP).',
                'purpose' => 'PROFILE_AVATAR',
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_file_size_kb' => 2048,
                'max_files' => 1,
                'is_active' => true,
            ],
        ];

        $seededTypes = [];
        foreach ($docTypes as $item) {
            $seededTypes[$item['code']] = DocumentType::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $item['code']],
                $item
            );
        }

        // 7. Seed Default Document Requirements for Services
        if (isset($seededTypes['BILL_OF_LADING'])) {
            DocumentRequirement::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'service_type' => 'GENERAL',
                    'document_type_id' => $seededTypes['BILL_OF_LADING']->id,
                ],
                [
                    'is_required' => true,
                    'version' => 1,
                    'lock_version' => 1,
                ]
            );
        }

        if (isset($seededTypes['CARGO_MANIFEST'])) {
            DocumentRequirement::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'service_type' => 'CARGO_HANDLING',
                    'document_type_id' => $seededTypes['CARGO_MANIFEST']->id,
                ],
                [
                    'is_required' => true,
                    'version' => 1,
                    'lock_version' => 1,
                ]
            );
        }

        // 8. Seed Default Notification Templates & Policies (Decision W31 / P1-09)
        $defaultTemplates = [
            [
                'code' => 'BILLING_REQUEST_QUEUED',
                'name' => 'Billing Request Queued Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your billing request has been queued with reference {{ reference_no }} (Ticket: {{ queue_ticket }}). Thank you, {{ org_name }}.',
            ],
            [
                'code' => 'BILLING_REQUEST_CORRECTION_REQUIRED',
                'name' => 'Billing Request Correction Required Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your billing request {{ reference_no }} requires document correction. Please log in to your portal account to review remarks. {{ org_name }}.',
            ],
            [
                'code' => 'BILLING_REQUEST_CANCELLED',
                'name' => 'Billing Request Cancelled Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your billing request {{ reference_no }} was cancelled ({{ action_label }}). Please sign in to your portal account for details. {{ org_name }}.',
            ],
            [
                'code' => 'INVOICE_ARTIFACT_READY',
                'name' => 'Invoice Ready Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your official invoice {{ reference_no }} is ready. Please log in to your portal account to review. {{ org_name }}.',
            ],
            [
                'code' => 'RECEIPT_ARTIFACT_READY',
                'name' => 'Collection Receipt Ready Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your collection receipt {{ reference_no }} is ready. Please sign in to your portal account to review. {{ org_name }}.',
            ],
            [
                'code' => 'RECEIPT_REVERSED',
                'name' => 'Collection Receipt Reversal Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, {{ action_label }} for {{ reference_no }} was recorded on {{ date_formatted }}. Please sign in to your portal account to review. {{ org_name }}.',
            ],
            [
                'code' => 'BILL_CLAIM_CODE_ISSUED',
                'name' => 'Bill Claim Verification Code',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your verification code for bill claim {{ reference_no }} is {{ claim_code }}. Valid for 15 minutes. Do not share this code. {{ org_name }}.',
            ],
            [
                'code' => 'TAX_EVIDENCE_APPROVED',
                'name' => 'Tax Evidence Approved Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your submitted tax evidence has been approved. Please log in to your portal account for details. {{ org_name }}.',
            ],
            [
                'code' => 'TAX_EVIDENCE_CORRECTION_REQUIRED',
                'name' => 'Tax Evidence Correction Required Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your submitted tax evidence requires correction. Please log in to your portal account to review remarks. {{ org_name }}.',
            ],
            [
                'code' => 'TAX_EVIDENCE_REJECTED',
                'name' => 'Tax Evidence Rejected Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your submitted tax evidence has been reviewed and rejected. Please log in to your portal account for details. {{ org_name }}.',
            ],
            [
                'code' => 'TAX_EVIDENCE_REVOKED',
                'name' => 'Tax Evidence Revoked Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, a previously submitted tax document has been revoked. Please log in to your portal account for details. {{ org_name }}.',
            ],
            [
                'code' => 'TAX_EVIDENCE_EXPIRED',
                'name' => 'Tax Evidence Expired Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your tax evidence has expired. Please log in to your portal account to file a renewal with updated validity. {{ org_name }}.',
            ],
            [
                'code' => 'PAYMENT_INSTRUCTIONS_ISSUED',
                'name' => 'Payment Instructions Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, payment instructions for {{ reference_no }} have been issued. Valid until {{ date_formatted }}. Please sign in to view details. {{ org_name }}.',
            ],
            [
                'code' => 'SETTLEMENT_POSTED',
                'name' => 'Settlement Confirmed Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, settlement for {{ reference_no }} has been posted on {{ date_formatted }}. Check portal for details. {{ org_name }}.',
            ],
            [
                'code' => 'VIP_CREDIT_ASSIGNED',
                'name' => 'VIP Credit Term Assigned Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, credit terms have been assigned for {{ reference_no }}. Due date: {{ date_formatted }}. {{ org_name }}.',
            ],
            [
                'code' => 'VIP_CREDIT_ACCOUNT_HELD',
                'name' => 'VIP Credit Account Hold Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your VIP credit account is unavailable for new charges. Existing bills remain repayable. Please sign in for details. {{ org_name }}.',
            ],
            [
                'code' => 'VIP_REPAYMENT_REJECTED',
                'name' => 'VIP Repayment Proof Review Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your VIP repayment proof needs correction. Please sign in to review the teller remarks. {{ org_name }}.',
            ],
            [
                'code' => 'PAYMENT_PROOF_REJECTED',
                'name' => 'Payment Proof Review Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your payment proof needs correction. Please sign in to review the teller remarks. {{ org_name }}.',
            ],
            [
                'code' => 'CHECK_DISHONORED',
                'name' => 'Deposited Check Follow-up Notice',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body' => 'Hello {{ recipient_name }}, your deposited check needs follow-up. Please sign in to review the next steps. {{ org_name }}.',
            ],
        ];

        foreach ($defaultTemplates as $tplData) {
            $tpl = NotificationTemplate::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $tplData['code'], 'channel' => 'sms'],
                [
                    'name' => $tplData['name'],
                    'template_class' => $tplData['template_class'],
                    'current_version' => 1,
                    'is_active' => true,
                ]
            );

            $tplVersion = NotificationTemplateVersion::firstOrCreate(
                ['template_id' => $tpl->id, 'version' => 1],
                [
                    'body_template' => $tplData['body'],
                    'allowed_variables' => SmsTemplateService::ALLOWED_VARIABLES,
                    'status' => 'active',
                    'published_at' => now(),
                    'activated_at' => now(),
                    'published_by_user_id' => $adminUser->id,
                    'activated_by_user_id' => $adminUser->id,
                ]
            );

            NotificationPolicyVersion::firstOrCreate(
                ['organization_id' => $org->id, 'event_key' => $tplData['code'], 'version' => 1],
                [
                    'template_id' => $tpl->id,
                    'template_version_id' => $tplVersion->id,
                    'is_enabled' => true,
                    'priority' => 'normal',
                    'allowed_channel' => 'sms',
                    'quiet_hours_policy' => [
                        'start' => '21:00',
                        'end' => '07:00',
                        'enforce' => ($tplData['template_class'] === 'OPERATIONAL_REMINDER'),
                    ],
                    'effective_from' => now(),
                    'updated_by_user_id' => $adminUser->id,
                ]
            );
        }

        // 9. Seed Default Tariffs (Decision W29 / P2-01 / P2-10)
        $defaultTariffs = [
            [
                'code' => 'ARR_DOM',
                'name' => 'Arrastre - Domestic Cargo',
                'service_type' => 'ARRASTRE',
                'route_type' => 'DOMESTIC',
                'unit_of_measure' => 'REV_TON',
                'rate' => '125.5000',
                'tax_treatment_key' => 'VATABLE',
                'ppa_share_applicability' => 'APPLICABLE',
                'ppa_share_rate' => '0.1000',
                'fuel_surcharge_applicability' => 'APPLICABLE',
            ],
            [
                'code' => 'STEV_DOM',
                'name' => 'Stevedoring - Domestic Cargo',
                'service_type' => 'STEVEDORING',
                'route_type' => 'DOMESTIC',
                'unit_of_measure' => 'REV_TON',
                'rate' => '95.0000',
                'tax_treatment_key' => 'VATABLE',
                'ppa_share_applicability' => 'NOT_APPLICABLE',
                'ppa_share_rate' => '0.0000',
                'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            ],
            [
                'code' => 'ARR_FOR',
                'name' => 'Arrastre - Foreign Cargo',
                'service_type' => 'ARRASTRE',
                'route_type' => 'FOREIGN',
                'unit_of_measure' => 'REV_TON',
                'rate' => '250.0000',
                'tax_treatment_key' => 'ZERO_RATED',
                'ppa_share_applicability' => 'APPLICABLE',
                'ppa_share_rate' => '0.2000',
                'fuel_surcharge_applicability' => 'APPLICABLE',
            ],
        ];

        foreach ($defaultTariffs as $tariffData) {
            $tariff = Tariff::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'tariff_code' => $tariffData['code'],
                    'service_type' => $tariffData['service_type'],
                    'route_type' => $tariffData['route_type'],
                ],
                [
                    'name' => $tariffData['name'],
                    'unit_of_measure' => $tariffData['unit_of_measure'],
                    'is_active' => true,
                ]
            );

            TariffVersion::firstOrCreate(
                ['tariff_id' => $tariff->id, 'version_number' => 1],
                [
                    'rate' => $tariffData['rate'],
                    'tax_treatment_key' => $tariffData['tax_treatment_key'],
                    'ppa_share_applicability' => $tariffData['ppa_share_applicability'],
                    'ppa_share_rate' => $tariffData['ppa_share_rate'],
                    'fuel_surcharge_applicability' => $tariffData['fuel_surcharge_applicability'],
                    'effective_from' => now()->subDay(),
                    'status' => 'effective',
                    'created_by_user_id' => $adminUser->id,
                ]
            );
        }

        $this->seedLegacyTariffs($org, $adminUser);

        // 10. Seed Fuel Surcharge Schedule & Active Observation (Decision W29 / P2-01 / P2-10)
        $fuelObservation = FuelPriceObservation::firstOrCreate(
            [
                'organization_id' => $org->id,
                'product_grade' => 'DIESEL',
                'currency' => 'PHP',
                'unit_of_measure' => 'LITER',
                'status' => 'active',
            ],
            [
                'price' => '65.0000',
                'observed_at' => now()->subDay(),
                'effective_at' => now()->subDay(),
                'entered_by_user_id' => $adminUser->id,
                'notes' => 'Baseline Gensan local port diesel price observation',
            ]
        );

        $policy = FuelSurchargePolicyVersion::firstOrCreate(
            ['organization_id' => $org->id, 'version_number' => 1],
            [
                'basis' => 'BASE_TARIFF_AMOUNT',
                'effective_from' => now()->subDay(),
                'status' => 'effective',
                'created_by_user_id' => $adminUser->id,
            ]
        );

        $bands = [
            ['min_price' => '0.0000', 'max_price' => '50.0000', 'surcharge_percent' => '0.0000', 'label' => 'Standard Rate (<= 50.00)'],
            ['min_price' => '50.0000', 'max_price' => '70.0000', 'surcharge_percent' => '0.0500', 'label' => 'Tier 1 Surcharge (50.01 - 70.00)'],
            ['min_price' => '70.0000', 'max_price' => '90.0000', 'surcharge_percent' => '0.1000', 'label' => 'Tier 2 Surcharge (70.01 - 90.00)'],
            ['min_price' => '90.0000', 'max_price' => null, 'surcharge_percent' => '0.1500', 'label' => 'Tier 3 High Surcharge (> 90.00)'],
        ];

        foreach ($bands as $bandData) {
            FuelSurchargeBand::firstOrCreate(
                [
                    'policy_version_id' => $policy->id,
                    'min_price' => $bandData['min_price'],
                    'max_price' => $bandData['max_price'],
                ],
                [
                    'surcharge_percent' => $bandData['surcharge_percent'],
                    'label' => $bandData['label'],
                ]
            );
        }

        // 11. Seed Default Document Series for Sales Invoices (Decision W07 / P2-02)
        DocumentSeries::firstOrCreate(
            ['organization_id' => $org->id, 'series_code' => 'SI-GENSAN-2026'],
            [
                'location_id' => $loc->id,
                'document_type' => 'SALES_INVOICE',
                'prefix' => 'SI-',
                'current_number' => 0,
                'start_number' => 1,
                'end_number' => null,
                'padding_length' => 10,
                'is_active' => true,
            ]
        );

        // Development seed only: a normal monthly period keeps fresh environments usable.
        // Production periods are explicit administrator-owned master data, never inferred from a legacy cutoff rule.
        AccountingPeriod::firstOrCreate(
            ['organization_id' => $org->id, 'period_code' => now('Asia/Manila')->format('Y-m')],
            ['starts_on' => now('Asia/Manila')->startOfMonth()->toDateString(), 'ends_on' => now('Asia/Manila')->endOfMonth()->toDateString(), 'status' => 'OPEN', 'notes' => 'Development seed period']
        );

        DocumentSeries::firstOrCreate(
            ['organization_id' => $org->id, 'series_code' => 'CR-GENSAN-2026'],
            [
                'location_id' => $loc->id,
                'document_type' => 'COLLECTION_RECEIPT',
                'prefix' => 'CR-',
                'current_number' => 0,
                'start_number' => 1,
                'end_number' => null,
                'padding_length' => 10,
                'is_active' => true,
            ]
        );

        DocumentSeries::firstOrCreate(
            ['organization_id' => $org->id, 'series_code' => 'ACK-GENSAN-2026'],
            [
                'location_id' => $loc->id,
                'document_type' => 'ACKNOWLEDGEMENT_RECEIPT',
                'prefix' => 'ACK-',
                'current_number' => 0,
                'start_number' => 1,
                'end_number' => null,
                'padding_length' => 10,
                'is_active' => true,
            ]
        );

        // 12. Seed Default Document Studio Template & Activation (Decision W28 / P2-03)
        $studioService = app(DocumentStudioService::class);
        $defaultLayout = $studioService->getDefaultSalesInvoiceLayout();

        $defaultTemplate = DocumentTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'SI-SERVICE-DEFAULT'],
            [
                'document_kind' => 'SERVICE',
                'name' => 'Standard Sales Invoice Layout (Letter)',
                'description' => 'BIR-compliant sales invoice template with buyer snapshot, VAT breakdown, and legal disclaimers.',
                'is_system' => true,
            ]
        );

        $defaultVersion = DocumentTemplateVersion::firstOrCreate(
            ['template_id' => $defaultTemplate->id, 'version_number' => 1],
            [
                'status' => 'PUBLISHED',
                'layout_schema_version' => '1.0.0',
                'layout_definition' => $defaultLayout,
                'validation_summary' => [
                    'structure_valid' => true,
                    'fiscal_valid' => true,
                    'missing_fields' => [],
                    'missing_elements' => [],
                    'errors' => [],
                ],
                'created_by_user_id' => $adminUser->id,
                'published_by_user_id' => $adminUser->id,
                'published_at' => now(),
            ]
        );

        DocumentTemplateActivation::firstOrCreate(
            [
                'organization_id' => $org->id,
                'document_kind' => 'SERVICE',
                'location_id' => null,
                'series_id' => null,
            ],
            [
                'template_version_id' => $defaultVersion->id,
                'effective_from' => now()->subDay(),
                'is_active' => true,
                'activated_by_user_id' => $adminUser->id,
            ]
        );

        $receiptTemplate = DocumentTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'CR-COLLECTION-DEFAULT'],
            ['document_kind' => 'COLLECTION_RECEIPT', 'name' => 'Standard Collection Receipt Layout (Letter)', 'description' => 'Collection receipt / OR layout with payer snapshot and immutable allocations.', 'is_system' => true]
        );
        $receiptVersion = DocumentTemplateVersion::firstOrCreate(
            ['template_id' => $receiptTemplate->id, 'version_number' => 1],
            ['status' => 'PUBLISHED', 'layout_schema_version' => '1.0.0', 'layout_definition' => $studioService->getDefaultCollectionReceiptLayout(), 'validation_summary' => ['structure_valid' => true, 'fiscal_valid' => true, 'missing_fields' => [], 'missing_elements' => [], 'errors' => []], 'created_by_user_id' => $adminUser->id, 'published_by_user_id' => $adminUser->id, 'published_at' => now()]
        );
        DocumentTemplateActivation::firstOrCreate(
            ['organization_id' => $org->id, 'document_kind' => 'COLLECTION_RECEIPT', 'location_id' => null, 'series_id' => null],
            ['template_version_id' => $receiptVersion->id, 'effective_from' => now()->subDay(), 'is_active' => true, 'activated_by_user_id' => $adminUser->id]
        );

        $ackTemplate = DocumentTemplate::firstOrCreate(
            ['organization_id' => $org->id, 'code' => 'ACK-RECEIPT-DEFAULT'],
            ['document_kind' => 'ACKNOWLEDGEMENT_RECEIPT', 'name' => 'Standard Acknowledgement Receipt Layout (Letter)', 'description' => 'Internal acknowledgement receipt. Not an Official Receipt; settlement is recorded without BIR OR labeling.', 'is_system' => true]
        );
        $ackVersion = DocumentTemplateVersion::firstOrCreate(
            ['template_id' => $ackTemplate->id, 'version_number' => 1],
            ['status' => 'PUBLISHED', 'layout_schema_version' => '1.0.0', 'layout_definition' => $studioService->getDefaultAcknowledgementReceiptLayout(), 'validation_summary' => ['structure_valid' => true, 'fiscal_valid' => true, 'missing_fields' => [], 'missing_elements' => [], 'errors' => []], 'created_by_user_id' => $adminUser->id, 'published_by_user_id' => $adminUser->id, 'published_at' => now()]
        );
        DocumentTemplateActivation::firstOrCreate(
            ['organization_id' => $org->id, 'document_kind' => 'ACKNOWLEDGEMENT_RECEIPT', 'location_id' => null, 'series_id' => null],
            ['template_version_id' => $ackVersion->id, 'effective_from' => now()->subDay(), 'is_active' => true, 'activated_by_user_id' => $adminUser->id]
        );

        // 13. Seed non-fiscal operational snapshot layouts (P4-02).
        $operationalTemplates = [
            ['code' => 'SOA-DEFAULT', 'kind' => 'ACCOUNT_STATEMENT', 'name' => 'Standard Account Statement Layout (Letter)', 'description' => 'Non-fiscal immutable account-statement snapshot.', 'layout' => $studioService->getDefaultAccountStatementLayout()],
            ['code' => 'YTR-DEFAULT', 'kind' => 'YELLOW_INVOICE', 'name' => 'Standard Yellow Invoice Transmittal Layout (Letter)', 'description' => 'Non-fiscal immutable yellow invoice-transmittal snapshot.', 'layout' => $studioService->getDefaultTransmittalLayout('YELLOW_INVOICE')],
            ['code' => 'WTR-DEFAULT', 'kind' => 'WHITE_RECEIPT', 'name' => 'Standard White Receipt Transmittal Layout (Letter)', 'description' => 'Non-fiscal immutable white receipt-transmittal snapshot without inferred tax classifications.', 'layout' => $studioService->getDefaultTransmittalLayout('WHITE_RECEIPT')],
        ];
        foreach ($operationalTemplates as $operationalTemplate) {
            $template = DocumentTemplate::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $operationalTemplate['code']],
                ['document_kind' => $operationalTemplate['kind'], 'name' => $operationalTemplate['name'], 'description' => $operationalTemplate['description'], 'is_system' => true]
            );
            $version = DocumentTemplateVersion::firstOrCreate(
                ['template_id' => $template->id, 'version_number' => 1],
                ['status' => 'PUBLISHED', 'layout_schema_version' => '1.0.0', 'layout_definition' => $operationalTemplate['layout'], 'validation_summary' => ['structure_valid' => true, 'fiscal_valid' => true, 'missing_fields' => [], 'missing_elements' => [], 'errors' => []], 'created_by_user_id' => $adminUser->id, 'published_by_user_id' => $adminUser->id, 'published_at' => now()]
            );
            DocumentTemplateActivation::firstOrCreate(
                ['organization_id' => $org->id, 'document_kind' => $operationalTemplate['kind'], 'location_id' => null, 'series_id' => null],
                ['template_version_id' => $version->id, 'effective_from' => now()->subDay(), 'is_active' => true, 'activated_by_user_id' => $adminUser->id]
            );
        }

        // 14. Seed Taxpayer Profile Version (Issuer Fiscal Profile - P2-06)
        TaxpayerProfileVersion::firstOrCreate(
            [
                'organization_id' => $org->id,
                'version' => 1,
            ],
            [
                'registered_name' => 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.',
                'trade_name' => 'SCIPSI PORT TERMINAL',
                'tin' => '000-123-456-000',
                'branch_code' => '00000',
                'tax_classification' => 'VAT_REGISTERED',
                'rdo_code' => '111',
                'registered_address' => [
                    'street' => 'Makar Wharf',
                    'barangay' => 'Labangal',
                    'city' => 'General Santos City',
                    'province' => 'South Cotabato',
                    'zip_code' => '9500',
                    'country' => 'Philippines',
                ],
                'line_of_business' => 'Cargo handling, stevedoring, and arrastre port services',
                'bir_permit_number' => 'BIR-CAS-2026-00129-GENSAN',
                'bir_permit_issued_at' => '2026-01-01',
                'statutory_legend' => 'VAT SALES INVOICE',
                'effective_from' => now()->subMonths(6),
                'is_active' => true,
            ]
        );

        // 14. Seed Fiscal Tax Rule Versions (P2-06)
        $taxRules = [
            [
                'tax_classification_key' => 'VATABLE',
                'vat_rate' => '0.1200',
                'buyer_tin_required' => true,
                'invoice_legend' => 'VATable Sales',
                'legal_basis' => 'NIRC Sec. 108',
            ],
            [
                'tax_classification_key' => 'ZERO_RATED',
                'vat_rate' => '0.0000',
                'buyer_tin_required' => true,
                'invoice_legend' => 'Zero-Rated Sales pursuant to NIRC Sec. 108(B)',
                'legal_basis' => 'NIRC Sec. 108(B)',
            ],
            [
                'tax_classification_key' => 'EXEMPT',
                'vat_rate' => '0.0000',
                'buyer_tin_required' => false,
                'invoice_legend' => 'VAT-Exempt Sales under NIRC Sec. 109',
                'legal_basis' => 'NIRC Sec. 109',
            ],
            [
                'tax_classification_key' => 'NON_VAT',
                'vat_rate' => '0.0000',
                'buyer_tin_required' => false,
                'invoice_legend' => 'Non-VAT Sales subject to Percentage Tax under NIRC Sec. 116',
                'legal_basis' => 'NIRC Sec. 116',
            ],
        ];

        foreach ($taxRules as $rule) {
            FiscalTaxRuleVersion::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'tax_classification_key' => $rule['tax_classification_key'],
                    'version' => 1,
                ],
                [
                    'vat_rate' => $rule['vat_rate'],
                    'buyer_tin_required' => $rule['buyer_tin_required'],
                    'invoice_legend' => $rule['invoice_legend'],
                    'legal_basis' => $rule['legal_basis'],
                    'effective_from' => now()->subMonths(6),
                    'is_active' => true,
                ]
            );
        }

        // 15. Seed a published manual payment-route policy (P3-05 / W25 pay path)
        // Gateway stays off until P3-06; customers need an effective published version
        // before selected-bill payment instructions can be issued.
        PaymentPolicyVersion::firstOrCreate(
            [
                'organization_id' => $org->id,
                'version_number' => 1,
            ],
            [
                'currency' => 'PHP',
                'gateway_enabled' => false,
                'manual_instructions' => "Deposit to SCIPSI's approved receiving bank account. Keep the bank transaction reference for proof upload and teller review.",
                'manual_deadline_hours' => 48,
                'review_target_hours' => 24,
                'clearance_target_hours' => 48,
                'correction_window_hours' => 24,
                'status' => PaymentPolicyVersion::STATUS_PUBLISHED,
                'effective_from' => now('Asia/Manila')->subDay(),
                'created_by_user_id' => $adminUser->id,
                'published_by_user_id' => $adminUser->id,
                'published_at' => now('Asia/Manila')->subDay(),
                'publication_reason' => 'Seeded default manual payment-route policy for selected-bill instructions.',
                'lock_version' => 1,
            ]
        );
    }

    private function seedLegacyTariffs(Organization $org, User $adminUser): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $path = database_path('seeders/data/legacy-tariffs.json');
        if (! is_file($path)) {
            return;
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
        $rows = json_decode($raw, true);
        if (! is_array($rows) || $rows === []) {
            return;
        }

        (new LegacyTariffCatalog)->seedInto($org, $rows, $adminUser->id);
    }

    private function seedVessels(Organization $org): void
    {
        $catalog = [
            ['name' => 'HONDURAS', 'vessel_type' => 'Non Containerized', 'typical_route' => 'DOMESTIC', 'shipping_line' => 'SJ SHIPPING LINES'],
            ['name' => 'HANEBURG', 'vessel_type' => 'Containerized', 'typical_route' => 'FOREIGN', 'shipping_line' => 'REGNANT ENTERPRISE CO. LTD'],
            ['name' => 'ALEXANDER', 'vessel_type' => 'Non Containerized', 'typical_route' => 'DOMESTIC', 'shipping_line' => 'LOADSTAR SHIPPING'],
        ];
        if (! app()->environment('testing')) {
            $path = database_path('seeders/data/vessels.json');
            if (is_file($path)) {
                $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
                $loaded = json_decode($raw, true);
                if (is_array($loaded) && $loaded !== []) {
                    $catalog = $loaded;
                }
            }
        }
        foreach ($catalog as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            Vessel::firstOrCreate(
                ['organization_id' => $org->id, 'name' => $name],
                [
                    'vessel_type' => ($row['vessel_type'] ?? '') !== '' ? $row['vessel_type'] : null,
                    'typical_route' => $row['typical_route'] ?? null,
                    'shipping_line' => ($row['shipping_line'] ?? '') !== '' ? $row['shipping_line'] : null,
                    'is_active' => true,
                ]
            );
        }
    }
}
