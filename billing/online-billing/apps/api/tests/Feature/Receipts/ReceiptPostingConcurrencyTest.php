<?php

namespace Tests\Feature\Receipts;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReceiptPostingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected User $admin;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'filesystems.disks.private' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing-concurrent-receipts'),
            ],
        ]);
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'RCPT-CONCURRENT-001',
            'name' => 'Concurrent Receipt Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $profile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);
        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Concurrent Receipt Customer, Inc.',
            'tin' => '111-222-333-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['street' => 'Makar Wharf', 'city' => 'General Santos City'],
            'contact_email' => 'concurrent.receipt@example.test',
            'contact_phone' => '+639171111121',
            'effective_from' => now()->subDay(),
            'status' => 'active',
        ]);
    }

    public function test_two_real_processes_cannot_over_allocate_one_invoice_and_retain_excess_as_unapplied(): void
    {
        $invoice = $this->postedInvoice();
        $barrierPath = tempnam(sys_get_temp_dir(), 'receipt-posting-barrier-');
        $workerPath = base_path('tests/Support/ConcurrentReceiptPostingWorker.php');
        $processes = [];

        try {
            foreach (['CONCURRENT-001', 'CONCURRENT-002'] as $index => $sourceKey) {
                $readyPath = $barrierPath.'.'.$index.'.ready';
                $payload = [
                    'source_type' => 'MANUAL_BANK_VERIFICATION',
                    'source_key' => $sourceKey,
                    'customer_id' => $this->customer->id,
                    'allocations' => [[
                        'invoice_id' => $invoice->id,
                        'tenders' => [[
                            'type' => 'BANK_TRANSFER',
                            'status' => 'CONFIRMED',
                            'amount' => (string) $invoice->total_charge_amount,
                            'reference' => $sourceKey,
                        ]],
                    ]],
                ];
                $command = sprintf(
                    '%s %s %s %s %d %s',
                    escapeshellarg(PHP_BINARY),
                    escapeshellarg($workerPath),
                    escapeshellarg($barrierPath),
                    escapeshellarg($readyPath),
                    $this->admin->id,
                    escapeshellarg(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)))
                );
                $pipes = [];
                $process = proc_open($command, [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = compact('process', 'pipes', 'readyPath');
            }

            $deadline = microtime(true) + 10;
            while (count(array_filter($processes, fn (array $worker) => file_exists($worker['readyPath']))) !== count($processes)) {
                $this->assertLessThan($deadline, microtime(true), 'Concurrent receipt workers did not reach their start barrier.');
                usleep(10_000);
            }

            unlink($barrierPath);

            $outcomes = array_map(function (array $worker): array {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exitCode = proc_close($worker['process']);

                $this->assertSame('', trim($stderr));
                $this->assertSame(0, $exitCode);

                return json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
            }, $processes);

            $posted = array_values(array_filter($outcomes, fn (array $outcome) => $outcome['status'] === 'posted'));
            $rejected = array_values(array_filter($outcomes, fn (array $outcome) => $outcome['status'] === 'rejected'));
            $receipts = Receipt::orderBy('id')->get();

            $this->assertCount(2, $posted, json_encode($outcomes, JSON_THROW_ON_ERROR));
            $this->assertCount(0, $rejected, json_encode($outcomes, JSON_THROW_ON_ERROR));
            $this->assertCount(2, $receipts);
            $this->assertDatabaseCount('receipt_allocations', 1);
            $this->assertSame((string) $invoice->total_charge_amount, (string) ReceiptAllocation::where('invoice_id', $invoice->id)->sum('applied_amount'));
            $this->assertSame((string) $invoice->total_charge_amount, bcadd((string) $receipts->sum('applied_amount'), '0.00', 2));
            $this->assertSame((string) $invoice->total_charge_amount, bcadd((string) $receipts->sum('unapplied_amount'), '0.00', 2));
            $this->assertCount(1, $receipts->filter(fn (Receipt $receipt) => bccomp($receipt->applied_amount, '0.00', 2) === 0));
            $this->assertDatabaseCount('receipt_posting_sources', 2);
            $this->assertDatabaseCount('document_numbers', 3); // one invoice and two receipt numbers
        } finally {
            foreach ($processes as $worker) {
                if (is_resource($worker['process'])) {
                    foreach ($worker['pipes'] as $pipe) {
                        if (is_resource($pipe)) {
                            fclose($pipe);
                        }
                    }
                    proc_terminate($worker['process']);
                    proc_close($worker['process']);
                }
                if (file_exists($worker['readyPath'])) {
                    unlink($worker['readyPath']);
                }
            }
            if (file_exists($barrierPath)) {
                unlink($barrierPath);
            }
            File::deleteDirectory(storage_path('framework/testing-concurrent-receipts'));
        }
    }

    protected function postedInvoice(): Invoice
    {
        $draft = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/invoices/drafts', [
            'customer_id' => $this->customer->id,
            ...$this->invoiceShipmentPayload(),
            'items' => [['tariff_code' => 'STEV_DOM', 'quantity' => 10]],
        ]);
        $draft->assertCreated();
        $invoiceId = $draft->json('data.id');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/invoices/drafts/{$invoiceId}/post", [
            'expected_version' => 1,
        ])->assertOk();

        return Invoice::findOrFail($invoiceId);
    }
}
