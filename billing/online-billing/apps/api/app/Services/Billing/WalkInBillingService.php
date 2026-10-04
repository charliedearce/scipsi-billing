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
use App\Services\Audit\DocumentRevisionService;
use App\Support\SafeBroadcast;
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
    public function __construct(
        protected DocumentRevisionService $revisionService
    ) {}

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

        $incomingTin = isset($buyerData['buyer_tin']) ? trim((string) $buyerData['buyer_tin']) : '';
        if ($incomingTin !== '') {
            $tinDigits = preg_replace('/\D/', '', $incomingTin) ?? '';
            if (strlen($tinDigits) < 9) {
                throw ValidationException::withMessages([
                    'buyer_tin' => 'TIN is optional. Leave it blank or enter at least 9 digits.',
                ]);
            }
        }

        $org = Organization::findOrFail($organizationId);
        Location::where('id', $locationId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return DB::transaction(function () use ($teller, $org, $locationId, $buyerData): WalkInCustomer {
            $buyerTin = isset($buyerData['buyer_tin']) ? trim((string) $buyerData['buyer_tin']) : '';
            $buyerTin = $buyerTin !== '' ? $buyerTin : null;
            $buyerAddress = trim((string) ($buyerData['buyer_address'] ?? ''));
            $buyerAddress = $buyerAddress !== '' ? $buyerAddress : null;
            $branchCodeRaw = trim((string) ($buyerData['buyer_branch_code'] ?? ''));
            $branchCodeStored = $branchCodeRaw !== '' ? $branchCodeRaw : null;
            $branchCodeProfile = $branchCodeStored ?? '00000';
            $contactMobile = trim((string) ($buyerData['contact_mobile'] ?? ''));
            $contactMobile = $contactMobile !== '' ? $contactMobile : null;
            $contactEmail = trim((string) ($buyerData['contact_email'] ?? ''));
            $contactEmail = $contactEmail !== '' ? $contactEmail : null;

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
                'tin' => $buyerTin,
                'branch_code' => $branchCodeProfile,
                'billing_address' => $buyerAddress !== null
                    ? ['raw' => $buyerAddress]
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
                'buyer_tin' => $buyerTin,
                'buyer_branch_code' => $branchCodeStored,
                'buyer_address' => $buyerAddress,
                'contact_mobile' => $contactMobile,
                'contact_email' => $contactEmail,
                'customer_id' => null, // portal link — NOT set until claim approved
                'created_by_user_id' => $teller->id,
                'lock_version' => 1,
            ]);

            SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
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
    /**
     * @param  array<string, mixed>  $shipment
     */
    public function createWalkInInvoiceDraft(
        User $teller,
        WalkInCustomer $walkIn,
        string $businessDate,
        ?string $notes = null,
        array $shipment = []
    ): Invoice {
        return DB::transaction(function () use ($teller, $walkIn, $businessDate, $notes, $shipment): Invoice {
            // Use the shell customer (created at walk-in time) for the invoice FK.
            // The shell customer_id on the invoice is NOT changed by a portal claim.
            $shellCustomer = $walkIn->shellCustomer;
            $buyerProfile = $shellCustomer?->buyerProfile;
            $profileVersion = $buyerProfile?->currentVersion();

            $resolvedShipment = [];
            if (! empty($shipment['vessel_id'])) {
                $resolvedShipment = InvoiceShipment::resolve($walkIn->organization_id, $shipment);
            }

            $invoice = Invoice::create(array_merge([
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
                'notes' => $resolvedShipment['notes'] ?? $notes ?? "Walk-in invoice for {$walkIn->buyer_name}",
                'created_by_user_id' => $teller->id,
                'lock_version' => 1,
            ], $resolvedShipment));

            $this->revisionService->createRevision(
                organizationId: $invoice->organization_id,
                locationId: $invoice->location_id,
                documentType: 'INVOICE',
                documentId: $invoice->id,
                actor: $teller,
                newSnapshot: $invoice->load(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
                reason: $notes ?? "Initial walk-in invoice draft for {$walkIn->buyer_name}",
                expectedVersion: 1
            );

            SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
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
                'customer_id' => $portalCustomer->id,
                'linked_at' => $now,
                'linked_by_user_id' => $actor->id,
            ]);

            // Reassign walk-in invoices to the portal Customer so they appear in their account.
            // Buyer snapshots on POSTED invoices are immutable and are NOT changed.
            Invoice::where('walk_in_customer_id', $walkIn->id)
                ->where('status', 'DRAFT') // only draft invoices are re-assignable
                ->update(['customer_id' => $portalCustomer->id]);

            SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
                $walkIn->organization_id,
                'walk_in',
                'walk_in_customer',
                $walkIn->id,
                'linked'
            ));
        });
    }

    /**
     * Update walk-in buyer fields before any invoice is posted.
     * Used to clear an incomplete TIN or correct counter capture errors.
     * Never rewrites buyer snapshots on already-posted invoices.
     *
     * @param  array{buyer_name?: string|null, buyer_tin?: string|null, buyer_branch_code?: string|null, buyer_address?: string|null, contact_mobile?: string|null, contact_email?: string|null}  $buyerData
     */
    public function updateWalkInBuyerFields(
        User $teller,
        WalkInCustomer $walkIn,
        array $buyerData
    ): WalkInCustomer {
        $hasPosted = Invoice::where('walk_in_customer_id', $walkIn->id)
            ->where('status', 'POSTED')
            ->exists();

        if ($hasPosted) {
            throw ValidationException::withMessages([
                'walk_in_customer_id' => 'Buyer fields cannot change after an invoice has been posted for this walk-in.',
            ]);
        }

        return DB::transaction(function () use ($teller, $walkIn, $buyerData): WalkInCustomer {
            $updates = [];

            if (array_key_exists('buyer_name', $buyerData)) {
                $name = trim((string) $buyerData['buyer_name']);
                if ($name === '') {
                    throw ValidationException::withMessages([
                        'buyer_name' => 'Buyer name is required for walk-in customers.',
                    ]);
                }
                $updates['buyer_name'] = $name;
            }

            if (array_key_exists('buyer_tin', $buyerData)) {
                $tin = trim((string) ($buyerData['buyer_tin'] ?? ''));
                $tinDigits = preg_replace('/\D/', '', $tin) ?? '';
                if ($tin !== '' && strlen($tinDigits) < 9) {
                    throw ValidationException::withMessages([
                        'buyer_tin' => 'TIN is optional. Leave it blank or enter at least 9 digits.',
                    ]);
                }
                $updates['buyer_tin'] = $tin === '' ? null : $tin;
            }

            foreach (['buyer_branch_code', 'buyer_address', 'contact_mobile', 'contact_email'] as $field) {
                if (! array_key_exists($field, $buyerData)) {
                    continue;
                }
                $value = trim((string) ($buyerData[$field] ?? ''));
                $updates[$field] = $value === '' ? null : $value;
            }

            if ($updates === []) {
                return $walkIn->fresh(['shellCustomer', 'location', 'creator']);
            }

            $walkIn->update($updates);

            $shell = $walkIn->shellCustomer;
            if ($shell && isset($updates['buyer_name'])) {
                $shell->update(['name' => $updates['buyer_name']]);
            }

            $version = $shell?->buyerProfile?->currentVersion();
            if ($version) {
                $versionUpdates = [];
                if (isset($updates['buyer_name'])) {
                    $versionUpdates['registered_name'] = $updates['buyer_name'];
                }
                if (array_key_exists('buyer_tin', $updates)) {
                    $versionUpdates['tin'] = $updates['buyer_tin'];
                }
                if (array_key_exists('buyer_branch_code', $updates)) {
                    $versionUpdates['branch_code'] = $updates['buyer_branch_code'] ?? '00000';
                }
                if (array_key_exists('buyer_address', $updates)) {
                    $versionUpdates['billing_address'] = $updates['buyer_address'] !== null
                        ? ['raw' => $updates['buyer_address']]
                        : null;
                }
                if ($versionUpdates !== []) {
                    $version->update($versionUpdates);
                }
            }

            SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
                $walkIn->organization_id,
                'walk_in',
                'walk_in_customer',
                $walkIn->id,
                'updated',
                userId: $teller->id,
            ));

            return $walkIn->fresh(['shellCustomer', 'location', 'creator']);
        });
    }
}
