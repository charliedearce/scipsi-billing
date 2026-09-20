<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRequest;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerUserLink;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\NotificationPreferenceVersion;
use App\Models\Organization;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Role;
use App\Models\TariffVersion;
use App\Models\User;
use App\Services\Billing\BillClaimService;
use App\Services\Billing\BillingRequestQueueService;
use App\Services\Billing\InvoiceIssuanceArtifactService;
use App\Services\Billing\TaxEvidenceService;
use App\Services\Billing\WalkInBillingService;
use App\Services\Sms\Gateways\FakeSmsGateway;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2TransactionalSmsTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Location $location;

    protected User $customerUser;

    protected Customer $customer;

    protected User $teller;

    protected User $admin;

    protected SmsDeliveryOrchestrator $orchestrator;

    protected FakeSmsGateway $fakeGateway;

    protected DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('local_private');

        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->location = Location::first();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();

        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Teller',
            'email' => 'teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639170000002',
        ]);
        $this->teller->roles()->attach($tellerRole);
        $this->teller->locations()->attach($this->location->id, ['is_primary' => true]);

        $customerRole = Role::where('name', 'Customer')->first();

        // Setup Customer User
        $this->customerUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Maria Clara Santos',
            'email' => 'maria.santos@test.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639171234567',
        ]);
        $this->customerUser->roles()->attach($customerRole);

        // Setup Customer Account & Link
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'CUST-SMS-0001',
            'name' => 'Santos Cargo Logistics Inc.',
            'customer_type' => 'CORPORATE',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'MANAGING_OFFICER',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        // Verified Mobile Contact Point
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639171234567',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);

        $this->docType = DocumentType::where('organization_id', $this->org->id)
            ->where('code', 'BILL_OF_LADING')
            ->firstOrFail();

        $this->fakeGateway = app(FakeSmsGateway::class);
        $this->fakeGateway->reset();
        $this->orchestrator = app(SmsDeliveryOrchestrator::class);
    }

    protected function createCleanPrivateFile(User $uploader): PrivateFile
    {
        $file = PrivateFile::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'document_type_id' => $this->docType->id,
            'purpose' => 'BILLING_SUPPORT',
            'uploaded_by' => $uploader->id,
            'current_version' => 1,
            'status' => 'ACTIVE',
        ]);

        PrivateFileVersion::create([
            'private_file_id' => $file->id,
            'version_number' => 1,
            'disk' => 'local_private',
            'file_path' => "uploads/bol_{$file->id}.pdf",
            'original_name' => 'bol_document.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => hash('sha256', 'dummy-clean-content'),
            'scan_status' => 'CLEAN',
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        return $file;
    }

    public function test_billing_request_queued_emits_sms_intent(): void
    {
        $queueService = app(BillingRequestQueueService::class);

        $request = $queueService->createDraft(
            $this->customerUser,
            $this->customer,
            $this->location->id,
            'GENERAL',
            'Test submission'
        );

        $cleanFile = $this->createCleanPrivateFile($this->customerUser);
        $queueService->attachDocument($request, $this->customerUser, $this->docType->id, $cleanFile->id);

        $submitted = $queueService->submitRequest($request, $this->customerUser);

        $this->assertEquals(BillingRequest::STATUS_QUEUED, $submitted->status);
        // Verify NotificationEvent created
        $event = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'BILLING_REQUEST_QUEUED')
            ->where('event_source_id', $submitted->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($this->customerUser->id, $event->user_id);
        $this->assertEquals($submitted->transaction_no, $event->payload_snapshot['reference_no']);

        // Verify NotificationDelivery queued
        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertStringContainsString($submitted->transaction_no, $delivery->rendered_body);

        // Verify Gateway send
        $dispatched = $this->orchestrator->dispatchDelivery($delivery);
        $this->assertEquals('provider_pending', $dispatched->status);
        $this->assertCount(1, $this->fakeGateway->getSentMessages());
    }

    public function test_billing_request_correction_required_emits_sms_intent(): void
    {
        $queueService = app(BillingRequestQueueService::class);

        $request = $queueService->createDraft(
            $this->customerUser,
            $this->customer,
            $this->location->id,
            'GENERAL'
        );
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);
        $queueService->attachDocument($request, $this->customerUser, $this->docType->id, $cleanFile->id);
        $queueService->submitRequest($request, $this->customerUser);

        // Teller claims and requests correction
        $claimed = $queueService->claimNextEligible($this->teller, $this->org->id, $this->location->id);
        $this->assertNotNull($claimed);

        $correctionNotes = 'Please provide a clearer copy with official stamp visible.';
        $fileRemarks = [
            [
                'document_type_id' => $this->docType->id,
                'rejection_reason' => 'Blurry stamp',
            ],
        ];

        $corrected = $queueService->requestCorrection($claimed, $this->teller, $correctionNotes, $fileRemarks);
        $this->assertEquals(BillingRequest::STATUS_NEEDS_CORRECTION, $corrected->status);

        // Verify NotificationEvent created
        $event = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'BILLING_REQUEST_CORRECTION_REQUIRED')
            ->where('event_source_id', $corrected->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($this->customerUser->id, $event->user_id);
        $this->assertEquals($corrected->transaction_no, $event->payload_snapshot['reference_no']);

        // Verify Delivery queued and rendered safely
        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertStringContainsString($corrected->transaction_no, $delivery->rendered_body);
        $this->assertStringNotContainsString('http://', $delivery->rendered_body);
        $this->assertStringNotContainsString('https://', $delivery->rendered_body);
    }

    public function test_invoice_artifact_ready_emits_sms_intent_after_canonical_render(): void
    {
        $artifactService = app(InvoiceIssuanceArtifactService::class);

        $buyerProfile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->org->id,
            'lock_version' => 1,
        ]);

        $profileVersion = $buyerProfile->versions()->create([
            'version' => 1,
            'registered_name' => 'Santos Cargo Logistics Inc.',
            'trade_name' => 'Santos Cargo',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'VATABLE',
            'billing_address' => ['street' => 'Pier 1 Makar', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'maria.santos@test.com',
            'contact_phone' => '+639171234567',
            'effective_from' => now()->subDay(),
            'status' => 'active',
            'reviewed_by_user_id' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        $series = DocumentSeries::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'series_code' => 'SI-SMS-2026',
            'document_type' => 'SALES_INVOICE',
            'prefix' => 'SI-',
            'padding' => 6,
            'current_number' => 1,
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'buyer_profile_version_id' => $profileVersion->id,
            'series_id' => $series->id,
            'invoice_number' => 'SI-000001',
            'status' => 'POSTED',
            'business_date' => Carbon::now()->toDateString(),
            'posted_at' => Carbon::now(),
            'currency' => 'PHP',
            'base_gross_amount' => '1000.00',
            'fuel_surcharge_amount' => '0.00',
            'gross_amount' => '1000.00',
            'ppa_amount' => '0.00',
            'discount_amount' => '0.00',
            'net_amount' => '1000.00',
            'tax_amount' => '120.00',
            'total_charge_amount' => '1120.00',
            'buyer_snapshot_name' => 'Santos Cargo Logistics Inc.',
            'buyer_snapshot_tin' => '123-456-789-000',
            'buyer_snapshot_branch_code' => '00000',
            'buyer_snapshot_tax_classification' => 'VATABLE',
            'buyer_snapshot_address' => ['street' => 'Pier 1 Makar', 'city' => 'General Santos City'],
            'created_by_user_id' => $this->customerUser->id,
            'lock_version' => 1,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'tariff_version_id' => TariffVersion::whereHas('tariff', function ($query) {
                $query->where('organization_id', $this->org->id);
            })->where('status', 'effective')->firstOrFail()->id,
            'description' => 'Cargo Handling',
            'quantity' => '1.0000',
            'unit_rate' => '1000.0000',
            'base_gross_amount' => '1000.00',
            'fuel_surcharge_amount' => '0.00',
            'gross_amount' => '1000.00',
            'ppa_amount' => '0.00',
            'discount_amount' => '0.00',
            'net_amount' => '1000.00',
            'tax_amount' => '120.00',
            'total_charge_amount' => '1120.00',
            'tax_treatment_key' => 'VATABLE',
        ]);

        $artifact = $artifactService->generateIssuanceArtifact($invoice, $this->admin);

        $this->assertEquals('RENDERED', $artifact->status);

        // Verify INVOICE_ARTIFACT_READY notification event created
        $event = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'INVOICE_ARTIFACT_READY')
            ->where('event_source_id', $invoice->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($this->customerUser->id, $event->user_id);
        $this->assertEquals('SI-000001', $event->payload_snapshot['reference_no']);

        // Verify delivery
        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertStringContainsString('SI-000001', $delivery->rendered_body);

        // Verify deduplication: second call doesn't duplicate
        $artifactService->generateIssuanceArtifact($invoice, $this->admin);
        $this->assertEquals(1, NotificationEvent::where('event_key', 'INVOICE_ARTIFACT_READY')->where('event_source_id', $invoice->id)->count());
    }

    public function test_withholding_certificate_approved_and_rejected_emit_sms_intents(): void
    {
        $taxService = app(TaxEvidenceService::class);
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);

        // Submit Withholding Cert
        $cert = $taxService->submitWithholdingCertificate(
            $this->customerUser,
            $this->customer,
            [
                'certificate_no' => '2307-2026-0001',
                'private_file_id' => $cleanFile->id,
                'payor_tin' => '123-456-789-000',
                'payor_name' => 'Santos Cargo Logistics Inc.',
                'period_from' => '2026-01-01',
                'period_to' => '2026-03-31',
                'atc_code' => 'WC100',
                'income_payment_base' => '100000.00',
                'withholding_rate' => '0.0200',
                'certified_amount' => '2000.00',
            ]
        );

        // Approve Withholding Cert
        $approved = $taxService->reviewWithholdingCertificate(
            $cert,
            $this->admin,
            CustomerWithholdingCertificate::STATUS_APPROVED,
            'Verified against BIR Form 2307 stamp'
        );

        $this->assertEquals(CustomerWithholdingCertificate::STATUS_APPROVED, $approved->status);

        $event = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'TAX_EVIDENCE_APPROVED')
            ->where('event_source_id', $cert->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($this->customerUser->id, $event->user_id);
        $this->assertEquals('approved', $event->payload_snapshot['action_label']);

        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertStringContainsString('approved', $delivery->rendered_body);

        // Strict Privacy Invariant: NO tax amounts, TINs, or cert numbers in rendered SMS
        $this->assertStringNotContainsString('2307-2026-0001', $delivery->rendered_body);
        $this->assertStringNotContainsString('2000.00', $delivery->rendered_body);
        $this->assertStringNotContainsString('123-456-789-000', $delivery->rendered_body);
        $this->assertStringNotContainsString('WC100', $delivery->rendered_body);

        // Test Rejection on a second cert
        $cleanFile2 = $this->createCleanPrivateFile($this->customerUser);
        $cert2 = $taxService->submitWithholdingCertificate(
            $this->customerUser,
            $this->customer,
            [
                'certificate_no' => '2307-2026-0002',
                'private_file_id' => $cleanFile2->id,
                'payor_tin' => '123-456-789-000',
                'payor_name' => 'Santos Cargo Logistics Inc.',
                'period_from' => '2026-01-01',
                'period_to' => '2026-03-31',
                'atc_code' => 'WC100',
                'income_payment_base' => '50000.00',
                'withholding_rate' => '0.0200',
                'certified_amount' => '1000.00',
            ]
        );

        $rejected = $taxService->reviewWithholdingCertificate(
            $cert2,
            $this->admin,
            CustomerWithholdingCertificate::STATUS_REJECTED,
            'Illegible stamp',
            'Signature missing'
        );

        $this->assertEquals(CustomerWithholdingCertificate::STATUS_REJECTED, $rejected->status);

        $rejectEvent = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'TAX_EVIDENCE_REJECTED')
            ->where('event_source_id', $cert2->id)
            ->first();

        $this->assertNotNull($rejectEvent);
        $rejectDelivery = NotificationDelivery::where('event_id', $rejectEvent->id)->first();
        $this->assertNotNull($rejectDelivery);
        $this->assertEquals('queued_local', $rejectDelivery->status);
        $this->assertStringContainsString('rejected', $rejectDelivery->rendered_body);
    }

    public function test_tax_evidence_revocation_emits_sms_intent(): void
    {
        $taxService = app(TaxEvidenceService::class);
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);

        $cert = $taxService->submitWithholdingCertificate(
            $this->customerUser,
            $this->customer,
            [
                'certificate_no' => '2307-2026-REVOKE',
                'private_file_id' => $cleanFile->id,
                'payor_tin' => '123-456-789-000',
                'payor_name' => 'Santos Cargo Logistics Inc.',
                'period_from' => '2026-01-01',
                'period_to' => '2026-03-31',
                'atc_code' => 'WC100',
                'income_payment_base' => '10000.00',
                'withholding_rate' => '0.0200',
                'certified_amount' => '200.00',
            ]
        );

        $taxService->reviewWithholdingCertificate($cert, $this->admin, CustomerWithholdingCertificate::STATUS_APPROVED);

        // Revoke
        $taxService->revokeWithholdingCertificate($cert, $this->admin, 'Audit discovered duplicate submission');

        $revokeEvent = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'TAX_EVIDENCE_REVOKED')
            ->where('event_source_id', $cert->id)
            ->first();

        $this->assertNotNull($revokeEvent);
        $revokeDelivery = NotificationDelivery::where('event_id', $revokeEvent->id)->first();
        $this->assertNotNull($revokeDelivery);
        $this->assertEquals('queued_local', $revokeDelivery->status);
        $this->assertStringContainsString('revoked', $revokeDelivery->rendered_body);
    }

    public function test_tax_exemption_review_decisions_emit_sms_intents(): void
    {
        $taxService = app(TaxEvidenceService::class);
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);

        $exemption = $taxService->submitTaxExemption(
            $this->customerUser,
            $this->customer,
            [
                'exemption_type' => CustomerTaxExemption::TYPE_VAT_EXEMPT,
                'legal_basis' => 'PEZA Law Republic Act 7916',
                'ruling_or_cert_no' => 'PEZA-2026-089',
                'covered_services' => ['ALL'],
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
                'private_file_id' => $cleanFile->id,
            ]
        );

        // Review needing correction
        $taxService->reviewTaxExemption(
            $exemption,
            $this->admin,
            CustomerTaxExemption::STATUS_NEEDS_CORRECTION,
            'Please upload original PEZA certificate with seal'
        );

        $event = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'TAX_EVIDENCE_CORRECTION_REQUIRED')
            ->where('event_source_id', $exemption->id)
            ->first();

        $this->assertNotNull($event);
        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertStringContainsString('correction', $delivery->rendered_body);

        // Strict privacy: no PEZA certificate numbers or legal bases in SMS
        $this->assertStringNotContainsString('PEZA-2026-089', $delivery->rendered_body);
        $this->assertStringNotContainsString('Republic Act 7916', $delivery->rendered_body);
    }

    public function test_bill_claim_code_issued_emits_sms_intent_with_claim_code(): void
    {
        $walkInService = app(WalkInBillingService::class);
        $claimService = app(BillClaimService::class);

        // 1. Create walk-in customer and posted invoice with matching phone number
        $walkIn = $walkInService->createWalkInCustomer(
            $this->teller,
            $this->org->id,
            $this->location->id,
            [
                'buyer_name' => 'Santos Cargo Logistics Inc.',
                'buyer_tin' => '123-456-789-000',
                'buyer_branch_code' => '00000',
                'buyer_address' => 'Pier 1 Makar, General Santos City',
                'contact_mobile' => '+639171234567',
            ]
        );

        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $walkIn->shell_customer_id,
            'walk_in_customer_id' => $walkIn->id,
            'invoice_number' => 'SI-WI-000099',
            'status' => 'POSTED',
            'business_date' => now()->toDateString(),
            'posted_at' => now(),
            'currency' => 'PHP',
            'base_gross_amount' => '1000.00',
            'fuel_surcharge_amount' => '0.00',
            'gross_amount' => '1000.00',
            'ppa_amount' => '0.00',
            'tax_amount' => '120.00',
            'total_charge_amount' => '1120.00',
            'buyer_snapshot_name' => 'Santos Cargo Logistics Inc.',
            'created_by_user_id' => $this->teller->id,
            'lock_version' => 1,
        ]);

        // 2. Initiate claim as portal customer
        $claim = $claimService->initiateClaim($this->customerUser, $this->customer, $invoice->invoice_number);

        $this->assertEquals('CLAIM_CODE', $claim->verification_route);
        $this->assertEquals('PENDING_VERIFICATION', $claim->claim_status);

        // Verify BILL_CLAIM_CODE_ISSUED event
        $event = NotificationEvent::where('organization_id', $this->org->id)
            ->where('event_key', 'BILL_CLAIM_CODE_ISSUED')
            ->where('event_source_id', $claim->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->payload_snapshot['claim_code']);
        $this->assertEquals(6, strlen($event->payload_snapshot['claim_code']));

        // Verify Delivery contains code
        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertStringContainsString($event->payload_snapshot['claim_code'], $delivery->rendered_body);
        $this->assertStringContainsString('15 minutes', $delivery->rendered_body);
    }

    public function test_sms_intent_is_suppressed_if_contact_unverified(): void
    {
        // Unverify customer contact point
        CustomerContactPoint::where('user_id', $this->customerUser->id)->update([
            'is_verified' => false,
        ]);

        $queueService = app(BillingRequestQueueService::class);
        $request = $queueService->createDraft($this->customerUser, $this->customer, $this->location->id, 'GENERAL');
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);
        $queueService->attachDocument($request, $this->customerUser, $this->docType->id, $cleanFile->id);
        $submitted = $queueService->submitRequest($request, $this->customerUser);

        $event = NotificationEvent::where('event_key', 'BILLING_REQUEST_QUEUED')
            ->where('event_source_id', $submitted->id)
            ->first();

        $this->assertNotNull($event);

        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('suppressed', $delivery->status);
        $this->assertEquals('UNVERIFIED_MOBILE_CONTACT', $delivery->suppression_reason);
        $this->assertEquals(0, $delivery->attempt_count);
    }

    public function test_sms_intent_is_suppressed_if_customer_opted_out_in_preferences(): void
    {
        // Customer explicitly opts out of BILLING_REQUEST_QUEUED SMS
        NotificationPreferenceVersion::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customerUser->id,
            'version' => 1,
            'preferences' => [
                'BILLING_REQUEST_QUEUED' => ['sms' => false, 'in_app' => true],
            ],
            'effective_from' => now(),
        ]);

        $queueService = app(BillingRequestQueueService::class);
        $request = $queueService->createDraft($this->customerUser, $this->customer, $this->location->id, 'GENERAL');
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);
        $queueService->attachDocument($request, $this->customerUser, $this->docType->id, $cleanFile->id);
        $submitted = $queueService->submitRequest($request, $this->customerUser);

        $event = NotificationEvent::where('event_key', 'BILLING_REQUEST_QUEUED')
            ->where('event_source_id', $submitted->id)
            ->first();

        $this->assertNotNull($event);

        $delivery = NotificationDelivery::where('event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('suppressed', $delivery->status);
        $this->assertEquals('CUSTOMER_OPTED_OUT', $delivery->suppression_reason);
    }

    public function test_sms_failure_never_blocks_or_rolls_back_primary_transaction(): void
    {
        // Bind an orchestrator that throws an exception to simulate failure
        $brokenOrchestrator = $this->createMock(SmsDeliveryOrchestrator::class);
        $brokenOrchestrator->method('queueIntent')->willThrowException(new \RuntimeException('SMS Gateway Outage'));

        $this->app->instance(SmsDeliveryOrchestrator::class, $brokenOrchestrator);

        $queueService = app(BillingRequestQueueService::class);
        $request = $queueService->createDraft($this->customerUser, $this->customer, $this->location->id, 'GENERAL');
        $cleanFile = $this->createCleanPrivateFile($this->customerUser);
        $queueService->attachDocument($request, $this->customerUser, $this->docType->id, $cleanFile->id);

        // Submitting must NOT fail despite the broken SMS service
        $submitted = $queueService->submitRequest($request, $this->customerUser);

        $this->assertEquals(BillingRequest::STATUS_QUEUED, $submitted->status);
        $this->assertDatabaseHas('billing_requests', [
            'id' => $submitted->id,
            'status' => BillingRequest::STATUS_QUEUED,
        ]);
    }
}
