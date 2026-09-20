<?php

namespace Tests\Unit\Settlement;

use App\Services\Billing\DecimalCalculatorService;
use App\Services\Billing\SettlementCalculatorService;
use InvalidArgumentException;
use Tests\TestCase;

class SettlementCalculatorTest extends TestCase
{
    protected SettlementCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new SettlementCalculatorService(new DecimalCalculatorService);
    }

    public function test_cash_and_approved_withholding_settle_invoice_without_becoming_a_discount(): void
    {
        $result = $this->calculator->calculate(
            invoiceTotal: '11200.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_BANK_TRANSFER,
                'status' => SettlementCalculatorService::STATUS_CONFIRMED,
                'amount' => '11000.00',
            ]],
            withholdingApplications: [[
                'certificate_id' => 'CWT-2307-001',
                'status' => 'APPROVED',
                'amount' => '200.00',
                'available_amount' => '200.00',
            ]]
        );

        $this->assertSame('11200.00', $result['invoice_total']);
        $this->assertSame('11000.00', $result['current']['cash_confirmed_amount']);
        $this->assertSame('200.00', $result['current']['withholding_approved_amount']);
        $this->assertSame('11200.00', $result['current']['confirmed_amount']);
        $this->assertSame('11000.00', $result['current']['cash_applied_amount']);
        $this->assertSame('200.00', $result['current']['withholding_applied_amount']);
        $this->assertSame('0.00', $result['balance_after']);
        $this->assertSame('PAID', $result['status']);
        $this->assertTrue($result['withholding_is_separate_from_discount']);
    }

    public function test_actual_partial_funds_remain_partial(): void
    {
        $result = $this->calculator->calculate(
            invoiceTotal: '1000.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_BANK_TRANSFER,
                'status' => SettlementCalculatorService::STATUS_CONFIRMED,
                'amount' => '400.00',
            ]]
        );

        $this->assertSame('400.00', $result['applied_amount']);
        $this->assertSame('600.00', $result['balance_after']);
        $this->assertSame('PARTIAL', $result['status']);
        $this->assertTrue($result['is_partial']);
    }

    public function test_pending_check_does_not_reduce_balance_until_cleared(): void
    {
        $pending = $this->calculator->calculate(
            invoiceTotal: '1000.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_CHECK,
                'status' => SettlementCalculatorService::STATUS_PENDING_CLEARANCE,
                'amount' => '1000.00',
            ]]
        );

        $this->assertSame('1000.00', $pending['balance_after']);
        $this->assertSame('1000.00', $pending['current']['pending_amount']);
        $this->assertSame('1000.00', $pending['current']['pending_check_amount']);
        $this->assertTrue($pending['pending_clearance']);
        $this->assertSame('UNPAID', $pending['status']);

        $cleared = $this->calculator->calculate(
            invoiceTotal: '1000.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_CHECK,
                'status' => SettlementCalculatorService::STATUS_CLEARED,
                'amount' => '1000.00',
            ]]
        );

        $this->assertSame('0.00', $cleared['balance_after']);
        $this->assertSame('PAID', $cleared['status']);
    }

    public function test_confirmed_overpayment_is_reported_as_unapplied_surplus(): void
    {
        $result = $this->calculator->calculate(
            invoiceTotal: '100.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_CASH,
                'status' => SettlementCalculatorService::STATUS_CONFIRMED,
                'amount' => '125.00',
            ]]
        );

        $this->assertSame('100.00', $result['applied_amount']);
        $this->assertSame('25.00', $result['overpayment_amount']);
        $this->assertSame('25.00', $result['current']['unapplied_cash_amount']);
        $this->assertSame('PAID_WITH_OVERPAYMENT', $result['status']);
    }

    public function test_pending_withholding_evidence_does_not_settle_invoice(): void
    {
        $result = $this->calculator->calculate(
            invoiceTotal: '1000.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_BANK_TRANSFER,
                'status' => SettlementCalculatorService::STATUS_CONFIRMED,
                'amount' => '950.00',
            ]],
            withholdingApplications: [[
                'certificate_id' => 'CWT-LATE-001',
                'status' => SettlementCalculatorService::STATUS_PENDING_REVIEW,
                'amount' => '50.00',
            ]]
        );

        $this->assertSame('950.00', $result['applied_amount']);
        $this->assertSame('50.00', $result['balance_after']);
        $this->assertSame('50.00', $result['current']['pending_withholding_amount']);
        $this->assertSame('PARTIAL', $result['status']);
    }

    public function test_certificate_capacity_and_duplicate_use_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('remaining capacity');

        $this->calculator->calculate(
            invoiceTotal: '1000.00',
            withholdingApplications: [[
                'certificate_id' => 'CWT-CAP-001',
                'status' => 'APPROVED',
                'amount' => '101.00',
                'available_amount' => '100.00',
            ]]
        );
    }

    public function test_duplicate_certificate_application_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('more than once');

        $this->calculator->calculate(
            invoiceTotal: '1000.00',
            withholdingApplications: [
                [
                    'certificate_id' => 'CWT-DUP-001',
                    'status' => 'APPROVED',
                    'amount' => '50.00',
                    'available_amount' => '100.00',
                ],
                [
                    'certificate_id' => 'CWT-DUP-001',
                    'status' => 'APPROVED',
                    'amount' => '50.00',
                    'available_amount' => '50.00',
                ],
            ]
        );
    }

    public function test_regular_customer_checkout_requires_full_remaining_balance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('full remaining balance');

        $this->calculator->validateRegularCustomerCheckoutAmount('1000.00', '999.99');
    }

    public function test_regular_customer_full_balance_checkout_is_accepted(): void
    {
        $result = $this->calculator->validateRegularCustomerCheckoutAmount('1000.00', '1000');

        $this->assertSame('1000.00', $result['requested_amount']);
        $this->assertTrue($result['is_full_balance']);
    }

    public function test_cash_is_applied_before_withholding_to_protect_certificate_capacity(): void
    {
        $result = $this->calculator->calculate(
            invoiceTotal: '100.00',
            currentTenders: [[
                'type' => SettlementCalculatorService::TENDER_CASH,
                'status' => SettlementCalculatorService::STATUS_CONFIRMED,
                'amount' => '100.00',
            ]],
            withholdingApplications: [[
                'certificate_id' => 'CWT-CASH-FIRST-001',
                'status' => 'APPROVED',
                'amount' => '10.00',
                'available_amount' => '10.00',
            ]]
        );

        $this->assertSame('100.00', $result['current']['cash_applied_amount']);
        $this->assertSame('0.00', $result['current']['withholding_applied_amount']);
        $this->assertSame('10.00', $result['current']['unapplied_withholding_amount']);
        $this->assertSame('PAID_WITH_OVERPAYMENT', $result['status']);
    }
}
