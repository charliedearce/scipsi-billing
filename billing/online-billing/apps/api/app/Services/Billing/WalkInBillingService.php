<?php

namespace App\Services\Billing;

use App\Events\DataRefreshEvent;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Models\WalkInCustomer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teller-side service for creating walk-in customer records and their invoice drafts.
 *
 * Design decisions (W25 / Decision §6 of CUSTOMER_SERVICE_LIFECYCLE.md):
 * - A walk-in customer is a proper business record — NOT a fake User or generic Customer.
 * - The issued buyer snapshot is immutable; linking to a portal account later does not alter it.
 * - Walk-in buyers become a shell Customer and BuyerProfile so existing invoice machinery works.
 */
class WalkInBillingService
{
    /**
     * Create a walk-in customer record and its corresponding shell Customer for invoicing.
     *
     * @param  array{buyer_name: string, buyer_tin?: string|null, buyer_branch_code?: string|null, buyer_address?: string|null, contact_mobile?: string|null, contact_email?: string|null}  $buyerData
     */
    public function createWalkInCustomer(
        User $teller,
        int $organizationId,
        int $locationId,
        array $buyerData
    ): WalkInCustomer {
        if (empty(trim($buyerData['buyer_name'] ?? ''))) {
            throw ValidationException::withMessages([
                'buyer_name' => 'Buyer name is required for walk-in customers.',
            ]);
        }

        $org = Organization::findOrFail($organizationId);
        Location::where('id', $locationId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return DB::transaction(function () use ($teller, $org, $locationId, $buyerData): WalkInCustomer {
            // 1. Create a shell Customer record for this walk-in so existing invoice FK constraints work.
            $customer = Customer::create([
                'organization_id' => $org->id,
                'account_number' => 'WI-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
                'name' => $buyerData['buyer_name'],
                'status' => 'active',
                'customer_type' => 'WALK_IN',
                'lock_version' => 1,
            ]);

            // 2. Create the corresponding BuyerProfile root record.
            $buyerProfile = CustomerBuyerProfile::create([
                'customer_id' => $customer->id,
                'current_version' => 1,
                'is_active' => true,
            ]);

            // Create the initial buyer profile version using the actual schema columns.
            $buyerProfile->versions()->create([
                'buyer_profile_id' => $buyerProfile->id,
                'version' => 1,
                'registered_name' => $buyerData['buyer_name'],
                'tin' => $buyerData['buyer_tin'] ?? null,
                'branch_code' => $buyerData['buyer_branch_code'] ?? '00000',
                'billing_address' => $buyerData['buyer_address']
                    ? ['raw' => $buyerData['buyer_address']]
                    : null,
                'effective_from' => Carbon::now(),
                'status' => 'active',
                'created_by_user_id' => $teller->id,
            ]);

            // 3. Create the WalkInCustomer audit record.
            //    shell_customer_id = internal shell for invoice FKs (always set).
            //    customer_id       = portal-linked customer (NULL until claim approved).
            $walkIn = WalkInCustomer::create([
                'organization_id' => $org->id,
                'location_id' => $locationId,
                'shell_customer_id' => $customer->id,  // internal shell only
                'buyer_name' => $buyerData['buyer_name'],
                'buyer_tin' => $buyerData['buyer_tin'] ?? null,
                'buyer_branch_code' => $buyerData['buyer_branch_code'] ?? null,
                'buyer_address' => $buyerData['buyer_address'] ?? null,
                'contact_mobile' => $buyerData['contact_mobile'] ?? null,
                'contact_email' => $buyerData['contact_email'] ?? null,
                'customer_id' => null, // portal link — NOT set until claim approved
                'created_by_user_id' => $teller->id,
                'lock_version' => 1,
            ]);

            broadcast(new DataRefreshEvent(
                $org->id,
                'walk_in',
                'walk_in_customer',
                $walkIn->id,
                'created'
            ));

            return $walkIn->load(['shellCustomer', 'location', 'creator']);
        });
    }

    /**
     * Create a blank invoice draft from a walk-in customer record.
     * The invoice uses the walk-in's buyer fields as the buyer profile source.
     *
     * @return Invoice A DRAFT invoice ready for teller line-item encoding.
     */
    public function createWalkInInvoiceDraft(
        User $teller,
        WalkInCustomer $walkIn,
        string $businessDate,
        ?string $notes = null
    ): Invoice {
        return DB::transaction(function () use ($teller, $walkIn, $businessDate, $notes): Invoice {
            // Use the shell customer (created at walk-in time) for the invoice FK.
            // The shell customer_id on the invoice is NOT changed by a portal claim.
            $shellCustomer = $walkIn->shellCustomer;
            $buyerProfile = $shellCustomer?->buyerProfile;
            $profileVersion = $buyerProfile?->currentVersion();

            $invoice = Invoice::create([
                'organization_id' => $walkIn->organization_id,
                'location_id' => $walkIn->location_id,
                'customer_id' => $shellCustomer->id,
                'buyer_profile_version_id' => $profileVersion?->id,
                'walk_in_customer_id' => $walkIn->id,
                'status' => 'DRAFT',
                'business_date' => $businessDate,
                'currency' => 'PHP',
                'base_gross_amount' => '0.00',
                'fuel_surcharge_amount' => '0.00',
                'gross_amount' => '0.00',
                'ppa_amount' => '0.00',
                'discount_amount' => '0.00',
                'net_amount' => '0.00',
                'tax_amount' => '0.00',
                'total_charge_amount' => '0.00',
                'notes' => $notes ?? "Walk-in invoice for {$walkIn->buyer_name}",
                'created_by_user_id' => $teller->id,
                'lock_version' => 1,
            ]);

            broadcast(new DataRefreshEvent(
                $walkIn->organization_id,
                'walk_in',
                'invoice',
                $invoice->id,
                'draft_created'
            ));

            return $invoice;
        });
    }

    /**
     * Link a walk-in record to a portal Customer account after a successful claim.
     * This is called by BillClaimService upon claim approval; it does NOT alter
     * the buyer snapshots of any already-posted invoices.
     */
    public function linkWalkInToPortalCustomer(
        User $actor,
        WalkInCustomer $walkIn,
        Customer $portalCustomer,
        ?string $reason = null
    ): void {
        if ($walkIn->isLinked() && $walkIn->customer_id !== $portalCustomer->id) {
            throw ValidationException::withMessages([
                'walk_in_customer_id' => 'This walk-in record is already linked to a different portal customer.',
            ]);
        }

        if ($walkIn->isLinked() && $walkIn->customer_id === $portalCustomer->id) {
            return; // idempotent
        }

        DB::transaction(function () use ($actor, $walkIn, $portalCustomer): void {
            $now = Carbon::now();

            $walkIn->update([
                'linked_at' => $now,
                'linked_by_user_id' => $actor->id,
            ]);

            // Reassign walk-in invoices to the portal Customer so they appear in their account.
            // Buyer snapshots on POSTED invoices are immutable and are NOT changed.
            Invoice::where('walk_in_customer_id', $walkIn->id)
                ->where('status', 'DRAFT') // only draft invoices are re-assignable
                ->update(['customer_id' => $portalCustomer->id]);

            broadcast(new DataRefreshEvent(
                $walkIn->organization_id,
                'walk_in',
                'walk_in_customer',
                $walkIn->id,
                'linked'
            ));
        });
    }
}
