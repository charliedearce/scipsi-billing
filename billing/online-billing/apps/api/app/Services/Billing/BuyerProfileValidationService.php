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
     * Evaluate required P0-06 Philippine BIR fiscal buyer fields.
     */
    public function checkFiscalReadiness(BuyerProfileVersion $version): array
    {
        $missing = [];

        // 1. Registered Name (official corporate or individual business name)
        if (empty(trim((string) $version->registered_name))) {
            $missing[] = 'registered_name';
        }

        // 2. Tax Identification Number (TIN)
        $cleanTin = preg_replace('/[^0-9]/', '', (string) $version->tin);
        if (empty($cleanTin) || strlen($cleanTin) < 9) {
            $missing[] = 'tin';
        }

        // 3. Branch Code (default '00000' or 3-5 digit code)
        if (empty(trim((string) $version->branch_code))) {
            $missing[] = 'branch_code';
        }

        // 4. Complete Billing Address
        $address = $version->billing_address;
        if (! is_array($address) || empty($address['street']) || empty($address['city'])) {
            $missing[] = 'billing_address';
        }

        return $missing;
    }
}
