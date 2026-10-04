<?php

namespace Tests\Feature\Reports;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected User $otherTeller;

    protected Customer $customer;

    protected int $primaryLocationId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->teller = User::where('email', 'teller1@scipsi.test')->firstOrFail();
        $this->otherTeller = User::where('email', 'teller2@scipsi.test')->firstOrFail();
        $this->primaryLocationId = (int) $this->admin->locations()->value('locations.id');
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'DASH-001',
            'name' => 'Dashboard Test Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create(['customer_id' => $this->customer->id, 'current_version' => 1, 'is_active' => true]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Dashboard Test Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City', 'province' => 'South Cotabato'],
            'contact_email' => 'dashboard@example.test',
            'contact_phone' => '+639171111117',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
    }

    public function test_administrator_dashboard_separates_today_activity_open_bills_and_teller_rows(): void
    {
        $openInvoice = $this->postedInvoice();
        $paidInvoice = $this->postedInvoice();
        $official = $this->postReceipt($paidInvoice, (string) $paidInvoice->total_charge_amount, 'DASH-OR-001');
        $acknowledgement = $this->postReceipt($openInvoice, '10.00', 'DASH-ACK-001');
        $acknowledgement->update([
            'receipt_kind' => Receipt::KIND_ACKNOWLEDGEMENT,
            'counts_as_official_receipt' => false,
        ]);
        foreach ([$openInvoice, $paidInvoice, $official, $acknowledgement] as $document) {
            $document->update(['posted_by_user_id' => $this->teller->id]);
        }

        $otherOrganization = Organization::create([
            'name' => 'Other Dashboard Organization',
            'code' => 'DASH-OTHER',
            'is_active' => true,
        ]);
        $otherLocation = Location::create([
            'organization_id' => $otherOrganization->id,
            'code' => 'DASH-OTHER-LOC',
            'name' => 'Other Dashboard Location',
            'is_active' => true,
        ]);
        Invoice::create([
            'organization_id' => $otherOrganization->id,
            'location_id' => $otherLocation->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'SI-OTHER-DASH-001',
            'status' => Invoice::STATUS_POSTED,
            'business_date' => now('Asia/Manila')->toDateString(),
            'currency' => 'PHP',
            'gross_amount' => '999.00',
            'net_amount' => '999.00',
            'total_charge_amount' => '999.00',
            'posted_by_user_id' => $this->teller->id,
            'posted_at' => now(),
            'lock_version' => 1,
        ]);

        $expectedBilled = bcadd((string) $openInvoice->total_charge_amount, (string) $paidInvoice->total_charge_amount, 2);
        $expectedOpen = bcsub((string) $openInvoice->total_charge_amount, '10.00', 2);

        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.business_date', now('Asia/Manila')->toDateString())
            ->assertJsonPath('data.currencies.0.currency', 'PHP')
            ->assertJsonPath('data.currencies.0.bills_posted_today.count', 2)
            ->assertJsonPath('data.currencies.0.bills_posted_today.amount', $expectedBilled)
            ->assertJsonPath('data.currencies.0.official_receipts_posted_today.count', 1)
            ->assertJsonPath('data.currencies.0.official_receipts_posted_today.amount', (string) $paidInvoice->total_charge_amount)
            ->assertJsonPath('data.currencies.0.acknowledgements_posted_today.count', 1)
            ->assertJsonPath('data.currencies.0.acknowledgements_posted_today.amount', '10.00')
            ->assertJsonPath('data.currencies.0.open_bills.count', 1)
            ->assertJsonPath('data.currencies.0.open_bills.amount', $expectedOpen);

        $tellers = collect($response->json('data.tellers'));
        $this->assertFalse($tellers->contains(fn (array $row): bool => $row['name'] === $this->otherTeller->name));
        $row = $tellers->first(fn (array $teller): bool => $teller['user_id'] === $this->teller->id);
        $this->assertNotNull($row);
        $this->assertSame($this->teller->name, $row['name']);
        $this->assertSame(2, $row['bills_posted_today']['count']);
        $this->assertSame($expectedBilled, $row['bills_posted_today']['amount']);
        $this->assertSame(1, $row['official_receipts_posted_today']['count']);
        $this->assertSame((string) $paidInvoice->total_charge_amount, $row['official_receipts_posted_today']['amount']);
        $this->assertSame(1, $row['acknowledgements_posted_today']['count']);
        $this->assertSame('10.00', $row['acknowledgements_posted_today']['amount']);
        $this->assertStringNotContainsString('999.00', json_encode($response->json('data.currencies')));
    }

    public function test_administrator_dashboard_reports_seven_separate_activity_days(): void
    {
        $openInvoice = $this->postedInvoice();
        $paidInvoice = $this->postedInvoice();
        $this->postReceipt($paidInvoice, (string) $paidInvoice->total_charge_amount, 'DASH-OR-002');
        $acknowledgement = $this->postReceipt($openInvoice, '10.00', 'DASH-ACK-002');
        $acknowledgement->update([
            'receipt_kind' => Receipt::KIND_ACKNOWLEDGEMENT,
            'counts_as_official_receipt' => false,
        ]);

        $today = now('Asia/Manila')->toDateString();
        $expectedBilled = bcadd((string) $openInvoice->total_charge_amount, (string) $paidInvoice->total_charge_amount, 2);

        $days = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->json('data.currencies.0.last_7_days');

        $this->assertCount(7, $days);
        $this->assertSame(
            now('Asia/Manila')->subDays(6)->toDateString(),
            $days[0]['business_date']
        );
        $this->assertSame($today, $days[6]['business_date']);

        $this->assertSame(2, $days[6]['bills']['count']);
        $this->assertSame($expectedBilled, $days[6]['bills']['amount']);
        $this->assertSame(1, $days[6]['official_receipts']['count']);
        $this->assertSame((string) $paidInvoice->total_charge_amount, $days[6]['official_receipts']['amount']);
        $this->assertSame(1, $days[6]['acknowledgements']['count']);
        $this->assertSame('10.00', $days[6]['acknowledgements']['amount']);

        foreach (array_slice($days, 0, 6) as $day) {
            $this->assertSame(0, $day['bills']['count']);
            $this->assertSame('0.00', $day['bills']['amount']);
            $this->assertSame('0.00', $day['official_receipts']['amount']);
            $this->assertSame('0.00', $day['acknowledgements']['amount']);
        }
    }

    private function postedInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $this->customer->id,
            ...$this->invoiceShipmentPayload(),
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
