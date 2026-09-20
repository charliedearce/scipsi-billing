<?php

namespace App\Services\Billing;

use InvalidArgumentException;

/**
 * Server-side settlement equations used by payment review and receipt posting.
 *
 * This class intentionally has no database side effects. P3-02 must lock the
 * invoice, source/payment identity and withholding certificates before using
 * this result to post a receipt or consume certificate capacity.
 */
class SettlementCalculatorService
{
    public const STATUS_UNPAID = 'UNPAID';

    public const STATUS_PARTIAL = 'PARTIAL';

    public const STATUS_PAID = 'PAID';

    public const STATUS_PAID_WITH_OVERPAYMENT = 'PAID_WITH_OVERPAYMENT';

    public const TENDER_CASH = 'CASH';

    public const TENDER_BANK_TRANSFER = 'BANK_TRANSFER';

    public const TENDER_GATEWAY = 'GATEWAY';

    public const TENDER_CHECK = 'CHECK';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_CLEARED = 'CLEARED';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';

    public const STATUS_PENDING_CLEARANCE = 'PENDING_CLEARANCE';

    public const STATUS_REJECTED = 'REJECTED';

    public function __construct(
        protected DecimalCalculatorService $decimalCalculator
    ) {}

    /**
     * Calculate invoice settlement without mutating financial state.
     *
     * Cash-like confirmed tenders are applied before approved withholding.
     * This deterministic order protects a withholding certificate from being
     * consumed when confirmed cash already covers the remaining invoice.
     * Pending checks and pending tax evidence never reduce the invoice balance.
     * Actual partial funds are valid here; regular-customer checkout selection
     * is separately restricted by validateRegularCustomerCheckoutAmount().
     *
     * @param  array<int, array<string, mixed>>  $priorSettlements  Posted/applied prior settlements.
     * @param  array<int, array<string, mixed>>  $currentTenders  Current cash-like tenders.
     * @param  array<int, array<string, mixed>>  $withholdingApplications  Current certificate applications.
     */
    public function calculate(
        string $invoiceTotal,
        array $priorSettlements = [],
        array $currentTenders = [],
        array $withholdingApplications = []
    ): array {
        $invoiceTotal = $this->money($invoiceTotal, 'invoice_total');
        if (bccomp($invoiceTotal, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('invoice_total must be greater than zero.');
        }

        $prior = $this->summarizePriorSettlements($priorSettlements);
        $current = $this->summarizeCurrentTenders($currentTenders);
        $withholding = $this->summarizeWithholdingApplications($withholdingApplications);

        $currentConfirmedWithholding = $withholding['approved_amount'];
        $currentConfirmedTotal = bcadd(
            $current['confirmed_cash_amount'],
            $currentConfirmedWithholding,
            2
        );
        $priorConfirmedTotal = bcadd(
            $prior['cash_amount'],
            $prior['withholding_amount'],
            2
        );

        $priorAppliedToInvoice = $this->minMoney($priorConfirmedTotal, $invoiceTotal);
        $balanceBeforeCurrent = $this->subtractFloor($invoiceTotal, $priorAppliedToInvoice);

        // Cash is applied first; withholding remains available if cash already
        // covers the current balance or creates an overpayment.
        $cashApplied = $this->minMoney($current['confirmed_cash_amount'], $balanceBeforeCurrent);
        $balanceAfterCash = $this->subtractFloor($balanceBeforeCurrent, $cashApplied);
        $withholdingApplied = $this->minMoney($currentConfirmedWithholding, $balanceAfterCash);
        $currentAppliedToInvoice = bcadd($cashApplied, $withholdingApplied, 2);

        $cumulativeConfirmed = bcadd($priorConfirmedTotal, $currentConfirmedTotal, 2);
        $cumulativeApplied = bcadd($priorAppliedToInvoice, $currentAppliedToInvoice, 2);
        $balanceAfter = $this->subtractFloor($invoiceTotal, $cumulativeApplied);
        $overpayment = $this->positiveDifference($cumulativeConfirmed, $invoiceTotal);

        $unappliedCash = $this->positiveDifference($current['confirmed_cash_amount'], $cashApplied);
        $unappliedWithholding = $this->positiveDifference($currentConfirmedWithholding, $withholdingApplied);
        $unappliedConfirmed = bcadd($unappliedCash, $unappliedWithholding, 2);

        $status = self::STATUS_UNPAID;
        if (bccomp($cumulativeApplied, '0.00', 2) > 0 && bccomp($balanceAfter, '0.00', 2) > 0) {
            $status = self::STATUS_PARTIAL;
        } elseif (bccomp($balanceAfter, '0.00', 2) === 0) {
            $status = bccomp($overpayment, '0.00', 2) > 0
                ? self::STATUS_PAID_WITH_OVERPAYMENT
                : self::STATUS_PAID;
        }

        return [
            'invoice_total' => $invoiceTotal,
            'prior' => [
                'cash_amount' => $prior['cash_amount'],
                'withholding_amount' => $prior['withholding_amount'],
                'applied_amount' => $priorAppliedToInvoice,
                'pending_amount' => $prior['pending_amount'],
            ],
            'current' => [
                'cash_confirmed_amount' => $current['confirmed_cash_amount'],
                'withholding_approved_amount' => $currentConfirmedWithholding,
                'confirmed_amount' => $currentConfirmedTotal,
                'cash_applied_amount' => $cashApplied,
                'withholding_applied_amount' => $withholdingApplied,
                'applied_amount' => $currentAppliedToInvoice,
                'pending_cash_amount' => $current['pending_cash_amount'],
                'pending_check_amount' => $current['pending_check_amount'],
                'pending_amount' => bcadd(
                    bcadd($current['pending_cash_amount'], $current['pending_check_amount'], 2),
                    $withholding['pending_amount'],
                    2
                ),
                'pending_withholding_amount' => $withholding['pending_amount'],
                'rejected_amount' => bcadd($current['rejected_amount'], $withholding['rejected_amount'], 2),
                'unapplied_cash_amount' => $unappliedCash,
                'unapplied_withholding_amount' => $unappliedWithholding,
                'unapplied_confirmed_amount' => $unappliedConfirmed,
            ],
            'withholding' => [
                'approved_amount' => $withholding['approved_amount'],
                'pending_amount' => $withholding['pending_amount'],
                'rejected_amount' => $withholding['rejected_amount'],
                'capacity_remaining_after_requested' => $withholding['capacity_remaining_after_requested'],
                'applications' => $withholding['applications'],
            ],
            'confirmed_amount' => $cumulativeConfirmed,
            'applied_amount' => $cumulativeApplied,
            'balance_before_current' => $balanceBeforeCurrent,
            'balance_after' => $balanceAfter,
            'overpayment_amount' => $overpayment,
            'pending_clearance' => bccomp($current['pending_check_amount'], '0.00', 2) > 0,
            'status' => $status,
            'is_paid' => in_array($status, [self::STATUS_PAID, self::STATUS_PAID_WITH_OVERPAYMENT], true),
            'is_partial' => $status === self::STATUS_PARTIAL,
            'withholding_is_separate_from_discount' => true,
        ];
    }

    /**
     * W23: regular-customer checkout does not accept customer-entered partials.
     * This does not restrict actual partial funds arriving through review.
     */
    public function validateRegularCustomerCheckoutAmount(
        string $remainingBalance,
        string $requestedAmount
    ): array {
        $remainingBalance = $this->money($remainingBalance, 'remaining_balance');
        $requestedAmount = $this->money($requestedAmount, 'requested_amount');

        if (bccomp($remainingBalance, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('remaining_balance must be greater than zero.');
        }

        if (bccomp($requestedAmount, $remainingBalance, 2) !== 0) {
            throw new InvalidArgumentException(
                'Regular-customer checkout must select the full remaining balance; actual partial funds are reconciled after verification.'
            );
        }

        return [
            'remaining_balance' => $remainingBalance,
            'requested_amount' => $requestedAmount,
            'is_full_balance' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $settlements
     */
    protected function summarizePriorSettlements(array $settlements): array
    {
        $cash = '0.00';
        $withholding = '0.00';
        $pending = '0.00';

        foreach ($settlements as $settlement) {
            $amount = $this->money($settlement['amount'] ?? null, 'prior settlement amount');
            $type = strtoupper(trim((string) ($settlement['type'] ?? self::TENDER_CASH)));
            $status = strtoupper(trim((string) ($settlement['status'] ?? 'POSTED')));

            if ($type === 'WITHHOLDING') {
                $target = 'withholding';
            } elseif (in_array($type, [self::TENDER_CASH, self::TENDER_BANK_TRANSFER, self::TENDER_GATEWAY, self::TENDER_CHECK], true)) {
                $target = 'cash';
            } else {
                throw new InvalidArgumentException("Unsupported prior settlement type [{$type}].");
            }

            if (in_array($status, ['POSTED', 'APPLIED', self::STATUS_CONFIRMED, self::STATUS_CLEARED], true)) {
                if ($target === 'withholding') {
                    $withholding = bcadd($withholding, $amount, 2);
                } else {
                    $cash = bcadd($cash, $amount, 2);
                }

                continue;
            }

            if (in_array($status, [self::STATUS_PENDING, self::STATUS_PENDING_REVIEW, self::STATUS_PENDING_CLEARANCE], true)) {
                $pending = bcadd($pending, $amount, 2);

                continue;
            }

            if ($status !== self::STATUS_REJECTED) {
                throw new InvalidArgumentException("Unsupported prior settlement status [{$status}].");
            }
        }

        return [
            'cash_amount' => $cash,
            'withholding_amount' => $withholding,
            'pending_amount' => $pending,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tenders
     */
    protected function summarizeCurrentTenders(array $tenders): array
    {
        $confirmedCash = '0.00';
        $pendingCash = '0.00';
        $pendingCheck = '0.00';
        $rejected = '0.00';

        foreach ($tenders as $tender) {
            $amount = $this->money($tender['amount'] ?? null, 'tender amount');
            $type = strtoupper(trim((string) ($tender['type'] ?? '')));
            $status = strtoupper(trim((string) ($tender['status'] ?? '')));

            if (! in_array($type, [self::TENDER_CASH, self::TENDER_BANK_TRANSFER, self::TENDER_GATEWAY, self::TENDER_CHECK], true)) {
                throw new InvalidArgumentException("Unsupported tender type [{$type}].");
            }

            if ($status === '' || ! in_array($status, [self::STATUS_CONFIRMED, self::STATUS_CLEARED, self::STATUS_PENDING, self::STATUS_PENDING_REVIEW, self::STATUS_PENDING_CLEARANCE, self::STATUS_REJECTED], true)) {
                throw new InvalidArgumentException("Unsupported tender status [{$status}].");
            }

            if ($type === self::TENDER_CHECK && $status === self::STATUS_CONFIRMED) {
                throw new InvalidArgumentException('A check must be CLEARED before it can be applied to an invoice.');
            }

            if ($status === self::STATUS_REJECTED) {
                $rejected = bcadd($rejected, $amount, 2);
            } elseif (in_array($status, [self::STATUS_CONFIRMED, self::STATUS_CLEARED], true)) {
                $confirmedCash = bcadd($confirmedCash, $amount, 2);
            } else {
                if ($type === self::TENDER_CHECK) {
                    $pendingCheck = bcadd($pendingCheck, $amount, 2);
                } else {
                    $pendingCash = bcadd($pendingCash, $amount, 2);
                }
            }
        }

        return [
            'confirmed_cash_amount' => $confirmedCash,
            'pending_cash_amount' => $pendingCash,
            'pending_check_amount' => $pendingCheck,
            'rejected_amount' => $rejected,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $applications
     */
    protected function summarizeWithholdingApplications(array $applications): array
    {
        $approved = '0.00';
        $pending = '0.00';
        $rejected = '0.00';
        $capacityRemaining = '0.00';
        $seenCertificates = [];
        $normalizedApplications = [];

        foreach ($applications as $application) {
            $certificateId = trim((string) ($application['certificate_id'] ?? ''));
            if ($certificateId === '') {
                throw new InvalidArgumentException('Every withholding application requires a certificate_id.');
            }
            if (isset($seenCertificates[$certificateId])) {
                throw new InvalidArgumentException("Withholding certificate [{$certificateId}] was supplied more than once.");
            }
            $seenCertificates[$certificateId] = true;

            $amount = $this->money(
                $application['amount'] ?? $application['requested_amount'] ?? null,
                "withholding amount for certificate [{$certificateId}]"
            );
            $status = strtoupper(trim((string) ($application['status'] ?? '')));
            if ($status === '') {
                throw new InvalidArgumentException("Withholding certificate [{$certificateId}] requires a status.");
            }

            $remainingBefore = null;
            $remainingAfter = null;
            if ($status === 'APPROVED') {
                $remainingBefore = $this->money(
                    $application['available_amount'] ?? $application['remaining_amount'] ?? null,
                    "remaining capacity for certificate [{$certificateId}]"
                );
                if (bccomp($amount, $remainingBefore, 2) > 0) {
                    throw new InvalidArgumentException("Withholding application for certificate [{$certificateId}] exceeds its remaining capacity.");
                }
                $remainingAfter = bcsub($remainingBefore, $amount, 2);
                $approved = bcadd($approved, $amount, 2);
                $capacityRemaining = bcadd($capacityRemaining, $remainingAfter, 2);
            } elseif (in_array($status, ['PENDING', self::STATUS_PENDING_REVIEW, 'NEEDS_CORRECTION'], true)) {
                $pending = bcadd($pending, $amount, 2);
            } elseif ($status === self::STATUS_REJECTED) {
                $rejected = bcadd($rejected, $amount, 2);
            } else {
                throw new InvalidArgumentException("Unsupported withholding status [{$status}].");
            }

            $normalizedApplications[] = [
                'certificate_id' => $certificateId,
                'status' => $status,
                'requested_amount' => $amount,
                'capacity_before' => $remainingBefore,
                'capacity_after' => $remainingAfter,
            ];
        }

        return [
            'approved_amount' => $approved,
            'pending_amount' => $pending,
            'rejected_amount' => $rejected,
            'capacity_remaining_after_requested' => $capacityRemaining,
            'applications' => $normalizedApplications,
        ];
    }

    protected function money(mixed $value, string $field): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("{$field} must be a decimal amount.");
        }

        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("{$field} must be a non-negative amount with at most two decimals.");
        }

        return $this->decimalCalculator->truncate($value, 2);
    }

    protected function minMoney(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    protected function subtractFloor(string $left, string $right): string
    {
        $result = bcsub($left, $right, 2);

        return bccomp($result, '0.00', 2) < 0 ? '0.00' : $result;
    }

    protected function positiveDifference(string $left, string $right): string
    {
        return bccomp($left, $right, 2) > 0 ? bcsub($left, $right, 2) : '0.00';
    }
}
