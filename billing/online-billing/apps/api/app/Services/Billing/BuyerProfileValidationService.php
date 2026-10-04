<?php

namespace App\Services\Billing;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class BuyerProfileValidationService
{
    /**
     * Validate customer account and resolve active buyer profile version.
     * Returns:
     * [
     *   'is_valid_for_draft' => bool,
     *   'is_fiscal_ready' => bool,
     *   'buyer_profile_version' => ?BuyerProfileVersion,
     *   'missing_fiscal_fields' => array,
     * ]
     */
    public function validateForBilling(Customer $customer): array
    {
        if ($customer->status !== 'active') {
            throw ValidationException::withMessages([
                'customer_id' => ["Customer account {$customer->account_number} is not active (current status: {$customer->status})."],
            ]);
        }

        $buyerProfile = $customer->buyerProfile;
        if (! $buyerProfile || ! $buyerProfile->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => ["Customer {$customer->account_number} does not have an active buyer profile."],
            ]);
        }

        $activeVersion = $buyerProfile->activeVersion;
        if (! $activeVersion) {
            throw ValidationException::withMessages([
                'customer_id' => ["Customer {$customer->account_number} has no active buyer profile version."],
            ]);
        }

        $missingFields = $this->checkFiscalReadiness($activeVersion);
        $isFiscalReady = count($missingFields) === 0;

        return [
            'is_valid_for_draft' => true,
            'is_fiscal_ready' => $isFiscalReady,
            'buyer_profile_version' => $activeVersion,
            'missing_fiscal_fields' => $missingFields,
        ];
    }

    /**
     * Evaluate buyer fields needed before invoice posting.
     *
     * Registered buyer name remains required. TIN, branch code and billing address are
     * optional profile inputs: when the customer/teller leaves them blank, posting may
     * proceed and the issued snapshot stores null/defaults. When TIN is supplied, it must
     * be a usable numeric TIN (at least 9 digits). Stronger P0-06 accountant rules can
     * tighten this later without rewriting issued snapshots.
     */
    public function checkFiscalReadiness(BuyerProfileVersion $version): array
    {
        $missing = [];

        if (empty(trim((string) $version->registered_name))) {
            $missing[] = 'registered_name';
        }

        $cleanTin = preg_replace('/[^0-9]/', '', (string) ($version->tin ?? ''));
        if ($cleanTin !== '' && strlen($cleanTin) < 9) {
            $missing[] = 'tin';
        }

        return $missing;
    }
}
