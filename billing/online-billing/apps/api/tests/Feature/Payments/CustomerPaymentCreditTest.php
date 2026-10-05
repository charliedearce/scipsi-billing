<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\DocumentSeries;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\ReceiptPostingSource;
use App\Models\User;
use App\Services\Billing\CustomerPaymentCreditService;
use App\Services\Billing\InvoiceSettlementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPaymentCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->customer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'OVERPAY-001',
            'name' => 'Overpayment Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
    }

    public function test_confirmed_manual_deposit_excess_creates_one_credit_lot_on_retry(): void
    {
        $invoice = $this->invoice('1000.00');
        $receipt = $this->receipt('1050.00', '1000.00', $invoice);
        $credits = app(CustomerPaymentCreditService::class);

        $first = $credits->createFromReceipt($receipt, $this->admin);
        $second = $credits->createFromReceipt($receipt, $this->admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('50.00', $credits->availableForCustomer($this->admin->organization_id, $this->customer->id, 'PHP'));
        $this->assertDatabaseCount('customer_payment_credits', 1);
        $this->assertDatabaseHas('customer_payment_credit_movements', ['credit_id' => $first->id, 'type' => 'CREATED', 'amount' => '50.00']);
        $this->assertDatabaseCount('customer_payment_credit_movements', 1);
    }

    public function test_credit_applies_cash_excess_to_future_bills_without_new_cash(): void
    {
        $sourceBill = $this->invoice('1000.00');
        $receipt = $this->receipt('1075.00', '1000.00', $sourceBill);
        $credits = app(CustomerPaymentCreditService::class);
        $credits->createFromReceipt($receipt, $this->admin);
        $firstBill = $this->invoice('30.00');
        $secondBill = $this->invoice('50.00');

        $lots = $credits->lockAvailableLots($this->customer, 'PHP');
        $result = $credits->applyToLockedInvoices($this->admin, $this->customer, $lots, collect([$firstBill, $secondBill]), 'checkout-1');

        $this->assertSame('75.00', $result['total']);
        $this->assertSame('30.00', $result['by_invoice'][$firstBill->id]);
        $this->assertSame('45.00', $result['by_invoice'][$secondBill->id]);
        $this->assertSame('0.00', $credits->availableForCustomer($this->admin->organization_id, $this->customer->id, 'PHP'));
        $this->assertSame('0.00', app(InvoiceSettlementService::class)->outstanding($firstBill));
        $this->assertSame('5.00', app(InvoiceSettlementService::class)->outstanding($secondBill));
        $this->assertDatabaseCount('receipt_tenders', 0);
    }

    public function test_cross_customer_application_is_rejected_without_spending_credit(): void
    {
        $receipt = $this->receipt('20.00', '0.00', null);
        $credits = app(CustomerPaymentCreditService::class);
        $credits->createFromReceipt($receipt, $this->admin);
        $otherCustomer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'OVERPAY-002',
            'name' => 'Other Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $otherInvoice = $this->invoice('20.00', $otherCustomer);

        try {
            $credits->applyToLockedInvoices($this->admin, $otherCustomer, $credits->lockAvailableLots($this->customer, 'PHP'), collect([$otherInvoice]), 'wrong-customer');
            $this->fail('Cross-customer credit was accepted.');
        } catch (ValidationException) {
            $this->assertSame('20.00', $credits->availableForCustomer($this->admin->organization_id, $this->customer->id, 'PHP'));
            $this->assertSame('20.00', app(InvoiceSettlementService::class)->outstanding($otherInvoice));
        }
    }

    public function test_checkout_key_cannot_be_reused_for_another_customers_invoice(): void
    {
        $credits = app(CustomerPaymentCreditService::class);
        $credits->createFromReceipt($this->receipt('20.00', '0.00', null), $this->admin);
        $firstBill = $this->invoice('10.00');
        $lots = $credits->lockAvailableLots($this->customer, 'PHP');
        $credits->applyToLockedInvoices($this->admin, $this->customer, $lots, collect([$firstBill]), 'checkout-shared');
        $otherCustomer = Customer::create([
            'organization_id' => $this->admin->organization_id,
            'account_number' => 'OVERPAY-003',
            'name' => 'Second Customer',
            'status' => 'active',
            'customer_type' => 'business',
        ]);
        $otherBill = $this->invoice('10.00', $otherCustomer);

        $this->expectException(ValidationException::class);
        $credits->applyToLockedInvoices($this->admin, $otherCustomer, collect(), collect([$otherBill]), 'checkout-shared');
    }

    public function test_unused_source_credit_reverses_once_and_spent_credit_blocks_reversal(): void
    {
        $credits = app(CustomerPaymentCreditService::class);
        $unusedReceipt = $this->receipt('12.34', '0.00', null);
        $credits->createFromReceipt($unusedReceipt, $this->admin);
        $credits->reverseUnusedForReceipt($unusedReceipt, $this->admin);
        $credits->reverseUnusedForReceipt($unusedReceipt, $this->admin);
        $this->assertSame('0.00', $credits->availableForCustomer($this->admin->organization_id, $this->customer->id, 'PHP'));
        $this->assertDatabaseCount('customer_payment_credit_movements', 2);

        $spentReceipt = $this->receipt('5.00', '0.00', null);
        $credits->createFromReceipt($spentReceipt, $this->admin);
        $bill = $this->invoice('5.00');
        $credits->applyToLockedInvoices($this->admin, $this->customer, $credits->lockAvailableLots($this->customer, 'PHP'), collect([$bill]), 'spent-checkout');
        $this->expectException(ValidationException::class);
        $credits->reverseUnusedForReceipt($spentReceipt, $this->admin);
    }

    public function test_credit_is_currency_scoped(): void
    {
        $credits = app(CustomerPaymentCreditService::class);
        $credits->createFromReceipt($this->receipt('10.00', '0.00', null), $this->admin);
        $this->assertSame('0.00', $credits->availableForCustomer($this->admin->organization_id, $this->customer->id, 'USD'));
        $usdInvoice = $this->invoice('10.00');
        $usdInvoice->update(['currency' => 'USD']);
        $this->expectException(ValidationException::class);
        $credits->applyToLockedInvoices($this->admin, $this->customer, $credits->lockAvailableLots($this->customer, 'PHP'), collect([$usdInvoice]), 'wrong-currency');
    }

    private function invoice(string $amount, ?Customer $customer = null): Invoice
    {
        return Invoice::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'status' => 'POSTED',
            'business_date' => now()->toDateString(),
            'currency' => 'PHP',
            'total_charge_amount' => $amount,
        ]);
    }

    private function receipt(string $cash, string $applied, ?Invoice $invoice): Receipt
    {
        $source = ReceiptPostingSource::create([
            'organization_id' => $this->admin->organization_id,
            'source_type' => 'MANUAL_PAYMENT_PROOF',
            'source_key' => 'proof-'.ReceiptPostingSource::count(),
            'payload_fingerprint' => str_repeat('a', 64),
        ]);
        $series = DocumentSeries::where('organization_id', $this->admin->organization_id)
            ->where('document_type', 'COLLECTION_RECEIPT')->firstOrFail();
        $receipt = Receipt::create([
            'organization_id' => $this->admin->organization_id,
            'customer_id' => $this->customer->id,
            'series_id' => $series->id,
            'posting_source_id' => $source->id,
            'status' => 'POSTED',
            'business_date' => now()->toDateString(),
            'currency' => 'PHP',
            'payer_snapshot' => ['customer_id' => $this->customer->id],
            'cash_received_amount' => $cash,
            'applied_amount' => $applied,
            'unapplied_amount' => bcsub($cash, $applied, 2),
            'posted_by_user_id' => $this->admin->id,
            'posted_at' => now(),
        ]);
        $source->update(['receipt_id' => $receipt->id]);
        if ($invoice !== null && bccomp($applied, '0.00', 2) > 0) {
            ReceiptAllocation::create([
                'receipt_id' => $receipt->id,
                'invoice_id' => $invoice->id,
                'cash_applied_amount' => $applied,
                'withholding_applied_amount' => '0.00',
                'applied_amount' => $applied,
            ]);
        }

        return $receipt;
    }
}
