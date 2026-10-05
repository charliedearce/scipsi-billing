<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\ReceiptAllocation;

class InvoiceSettlementService
{
    public function __construct(private CustomerPaymentCreditService $credits) {}

    public function outstanding(Invoice $invoice): string
    {
        $applied = $this->appliedForInvoices([$invoice->id])[$invoice->id] ?? '0.00';
        $remaining = bcsub((string) $invoice->total_charge_amount, $applied, 2);

        return bccomp($remaining, '0.00', 2) > 0 ? $remaining : '0.00';
    }

    /** @param array<int, int> $invoiceIds @return array<int, string> */
    public function appliedForInvoices(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }
        $totals = [];
        $receipts = ReceiptAllocation::whereIn('invoice_id', $invoiceIds)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
            ->get();
        foreach ($receipts as $allocation) {
            $invoiceId = (int) $allocation->invoice_id;
            $totals[$invoiceId] = bcadd($totals[$invoiceId] ?? '0.00', (string) $allocation->applied_amount, 2);
        }
        foreach ($this->credits->applicationsForInvoices($invoiceIds) as $invoiceId => $amount) {
            $totals[$invoiceId] = bcadd($totals[$invoiceId] ?? '0.00', $amount, 2);
        }

        return $totals;
    }
}
