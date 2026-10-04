<?php

namespace Tests\Feature\Tax;

use App\Models\Customer;
use App\Models\CustomerContactPoint;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerUserLink;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentType;
use App\Models\InAppNotification;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\Organization;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\TaxEvidenceExpiryService;
use App\Services\Billing\TaxEvidenceService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaxEvidenceExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Location $location;

    protected User $admin;

    protected User $customerUser;

    protected Customer $customer;

    protected DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local_private');
        Storage::fake('private');

        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->location = Location::firstOrFail();
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->docType = DocumentType::where('organization_id', $this->org->id)
            ->where('code', 'BIR_2307')
            ->firstOrFail();

        $customerRole = Role::where('name', 'Customer')->firstOrFail();
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'CUST-TAX-EXP-001',
            'name' => 'Expiry Alert Customer',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);
        $this->customerUser = User::create([
            'name' => 'Expiry Customer User',
            'email' => 'tax.expiry@scipsi.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach($customerRole->id);
        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'owner',
            'is_active' => true,
            'linked_at' => now(),
        ]);
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'type' => 'mobile',
            'value' => '+639179990001',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);
    }

    protected function createCleanPrivateFile(): PrivateFile
    {
        $file = PrivateFile::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'document_type_id' => $this->docType->id,
            'purpose' => 'WITHHOLDING_CERTIFICATE',
            'uploaded_by' => $this->customerUser->id,
            'current_version' => 1,
            'status' => 'ACTIVE',
        ]);

        PrivateFileVersion::create([
            'private_file_id' => $file->id,
            'version_number' => 1,
            'disk' => 'local_private',
            'file_path' => "uploads/tax_expiry_{$file->id}.pdf",
            'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => hash('sha256', 'tax-expiry-'.$file->id),
            'scan_status' => 'CLEAN',
            'uploaded_by' => $this->customerUser->id,
            'created_at' => now(),
        ]);

        return $file->fresh();
    }

    protected function approveWithholding(string $periodTo): CustomerWithholdingCertificate
    {
        $tax = app(TaxEvidenceService::class);
        $file = $this->createCleanPrivateFile();
        $cert = $tax->submitWithholdingCertificate($this->customerUser, $this->customer, [
            'certificate_no' => '2307-EXP-'.uniqid(),
            'private_file_id' => $file->id,
            'payor_tin' => '111-222-333-000',
            'payor_name' => 'Expiry Alert Customer',
            'period_from' => '2026-01-01',
            'period_to' => $periodTo,
            'atc_code' => 'WC100',
            'income_payment_base' => '10000.00',
            'withholding_rate' => '0.0100',
            'certified_amount' => '100.00',
        ]);

        return $tax->reviewWithholdingCertificate(
            $cert,
            $this->admin,
            CustomerWithholdingCertificate::STATUS_APPROVED,
            'Approved for expiry scheduler tests'
        );
    }

    protected function approveExemption(string $validTo): CustomerTaxExemption
    {
        $tax = app(TaxEvidenceService::class);
        $file = $this->createCleanPrivateFile();
        $exemption = $tax->submitTaxExemption($this->customerUser, $this->customer, [
            'exemption_type' => CustomerTaxExemption::TYPE_ZERO_RATED,
            'legal_basis' => 'PEZA',
            'ruling_or_cert_no' => 'PEZA-EXP-'.uniqid(),
            'valid_from' => '2026-01-01',
            'valid_to' => $validTo,
            'covered_services' => ['ALL'],
            'private_file_id' => $file->id,
        ]);

        return $tax->reviewTaxExemption(
            $exemption,
            $this->admin,
            CustomerTaxExemption::STATUS_APPROVED,
            'Approved for expiry scheduler tests'
        );
    }

    public function test_approaching_expiry_creates_single_in_app_tax_notification(): void
    {
        $asOf = Carbon::parse('2026-09-22', TaxEvidenceExpiryService::TIMEZONE)->startOfDay();
        $cert = $this->approveWithholding('2026-10-10'); // 18 days remaining → < 30

        $service = app(TaxEvidenceExpiryService::class);
        $first = $service->processOrganization((int) $this->org->id, $asOf);
        $second = $service->processOrganization((int) $this->org->id, $asOf);

        $this->assertSame(1, $first['approaching_withholding']);
        $this->assertSame(0, $first['expired_withholding']);
        $this->assertSame(0, $second['approaching_withholding']);
        $this->assertSame(0, $second['notifications_created']);

        $this->assertSame(CustomerWithholdingCertificate::STATUS_APPROVED, $cert->fresh()->status);

        $notice = InAppNotification::query()
            ->where('user_id', $this->customerUser->id)
            ->where('type', 'TAX')
            ->where('data->dedupe_key', "tax-expiring:withholding:{$cert->id}:2026-10-10")
            ->first();

        $this->assertNotNull($notice);
        $this->assertStringContainsString('expiring', strtolower($notice->title));
        $this->assertSame('approaching', $notice->data['alert'] ?? null);
        $this->assertSame('/my-tax-evidence', $notice->data['renew_path'] ?? null);
        $this->assertSame(
            1,
            InAppNotification::query()
                ->where('user_id', $this->customerUser->id)
                ->where('data->alert', 'approaching')
                ->count()
        );

        $this->assertSame(
            0,
            NotificationEvent::query()->where('event_key', 'TAX_EVIDENCE_EXPIRED')->count()
        );
    }

    public function test_expired_evidence_marks_status_notifies_and_queues_sms_once(): void
    {
        $asOf = Carbon::parse('2026-09-22', TaxEvidenceExpiryService::TIMEZONE)->startOfDay();
        $cert = $this->approveWithholding('2026-09-15');
        $exemption = $this->approveExemption('2026-09-01');

        Artisan::call('tax:process-evidence-expiry', [
            '--organization' => $this->org->id,
            '--as-of' => '2026-09-22',
        ]);

        $this->assertSame(CustomerWithholdingCertificate::STATUS_EXPIRED, $cert->fresh()->status);
        $this->assertSame(CustomerTaxExemption::STATUS_EXPIRED, $exemption->fresh()->status);

        $this->assertDatabaseHas('customer_tax_evidence_events', [
            'evidence_type' => 'WITHHOLDING_CERTIFICATE',
            'evidence_id' => $cert->id,
            'event_type' => 'EXPIRED',
            'to_status' => 'EXPIRED',
        ]);

        $expiredNotices = InAppNotification::query()
            ->where('user_id', $this->customerUser->id)
            ->where('type', 'TAX')
            ->where('data->alert', 'expired')
            ->get();
        $this->assertCount(2, $expiredNotices);

        $smsEvents = NotificationEvent::query()
            ->where('event_key', 'TAX_EVIDENCE_EXPIRED')
            ->where('user_id', $this->customerUser->id)
            ->get();
        $this->assertCount(2, $smsEvents);

        foreach ($smsEvents as $event) {
            $delivery = NotificationDelivery::where('event_id', $event->id)->first();
            $this->assertNotNull($delivery);
            $this->assertSame('queued_local', $delivery->status);
            $this->assertStringContainsString('expired', strtolower($delivery->rendered_body));
            $this->assertStringNotContainsString($cert->certificate_no, $delivery->rendered_body);
            $this->assertStringNotContainsString('100.00', $delivery->rendered_body);
        }

        Artisan::call('tax:process-evidence-expiry', [
            '--organization' => $this->org->id,
            '--as-of' => '2026-09-22',
        ]);

        $this->assertSame(
            2,
            InAppNotification::query()
                ->where('user_id', $this->customerUser->id)
                ->where('data->alert', 'expired')
                ->count()
        );
        $this->assertSame(
            2,
            NotificationEvent::query()->where('event_key', 'TAX_EVIDENCE_EXPIRED')->count()
        );
    }

    public function test_exactly_thirty_days_remaining_does_not_trigger_approaching_alert(): void
    {
        $asOf = Carbon::parse('2026-09-22', TaxEvidenceExpiryService::TIMEZONE)->startOfDay();
        // 30 days remaining → not "less than 30"
        $this->approveWithholding('2026-10-22');

        $result = app(TaxEvidenceExpiryService::class)->processOrganization((int) $this->org->id, $asOf);

        $this->assertSame(0, $result['approaching_withholding']);
        $this->assertSame(0, $result['notifications_created']);
    }

    public function test_open_ended_exemption_is_never_auto_expired(): void
    {
        $tax = app(TaxEvidenceService::class);
        $file = $this->createCleanPrivateFile();
        $exemption = $tax->submitTaxExemption($this->customerUser, $this->customer, [
            'exemption_type' => CustomerTaxExemption::TYPE_VAT_EXEMPT,
            'legal_basis' => 'NIRC Sec. 109',
            'ruling_or_cert_no' => 'OPEN-ENDED-001',
            'valid_from' => '2020-01-01',
            'valid_to' => null,
            'covered_services' => ['ALL'],
            'private_file_id' => $file->id,
        ]);
        $tax->reviewTaxExemption(
            $exemption,
            $this->admin,
            CustomerTaxExemption::STATUS_APPROVED,
            'Open-ended ruling'
        );

        $result = app(TaxEvidenceExpiryService::class)->processOrganization(
            (int) $this->org->id,
            Carbon::parse('2030-01-01', TaxEvidenceExpiryService::TIMEZONE)->startOfDay()
        );

        $this->assertSame(CustomerTaxExemption::STATUS_APPROVED, $exemption->fresh()->status);
        $this->assertSame(0, $result['expired_exemptions']);
    }
}
