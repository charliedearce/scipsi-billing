<?php

namespace Tests\Feature\Tax;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerUserLink;
use App\Models\DocumentType;
use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PrivateFile;
use App\Models\Role;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Models\User;
use App\Services\Billing\FiscalInvoiceService;
use App\Services\Billing\InvoiceDraftService;
use App\Services\Billing\InvoicePostingService;
use App\Services\Billing\TaxEvidenceService;
use App\Services\Uploads\PrivateStorageService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaxEvidenceVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Location $location;

    protected User $admin;

    protected User $teller;

    protected User $customerUser1;

    protected User $customerUser2;

    protected Customer $customer1;

    protected Customer $customer2;

    protected CustomerBuyerProfile $buyerProfile1;

    protected BuyerProfileVersion $profileVersion1;

    protected CustomerBuyerProfile $buyerProfile2;

    protected BuyerProfileVersion $profileVersion2;

    protected PrivateStorageService $storageService;

    protected TaxEvidenceService $taxEvidenceService;

    protected InvoiceDraftService $draftService;

    protected FiscalInvoiceService $fiscalService;

    protected InvoicePostingService $postingService;

    protected DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local_private');

        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->location = Location::first();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->docType = DocumentType::first();

        // Make sure all default tariff and fuel versions cover any test date
        TariffVersion::query()->update([
            'effective_from' => Carbon::now()->subYears(2),
        ]);
        FuelPriceObservation::query()->update([
            'effective_at' => Carbon::now()->subYears(2),
            'observed_at' => Carbon::now()->subYears(2),
        ]);
        FuelSurchargePolicyVersion::query()->update([
            'effective_from' => Carbon::now()->subYears(2),
        ]);

        $tellerRole = Role::where('name', 'Teller')->first();
        $customerRole = Role::where('name', 'Customer')->first();

        $this->teller = User::create([
            'name' => 'Teller Reviewer',
            'email' => 'teller_tax@scipsi.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->teller->roles()->attach($tellerRole->id);
        $this->teller->locations()->attach($this->location->id, ['is_primary' => true]);

        // Customer 1
        $this->customer1 = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'CUST-TAX-001',
            'name' => 'Ocean Exporters Inc',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);
        $this->customerUser1 = User::create([
            'name' => 'Customer User 1',
            'email' => 'taxuser1@ocean.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser1->roles()->attach($customerRole->id);
        CustomerUserLink::create([
            'customer_id' => $this->customer1->id,
            'user_id' => $this->customerUser1->id,
            'authority_role' => 'BILLING_OFFICER',
            'is_active' => true,
            'linked_at' => Carbon::now(),
        ]);

        $this->buyerProfile1 = CustomerBuyerProfile::create([
            'customer_id' => $this->customer1->id,
            'current_version' => 1,
            'is_active' => true,
        ]);
        $this->profileVersion1 = BuyerProfileVersion::create([
            'buyer_profile_id' => $this->buyerProfile1->id,
            'version' => 1,
            'registered_name' => 'OCEAN EXPORTERS INC',
            'trade_name' => 'OCEAN EXPORTERS',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Pier 1, Makar Wharf',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
                'country' => 'Philippines',
            ],
            'contact_email' => 'finance@ocean.test',
            'contact_phone' => '+639171112222',
            'effective_from' => Carbon::now()->subDays(30),
            'status' => 'active',
        ]);

        // Customer 2
        $this->customer2 = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'CUST-TAX-002',
            'name' => 'South Sea Foods Corp',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);
        $this->customerUser2 = User::create([
            'name' => 'Customer User 2',
            'email' => 'taxuser2@southsea.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser2->roles()->attach($customerRole->id);
        CustomerUserLink::create([
            'customer_id' => $this->customer2->id,
            'user_id' => $this->customerUser2->id,
            'authority_role' => 'BILLING_OFFICER',
            'is_active' => true,
            'linked_at' => Carbon::now(),
        ]);

        $this->buyerProfile2 = CustomerBuyerProfile::create([
            'customer_id' => $this->customer2->id,
            'current_version' => 1,
            'is_active' => true,
        ]);
        $this->profileVersion2 = BuyerProfileVersion::create([
            'buyer_profile_id' => $this->buyerProfile2->id,
            'version' => 1,
            'registered_name' => 'SOUTH SEA FOODS CORP',
            'trade_name' => 'SOUTH SEA FOODS',
            'tin' => '444-555-666-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Tambler Fishport',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
                'country' => 'Philippines',
            ],
            'contact_email' => 'accounts@southsea.test',
            'contact_phone' => '+639174445555',
            'effective_from' => Carbon::now()->subDays(30),
            'status' => 'active',
        ]);

        $this->storageService = app(PrivateStorageService::class);
        $this->taxEvidenceService = app(TaxEvidenceService::class);
        $this->draftService = app(InvoiceDraftService::class);
        $this->fiscalService = app(FiscalInvoiceService::class);
        $this->postingService = app(InvoicePostingService::class);
    }

    private function createPrivateFileForUser(User $user, string $filename = 'evidence.pdf'): PrivateFile
    {
        $uploaded = UploadedFile::fake()->create($filename, 200, 'application/pdf');

        return $this->storageService->storeFile($uploaded, $this->docType, $user);
    }

    public function test_customer_can_submit_bir_2307_withholding_certificate(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1, '2307_form_q1.pdf');

        $response = $this->actingAs($this->customerUser1, 'sanctum')
            ->postJson('/api/v1/customer/tax-evidence/withholding', [
                'customer_id' => $this->customer1->id,
                'certificate_no' => '2307-2026-Q1-001',
                'private_file_id' => $file->id,
                'payor_tin' => '111-222-333-000',
                'payor_name' => 'OCEAN EXPORTERS INC',
                'payee_tin' => '000-123-456-000',
                'payee_name' => 'SOUTH COTABATO INTEGRATED PORT SERVICES INC',
                'period_from' => Carbon::now()->subMonths(3)->toDateString(),
                'period_to' => Carbon::now()->toDateString(),
                'atc_code' => 'WC158',
                'income_payment_base' => '100000.00',
                'withholding_rate' => '2.00',
                'certified_amount' => '2000.00',
                'customer_notes' => 'Q1 CWT submission for stevedoring services',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('certificate.status', 'PENDING_REVIEW')
            ->assertJsonPath('certificate.certificate_no', '2307-2026-Q1-001')
            ->assertJsonPath('certificate.certified_amount', '2000.00');

        $this->assertDatabaseHas('customer_withholding_certificates', [
            'certificate_no' => '2307-2026-Q1-001',
            'status' => 'PENDING_REVIEW',
            'certified_amount' => '2000.00',
            'remaining_amount' => '2000.00',
        ]);

        $this->assertDatabaseHas('customer_tax_evidence_events', [
            'evidence_type' => 'WITHHOLDING_CERTIFICATE',
            'event_type' => 'SUBMITTED',
            'from_status' => null,
            'to_status' => 'PENDING_REVIEW',
        ]);
    }

    public function test_customer_can_submit_a_2307_without_inventing_a_rate(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1, '2307_amount_only.pdf');

        $response = $this->actingAs($this->customerUser1, 'sanctum')
            ->postJson('/api/v1/customer/tax-evidence/withholding', [
                'customer_id' => $this->customer1->id,
                'certificate_no' => '2307-2026-AMOUNT-ONLY',
                'private_file_id' => $file->id,
                'payor_tin' => '111-222-333-000',
                'payor_name' => 'OCEAN EXPORTERS INC',
                'period_from' => Carbon::now()->subMonth()->toDateString(),
                'period_to' => Carbon::now()->toDateString(),
                'atc_code' => 'WC158',
                'income_payment_base' => '10000.00',
                'certified_amount' => '200.00',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('certificate.certified_amount', '200.00')
            ->assertJsonPath('certificate.withholding_rate', null);

        $this->assertDatabaseHas('customer_withholding_certificates', [
            'certificate_no' => '2307-2026-AMOUNT-ONLY',
            'certified_amount' => '200.00',
            'remaining_amount' => '200.00',
            'withholding_rate' => null,
        ]);
    }

    public function test_duplicate_withholding_certificate_number_is_rejected(): void
    {
        $file1 = $this->createPrivateFileForUser($this->customerUser1, '2307_form_1.pdf');
        $file2 = $this->createPrivateFileForUser($this->customerUser1, '2307_form_2.pdf');

        $payload = [
            'customer_id' => $this->customer1->id,
            'certificate_no' => '2307-DUPLICATE-TEST',
            'private_file_id' => $file1->id,
            'payor_tin' => '111-222-333-000',
            'payor_name' => 'OCEAN EXPORTERS INC',
            'period_from' => Carbon::now()->subMonths(3)->toDateString(),
            'period_to' => Carbon::now()->toDateString(),
            'atc_code' => 'WC158',
            'income_payment_base' => '50000.00',
            'withholding_rate' => '2.00',
            'certified_amount' => '1000.00',
        ];

        $res1 = $this->actingAs($this->customerUser1, 'sanctum')
            ->postJson('/api/v1/customer/tax-evidence/withholding', $payload);
        $res1->assertStatus(201);

        $payload['private_file_id'] = $file2->id;
        $res2 = $this->actingAs($this->customerUser1, 'sanctum')
            ->postJson('/api/v1/customer/tax-evidence/withholding', $payload);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['certificate_no']);
    }

    public function test_staff_can_review_and_approve_withholding_certificate(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $cert = $this->taxEvidenceService->submitWithholdingCertificate(
            $this->customerUser1,
            $this->customer1,
            [
                'customer_id' => $this->customer1->id,
                'certificate_no' => '2307-APPR-001',
                'private_file_id' => $file->id,
                'payor_tin' => '111-222-333-000',
                'payor_name' => 'OCEAN EXPORTERS INC',
                'period_from' => Carbon::now()->subMonths(3)->toDateString(),
                'period_to' => Carbon::now()->toDateString(),
                'atc_code' => 'WC158',
                'income_payment_base' => '50000.00',
                'withholding_rate' => '2.00',
                'certified_amount' => '1000.00',
            ]
        );

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/admin/tax-evidence/withholding/{$cert->id}/review", [
                'decision' => 'APPROVED',
                'decision_notes' => 'TIN verified against BIR Form 2307 stamp',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('certificate.status', 'APPROVED')
            ->assertJsonPath('certificate.remaining_amount', '1000.00');

        $cert->refresh();
        $this->assertEquals('APPROVED', $cert->status);
        $this->assertEquals('1000.00', $cert->remaining_amount);
        $this->assertEquals($this->teller->id, $cert->reviewed_by_user_id);
        $this->assertNotNull($cert->reviewed_at);

        $this->assertDatabaseHas('customer_tax_evidence_events', [
            'evidence_id' => $cert->id,
            'evidence_type' => 'WITHHOLDING_CERTIFICATE',
            'event_type' => 'APPROVED',
            'to_status' => 'APPROVED',
        ]);

        $today = Carbon::now()->toDateString();
        $this->actingAs($this->teller, 'sanctum')
            ->getJson("/api/v1/admin/tax-evidence/withholding?customer_id={$this->customer1->id}&status=APPROVED&business_date={$today}")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $cert->id);

        $tomorrow = Carbon::now()->addDay()->toDateString();
        $this->actingAs($this->teller, 'sanctum')
            ->getJson("/api/v1/admin/tax-evidence/withholding?customer_id={$this->customer1->id}&status=APPROVED&business_date={$tomorrow}")
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_staff_can_request_correction_and_reject_withholding(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $cert = $this->taxEvidenceService->submitWithholdingCertificate(
            $this->customerUser1,
            $this->customer1,
            [
                'customer_id' => $this->customer1->id,
                'certificate_no' => '2307-REJ-001',
                'private_file_id' => $file->id,
                'payor_tin' => '111-222-333-000',
                'payor_name' => 'OCEAN EXPORTERS INC',
                'period_from' => Carbon::now()->subMonths(3)->toDateString(),
                'period_to' => Carbon::now()->toDateString(),
                'atc_code' => 'WC158',
                'income_payment_base' => '50000.00',
                'withholding_rate' => '2.00',
                'certified_amount' => '1000.00',
            ]
        );

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/admin/tax-evidence/withholding/{$cert->id}/review", [
                'decision' => 'NEEDS_CORRECTION',
                'decision_notes' => 'Missing signature of authorized signatory in Part IV',
                'rejection_reason' => 'Signatory signature missing',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('certificate.status', 'NEEDS_CORRECTION');

        $this->assertEquals('NEEDS_CORRECTION', $cert->fresh()->status);

        // Reject
        $resReject = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/admin/tax-evidence/withholding/{$cert->id}/review", [
                'decision' => 'REJECTED',
                'rejection_reason' => 'Invalid taxable year',
            ]);

        $resReject->assertStatus(200)
            ->assertJsonPath('certificate.status', 'REJECTED');
        $this->assertEquals('REJECTED', $cert->fresh()->status);
    }

    public function test_withholding_certificate_does_not_modify_invoice_vat_or_gross_amount(): void
    {
        // Under Decision W20, BIR Form 2307 is a payment tender (Phase 3), not a billing line item deduction.
        // Even if a customer has an active Form 2307, the invoice draft calculation must remain standard 12% VAT.
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $cert = $this->taxEvidenceService->submitWithholdingCertificate(
            $this->customerUser1,
            $this->customer1,
            [
                'customer_id' => $this->customer1->id,
                'certificate_no' => '2307-INVOICE-INVARIANT',
                'private_file_id' => $file->id,
                'payor_tin' => '111-222-333-000',
                'payor_name' => 'OCEAN EXPORTERS INC',
                'period_from' => Carbon::now()->subMonths(3)->toDateString(),
                'period_to' => Carbon::now()->toDateString(),
                'atc_code' => 'WC158',
                'income_payment_base' => '100000.00',
                'withholding_rate' => '2.00',
                'certified_amount' => '2000.00',
            ]
        );
        $this->taxEvidenceService->reviewWithholdingCertificate($cert, $this->teller, 'APPROVED');

        // Create standard draft invoice for customer 1
        $calcRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer1->id,
                'business_date' => Carbon::now()->toDateString(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $calcRes->assertStatus(200);
        $tax = $calcRes->json('data.totals.tax_amount');
        $gross = $calcRes->json('data.totals.gross_amount');
        $taxTreatment = $calcRes->json('data.items.0.snapshot.tax_treatment_key');

        // Standard tariff rate STEV_DOM has 12% VAT
        $this->assertEquals('VATABLE', $taxTreatment);
        $this->assertGreaterThan(0, (float) $tax);
        $this->assertGreaterThan((float) $tax, (float) $gross);
    }

    public function test_customer_can_submit_tax_exemption_evidence(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1, 'peza_cert_2026.pdf');

        $response = $this->actingAs($this->customerUser1, 'sanctum')
            ->postJson('/api/v1/customer/tax-evidence/exemptions', [
                'customer_id' => $this->customer1->id,
                'exemption_type' => 'ZERO_RATED',
                'legal_basis' => 'PEZA Law - RA 7916 / Ecozone Developer-Operator',
                'ruling_or_cert_no' => 'PEZA-COR-2026-0099',
                'private_file_id' => $file->id,
                'valid_from' => Carbon::now()->subMonths(6)->toDateString(),
                'valid_to' => Carbon::now()->addMonths(6)->toDateString(),
                'covered_services' => ['STEV_DOM', 'PORT_STORAGE', 'MOORING'],
                'customer_notes' => 'Registered PEZA ecozone enterprise operating under zero-rating regime',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('tax_exemption.status', 'PENDING_REVIEW')
            ->assertJsonPath('tax_exemption.ruling_or_cert_no', 'PEZA-COR-2026-0099')
            ->assertJsonPath('tax_exemption.exemption_type', 'ZERO_RATED');

        $this->assertDatabaseHas('customer_tax_exemptions', [
            'customer_id' => $this->customer1->id,
            'ruling_or_cert_no' => 'PEZA-COR-2026-0099',
            'status' => 'PENDING_REVIEW',
            'exemption_type' => 'ZERO_RATED',
        ]);
    }

    public function test_staff_can_approve_tax_exemption(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $exemption = $this->taxEvidenceService->submitTaxExemption(
            $this->customerUser1,
            $this->customer1,
            [
                'customer_id' => $this->customer1->id,
                'exemption_type' => 'ZERO_RATED',
                'legal_basis' => 'BIR Ruling DA-123-2026 Zero-Rated Export Service',
                'ruling_or_cert_no' => 'DA-123-2026',
                'private_file_id' => $file->id,
                'valid_from' => Carbon::now()->subMonths(6)->toDateString(),
                'valid_to' => Carbon::now()->addMonths(6)->toDateString(),
                'covered_services' => ['STEV_DOM'],
            ]
        );

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/admin/tax-evidence/exemptions/{$exemption->id}/review", [
                'decision' => 'APPROVED',
                'decision_notes' => 'Validated with BIR ruling registry and active PEZA ecozone permit.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('tax_exemption.status', 'APPROVED');

        $exemption->refresh();
        $this->assertEquals('APPROVED', $exemption->status);
        $this->assertEquals($this->teller->id, $exemption->reviewed_by_user_id);
        $this->assertNotNull($exemption->reviewed_at);

        $this->assertDatabaseHas('customer_tax_evidence_events', [
            'evidence_id' => $exemption->id,
            'evidence_type' => 'TAX_EXEMPTION',
            'event_type' => 'APPROVED',
            'to_status' => 'APPROVED',
        ]);

        $today = Carbon::now()->toDateString();
        $baseUrl = "/api/v1/admin/tax-evidence/exemptions?customer_id={$this->customer1->id}&status=APPROVED&business_date={$today}";
        $this->actingAs($this->teller, 'sanctum')
            ->getJson("{$baseUrl}&exemption_type=ZERO_RATED")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $exemption->id);
        $this->actingAs($this->teller, 'sanctum')
            ->getJson("{$baseUrl}&exemption_type=VAT_EXEMPT")
            ->assertOk()
            ->assertJsonPath('total', 0);

        $afterValidity = Carbon::now()->addMonths(7)->toDateString();
        $this->actingAs($this->teller, 'sanctum')
            ->getJson("/api/v1/admin/tax-evidence/exemptions?customer_id={$this->customer1->id}&status=APPROVED&business_date={$afterValidity}&exemption_type=ZERO_RATED")
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_approved_tax_exemption_automatically_applies_zero_rated_vat_in_draft(): void
    {
        // 1. Create and approve exemption covering STEV_DOM
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $exemption = $this->taxEvidenceService->submitTaxExemption(
            $this->customerUser1,
            $this->customer1,
            [
                'customer_id' => $this->customer1->id,
                'exemption_type' => 'ZERO_RATED',
                'legal_basis' => 'PEZA RA 7916 Zero Rating Certificate',
                'ruling_or_cert_no' => 'PEZA-VAT-2026-001',
                'private_file_id' => $file->id,
                'valid_from' => Carbon::now()->subMonths(6)->toDateString(),
                'valid_to' => Carbon::now()->addMonths(6)->toDateString(),
                'covered_services' => ['STEV_DOM'],
            ]
        );
        $this->taxEvidenceService->reviewTaxExemption($exemption, $this->teller, 'APPROVED');

        // 2. Draft invoice calculation on today
        $calcRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer1->id,
                'business_date' => Carbon::now()->toDateString(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $calcRes->assertStatus(200);
        $this->assertEquals('ZERO_RATED', $calcRes->json('data.items.0.snapshot.tax_treatment_key'));
        $this->assertEquals('0.00', $calcRes->json('data.items.0.tax_amount'));
        $this->assertEquals('0.00', $calcRes->json('data.totals.tax_amount'));

        // 3. Create Draft Invoice
        $draftRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer1->id,
                ...$this->invoiceShipmentPayload(),
                'business_date' => Carbon::now()->toDateString(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        $draftRes->assertStatus(201);
        $this->assertEquals('0.00', $draftRes->json('data.tax_amount'));
        $this->assertEquals('0.00', $draftRes->json('data.items.0.tax_amount'));
        $this->assertEquals('ZERO_RATED', $draftRes->json('data.items.0.pricing_snapshot.tax_treatment_key'));
    }

    public function test_approved_non_vat_ruling_is_reflected_in_bill_and_unrelated_ruling_cannot_authorize_posting(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $ruling = $this->taxEvidenceService->submitTaxExemption($this->customerUser1, $this->customer1, [
            'customer_id' => $this->customer1->id,
            'exemption_type' => 'VAT_EXEMPT',
            'legal_basis' => 'Reviewed Non-VAT service ruling',
            'ruling_or_cert_no' => 'NONVAT-BILL-2026',
            'private_file_id' => $file->id,
            'valid_from' => Carbon::now()->subMonth()->toDateString(),
            'valid_to' => Carbon::now()->addMonth()->toDateString(),
            'covered_services' => ['STEV_DOM'],
        ]);
        $this->taxEvidenceService->reviewTaxExemption($ruling, $this->teller, 'APPROVED');

        $preview = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/calculate', [
            'customer_id' => $this->customer1->id,
            'business_date' => Carbon::today('Asia/Manila')->toDateString(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ]);
        $preview->assertOk()
            ->assertJsonPath('data.items.0.snapshot.tax_treatment_key', 'EXEMPT')
            ->assertJsonPath('data.items.0.tax_amount', '0.00');

        $draft = $this->draftService->createDraft($this->org->id, $this->location->id, $this->admin, [
            'customer_id' => $this->customer1->id,
            'business_date' => Carbon::today('Asia/Manila')->toDateString(),
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ]);
        $this->assertSame('EXEMPT', $draft->items->first()->pricingSnapshot->tax_treatment_key);
        $this->assertSame('0.00', $draft->tax_amount);

        $this->taxEvidenceService->revokeTaxExemption($ruling, $this->admin, 'Coverage withdrawn');
        $otherFile = $this->createPrivateFileForUser($this->customerUser1);
        $unrelated = $this->taxEvidenceService->submitTaxExemption($this->customerUser1, $this->customer1, [
            'customer_id' => $this->customer1->id,
            'exemption_type' => 'VAT_EXEMPT',
            'legal_basis' => 'Reviewed ruling for a different service',
            'ruling_or_cert_no' => 'OTHER-BILL-2026',
            'private_file_id' => $otherFile->id,
            'valid_from' => Carbon::now()->subMonth()->toDateString(),
            'valid_to' => Carbon::now()->addMonth()->toDateString(),
            'covered_services' => ['ARR_FOR'],
        ]);
        $this->taxEvidenceService->reviewTaxExemption($unrelated, $this->teller, 'APPROVED');

        $readiness = $this->fiscalService->validateFiscalReadiness($draft->fresh(['items.pricingSnapshot']));
        $this->assertFalse($readiness['is_fiscal_ready']);
        $this->assertTrue(collect($readiness['errors'])->contains(fn ($error) => str_contains($error, 'no approved tax exemption')));

        $zeroRatedFile = $this->createPrivateFileForUser($this->customerUser1);
        $wrongType = $this->taxEvidenceService->submitTaxExemption($this->customerUser1, $this->customer1, [
            'customer_id' => $this->customer1->id,
            'exemption_type' => 'ZERO_RATED',
            'legal_basis' => 'Reviewed zero-rated ruling for this service',
            'ruling_or_cert_no' => 'ZERORATE-BILL-2026',
            'private_file_id' => $zeroRatedFile->id,
            'valid_from' => Carbon::now()->subMonth()->toDateString(),
            'valid_to' => Carbon::now()->addMonth()->toDateString(),
            'covered_services' => ['STEV_DOM'],
        ]);
        $this->taxEvidenceService->reviewTaxExemption($wrongType, $this->teller, 'APPROVED');
        $wrongTypeReadiness = $this->fiscalService->validateFiscalReadiness($draft->fresh(['items.pricingSnapshot']));
        $this->assertFalse($wrongTypeReadiness['is_fiscal_ready']);
        $this->assertTrue(collect($wrongTypeReadiness['errors'])->contains(fn ($error) => str_contains($error, 'no approved tax exemption')));
    }

    public function test_fiscal_readiness_validates_exemption_evidence_for_zero_rated_and_exempt_invoices(): void
    {
        // Match AccountingPeriodService: business dates are evaluated in Asia/Manila.
        $today = Carbon::today('Asia/Manila')->toDateString();

        // Tariff-native ZERO_RATED (foreign ARR_FOR) does not require a customer PEZA/BOI exemption.
        $nativeZeroDraft = $this->draftService->createDraft($this->org->id, $this->location->id, $this->admin, [
            'customer_id' => $this->customer2->id,
            'business_date' => $today,
            ...$this->invoiceShipmentPayload($this->org->id, ['route_type' => 'FOREIGN']),
            'items' => [
                [
                    'tariff_code' => 'ARR_FOR',
                    'quantity' => 5,
                ],
            ],
        ]);
        $nativeReady = $this->fiscalService->validateFiscalReadiness($nativeZeroDraft);
        $this->assertTrue(
            collect($nativeReady['errors'])->every(fn ($v) => ! str_contains($v, 'approved tax exemption')),
            'Tariff-native zero-rated lines must not demand a customer tax exemption.'
        );

        // Exemption-driven ZERO_RATED on an otherwise VATABLE tariff requires approved evidence.
        $file = $this->createPrivateFileForUser($this->customerUser2);
        $exemption = $this->taxEvidenceService->submitTaxExemption(
            $this->customerUser2,
            $this->customer2,
            [
                'customer_id' => $this->customer2->id,
                'exemption_type' => 'ZERO_RATED',
                'legal_basis' => 'NIRC Section 109 Exempt Agricultural Sea Cargo',
                'ruling_or_cert_no' => 'NIRC-109-2026',
                'private_file_id' => $file->id,
                'valid_from' => Carbon::now()->subMonths(6)->toDateString(),
                'valid_to' => Carbon::now()->addMonths(6)->toDateString(),
                'covered_services' => ['STEV_DOM', 'STEVEDORING'],
            ]
        );
        $this->taxEvidenceService->reviewTaxExemption($exemption, $this->teller, 'APPROVED');

        $exemptDraft = $this->draftService->createDraft($this->org->id, $this->location->id, $this->admin, [
            'customer_id' => $this->customer2->id,
            'business_date' => $today,
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [
                [
                    'tariff_code' => 'STEV_DOM',
                    'quantity' => 5,
                ],
            ],
        ]);
        $this->assertEquals('ZERO_RATED', $exemptDraft->items->first()->pricingSnapshot->tax_treatment_key);
        $withExemption = $this->fiscalService->validateFiscalReadiness($exemptDraft);
        $this->assertTrue($withExemption['is_fiscal_ready']);
        $this->assertEmpty($withExemption['errors']);

        // Simulate a VATABLE tariff line marked ZERO_RATED without an effective exemption.
        $this->taxEvidenceService->revokeTaxExemption(
            $exemption,
            $this->admin,
            'Suspended for fiscal readiness regression'
        );
        $orphanDraft = $this->draftService->createDraft($this->org->id, $this->location->id, $this->admin, [
            'customer_id' => $this->customer2->id,
            'business_date' => $today,
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [
                [
                    'tariff_code' => 'STEV_DOM',
                    'quantity' => 5,
                ],
            ],
        ]);
        $snap = $orphanDraft->items->first()->pricingSnapshot;
        $this->assertEquals('VATABLE', $snap->tax_treatment_key);
        $snap->update(['tax_treatment_key' => 'ZERO_RATED']);
        $orphanDraft->unsetRelation('items');

        $violations = $this->fiscalService->validateFiscalReadiness($orphanDraft->fresh(['items.pricingSnapshot', 'items.tariffVersion']));
        $this->assertFalse($violations['is_fiscal_ready']);
        $this->assertTrue(collect($violations['errors'])->contains(function ($v) {
            return str_contains($v, 'no approved tax exemption');
        }));

        // Restore exemption and confirm posting of exemption-driven zero-rated STEV_DOM.
        $file2 = $this->createPrivateFileForUser($this->customerUser2);
        $exemption2 = $this->taxEvidenceService->submitTaxExemption(
            $this->customerUser2,
            $this->customer2,
            [
                'customer_id' => $this->customer2->id,
                'exemption_type' => 'ZERO_RATED',
                'legal_basis' => 'NIRC Section 109 Exempt Agricultural Sea Cargo',
                'ruling_or_cert_no' => 'NIRC-109-2026-B',
                'private_file_id' => $file2->id,
                'valid_from' => Carbon::now()->subMonths(6)->toDateString(),
                'valid_to' => Carbon::now()->addMonths(6)->toDateString(),
                'covered_services' => ['STEV_DOM', 'STEVEDORING'],
            ]
        );
        $this->taxEvidenceService->reviewTaxExemption($exemption2, $this->teller, 'APPROVED');

        $postable = $this->draftService->createDraft($this->org->id, $this->location->id, $this->admin, [
            'customer_id' => $this->customer2->id,
            'business_date' => $today,
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [
                [
                    'tariff_code' => 'STEV_DOM',
                    'quantity' => 5,
                ],
            ],
        ]);
        $invoice = $this->postingService->postInvoice($postable, $this->admin, 1);
        $this->assertEquals('POSTED', $invoice->status);
        $this->assertEquals('0.00', $invoice->tax_amount);
    }

    public function test_expired_or_revoked_exemption_blocks_zero_rated_invoicing(): void
    {
        // 1. Exemption valid only until yesterday
        $yesterday = Carbon::now()->subDay()->toDateString();
        $file = $this->createPrivateFileForUser($this->customerUser1);
        $exemption = $this->taxEvidenceService->submitTaxExemption(
            $this->customerUser1,
            $this->customer1,
            [
                'customer_id' => $this->customer1->id,
                'exemption_type' => 'ZERO_RATED',
                'legal_basis' => 'Short-Term PEZA Certificate',
                'ruling_or_cert_no' => 'PEZA-SHORT-2026',
                'private_file_id' => $file->id,
                'valid_from' => Carbon::now()->subMonths(3)->toDateString(),
                'valid_to' => $yesterday,
                'covered_services' => ['STEV_DOM'],
            ]
        );
        $this->taxEvidenceService->reviewTaxExemption($exemption, $this->teller, 'APPROVED');

        // 2. Draft for today (Day after expiration)
        $calcRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer1->id,
                'business_date' => Carbon::now()->toDateString(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);

        // Should fall back to VATABLE 12% because exemption is expired
        $calcRes->assertStatus(200);
        $this->assertEquals('VATABLE', $calcRes->json('data.items.0.snapshot.tax_treatment_key'));
        $this->assertGreaterThan(0, (float) $calcRes->json('data.totals.tax_amount'));

        // 3. Make active and then revoke
        $exemption->update([
            'valid_to' => Carbon::now()->addMonths(6)->toDateString(),
        ]);
        $revokeRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tax-evidence/exemptions/{$exemption->id}/revoke", [
                'reason' => 'Ecozone registration suspended by PEZA board',
            ]);
        $revokeRes->assertStatus(200);

        $this->assertEquals('REVOKED', $exemption->fresh()->status);

        // Draft for today (when it was revoked)
        $calcRevoked = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer1->id,
                'business_date' => Carbon::now()->toDateString(),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 10],
                ],
            ]);
        $this->assertEquals('VATABLE', $calcRevoked->json('data.items.0.snapshot.tax_treatment_key'));
    }

    public function test_customer_authorization_isolation(): void
    {
        $file = $this->createPrivateFileForUser($this->customerUser1);

        // CustomerUser2 cannot submit tax evidence on behalf of Customer1
        $response = $this->actingAs($this->customerUser2, 'sanctum')
            ->postJson('/api/v1/customer/tax-evidence/withholding', [
                'customer_id' => $this->customer1->id,
                'certificate_no' => '2307-HACK-001',
                'private_file_id' => $file->id,
                'payor_tin' => '111-222-333-000',
                'payor_name' => 'OCEAN EXPORTERS INC',
                'period_from' => Carbon::now()->subMonths(3)->toDateString(),
                'period_to' => Carbon::now()->toDateString(),
                'atc_code' => 'WC158',
                'income_payment_base' => '10000.00',
                'withholding_rate' => '2.00',
                'certified_amount' => '200.00',
            ]);

        $response->assertStatus(403);

        // CustomerUser2 cannot review or revoke
        $resReview = $this->actingAs($this->customerUser2, 'sanctum')
            ->postJson('/api/v1/admin/tax-evidence/withholding/1/review', [
                'decision' => 'APPROVED',
            ]);
        $resReview->assertStatus(403);
    }
}
