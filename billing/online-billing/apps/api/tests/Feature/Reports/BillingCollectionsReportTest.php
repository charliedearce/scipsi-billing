<?php

namespace Tests\Feature\Reports;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingCollectionsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected Customer $customer;

    protected int $primaryLocationId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->teller = User::where('email', 'teller1@scipsi.test')->firstOrFail();
        $this->primaryLocationId = (int) $this->admin->locations()->value('locations.id');
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'RPT-001',
            'name' => 'Report Test Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Report Test Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'reports@example.test',
            'contact_phone' => '+639171111116',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
    }

    public function test_register_keeps_issued_and_collected_activity_separate_and_exports_audited_spreadsheet_safe_csv(): void
    {
        $issued = $this->postedInvoice();
        $cancelled = $this->postedInvoice();
        $cancelled->update(['status' => 'CANCELLED']);
        $receipt = $this->postReceipt($issued, '100.00', 'RPT-RECEIPT-001');
        $receipt->update(['payer_snapshot' => ['registered_name' => '=Unsafe payer']]);
        $date = now('Asia/Manila')->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/reports/billing-collections?date_from={$date}&date_to={$date}")
            ->assertOk();

        $response
            ->assertJsonPath('data.report.code', 'BILLING_COLLECTIONS_REGISTER')
            ->assertJsonPath('data.report.filters.date_from', $date)
            ->assertJsonPath('data.totals_by_currency.0.invoices.count', 1)
            ->assertJsonPath('data.totals_by_currency.0.invoices.billed_amount', (string) $issued->total_charge_amount)
            ->assertJsonPath('data.totals_by_currency.0.receipts.count', 1)
            ->assertJsonPath('data.totals_by_currency.0.receipts.cash_received_amount', '100.00')
            ->assertJsonPath('data.totals_by_currency.0.receipts.applied_amount', '100.00');
        $this->assertSame(2, $response->json('data.pagination.total'));
        $this->assertStringContainsString('separate activity streams', $response->json('data.report.interpretation_notice'));

        $export = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/reports/billing-collections/export?date_from={$date}&date_to={$date}");
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $export->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Document type', $content);
        $this->assertStringContainsString("'=Unsafe payer", $content);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'BILLING_COLLECTIONS_REPORT_EXPORTED',
            'aggregate_type' => 'REPORT_EXPORT',
            'actor_id' => $this->admin->id,
            'permission_snapshot' => 'reports:export',
        ]);
    }

    public function test_register_and_export_respect_teller_location_scope(): void
    {
        $primaryInvoice = $this->postedInvoice();
        $otherLocation = Location::create([
            'organization_id' => $this->admin->organization_id,
            'code' => 'OTHER',
            'name' => 'Other Reporting Location',
            'is_active' => true,
        ]);
        Invoice::create([
            'organization_id' => $this->admin->organization_id,
            'location_id' => $otherLocation->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'SI-OTHER-REPORT-001',
            'status' => 'POSTED',
            'business_date' => now('Asia/Manila')->toDateString(),
            'currency' => 'PHP',
            'gross_amount' => '500.00',
            'net_amount' => '500.00',
            'total_charge_amount' => '500.00',
            'posted_by_user_id' => $this->admin->id,
            'posted_at' => now(),
            'lock_version' => 1,
        ]);
        $date = now('Asia/Manila')->toDateString();

        $scoped = $this->actingAs($this->teller, 'sanctum')
            ->getJson("/api/v1/reports/billing-collections?date_from={$date}&date_to={$date}")
            ->assertOk();
        $scoped->assertJsonPath('data.totals_by_currency.0.invoices.count', 1);
        $this->assertSame($primaryInvoice->invoice_number, $scoped->json('data.rows.0.document_number'));

        $this->actingAs($this->teller, 'sanctum')
            ->getJson("/api/v1/reports/billing-collections?date_from={$date}&date_to={$date}&location_id={$otherLocation->id}")
            ->assertForbidden();
    }

    private function postedInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $this->customer->id,
            'business_date' => now('Asia/Manila')->toDateString(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ])->assertCreated();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts/'.$draft->json('data.id').'/post', ['expected_version' => 1])
            ->assertOk();

        return Invoice::findOrFail($draft->json('data.id'));
    }

    private function postReceipt(Invoice $invoice, string $amount, string $key): Receipt
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/receipts', [
            'source_type' => 'MANUAL_BANK_VERIFICATION',
            'source_key' => $key,
            'customer_id' => $this->customer->id,
            'location_id' => $this->primaryLocationId,
            'allocations' => [[
                'invoice_id' => $invoice->id,
                'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => $amount, 'reference' => $key]],
            ]],
        ])->assertOk();

        return Receipt::findOrFail($response->json('data.id'));
    }
}
