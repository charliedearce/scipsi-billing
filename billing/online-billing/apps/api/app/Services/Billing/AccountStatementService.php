<?php

namespace App\Services\Billing;

use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountStatementService
{
    public function generate(User $actor, int $customerId, Carbon|string $asOfDate, ?int $locationId = null): AccountStatement
    {
        $asOf = $asOfDate instanceof Carbon ? $asOfDate->toDateString() : $asOfDate;

        return DB::transaction(function () use ($actor, $customerId, $asOf, $locationId): AccountStatement {
            $customer = Customer::where('organization_id', $actor->organization_id)->whereKey($customerId)->lockForUpdate()->firstOrFail();
            if (! $customer->isActive()) {
                throw ValidationException::withMessages(['customer_id' => ['An account statement requires an active customer.']]);
            }

            $invoices = Invoice::where('organization_id', $actor->organization_id)->where('customer_id', $customer->id)
                ->where('status', 'POSTED')->whereDate('business_date', '<=', $asOf)
                ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
                ->orderBy('id')->lockForUpdate()->get();
            $allocations = ReceiptAllocation::query()->whereIn('invoice_id', $invoices->pluck('id'))
                ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED')->whereDate('business_date', '<=', $asOf))
                ->get()->groupBy('invoice_id');

            $items = [];
            $invoiceTotal = '0.00';
            $paymentTotal = '0.00';
            $outstandingTotal = '0.00';
            foreach ($invoices as $invoice) {
                $paid = '0.00';
                foreach ($allocations->get($invoice->id, collect()) as $allocation) {
                    $paid = bcadd($paid, (string) $allocation->applied_amount, 2);
                }
                $amount = (string) $invoice->total_charge_amount;
                $outstanding = bcsub($amount, $paid, 2);
                if (bccomp($outstanding, '0.00', 2) <= 0) {
                    continue;
                }
                $items[] = compact('invoice', 'amount', 'paid', 'outstanding');
                $invoiceTotal = bcadd($invoiceTotal, $amount, 2);
                $paymentTotal = bcadd($paymentTotal, $paid, 2);
                $outstandingTotal = bcadd($outstandingTotal, $outstanding, 2);
            }
            if ($items === []) {
                throw ValidationException::withMessages(['customer_id' => ['This customer has no outstanding posted invoices as of the requested date.']]);
            }

            $statement = AccountStatement::create([
                'organization_id' => $actor->organization_id, 'location_id' => $locationId, 'customer_id' => $customer->id,
                'statement_number' => 'SOA-'.strtoupper(Str::uuid()), 'as_of_date' => $asOf, 'currency' => 'PHP',
                'invoice_total' => $invoiceTotal, 'payment_total' => $paymentTotal, 'outstanding_total' => $outstandingTotal,
                'customer_snapshot' => ['account_number' => $customer->account_number, 'name' => $customer->name],
                'status' => 'GENERATED', 'generated_by_user_id' => $actor->id, 'generated_at' => now(),
            ]);
            foreach ($items as $item) {
                AccountStatementItem::create([
                    'statement_id' => $statement->id, 'invoice_id' => $item['invoice']->id, 'invoice_number' => $item['invoice']->invoice_number,
                    'business_date' => $item['invoice']->business_date, 'invoice_amount' => $item['amount'], 'payment_amount' => $item['paid'], 'outstanding_amount' => $item['outstanding'],
                    'snapshot' => ['invoice_lock_version' => $item['invoice']->lock_version, 'currency' => $item['invoice']->currency],
                ]);
            }
            AuditEvent::create(['organization_id' => $actor->organization_id, 'location_id' => $locationId, 'event_type' => 'ACCOUNT_STATEMENT_GENERATED', 'aggregate_type' => 'ACCOUNT_STATEMENT', 'aggregate_id' => $statement->id, 'aggregate_version' => 1, 'actor_type' => 'user', 'actor_id' => $actor->id, 'permission_snapshot' => 'statements:generate', 'occurred_at' => now(), 'business_date' => $asOf, 'reason' => 'As-of account statement snapshot generated', 'metadata' => ['statement_number' => $statement->statement_number, 'customer_id' => $customer->id, 'outstanding_total' => $outstandingTotal]]);

            return $statement->load('items');
        });
    }
}
