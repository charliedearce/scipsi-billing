<?php

namespace App\Services\Billing;

use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guards a reviewer-confirmed bank-transfer identity across manual and VIP payment channels.
 * A file hash is intentionally not used as a transfer identity.
 */
class BankTransferSettlementReferenceService
{
    public function claim(int $organizationId, string $currency, string $reference, string $sourceType, string $sourceKey): string
    {
        $normalized = $this->normalize($reference);
        DB::table('bank_transfer_settlement_references')->insertOrIgnore([
            'organization_id' => $organizationId,
            'currency' => strtoupper($currency),
            'normalized_reference' => $normalized,
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
            'created_at' => Carbon::now('Asia/Manila'),
        ]);
        $claim = DB::table('bank_transfer_settlement_references')->where([
            'organization_id' => $organizationId,
            'currency' => strtoupper($currency),
            'normalized_reference' => $normalized,
        ])->lockForUpdate()->first();
        if (! $claim || $claim->source_type !== $sourceType || $claim->source_key !== $sourceKey) {
            throw ValidationException::withMessages(['confirmed_reference' => ['This confirmed bank-transfer reference is already linked to another settlement.']]);
        }

        return $normalized;
    }

    public function linkReceipt(int $organizationId, string $currency, string $normalizedReference, Receipt $receipt): void
    {
        DB::table('bank_transfer_settlement_references')->where([
            'organization_id' => $organizationId,
            'currency' => strtoupper($currency),
            'normalized_reference' => $normalizedReference,
        ])->update(['receipt_id' => $receipt->id]);
    }

    public function normalize(string $reference): string
    {
        return strtoupper((string) preg_replace('/\s+/', ' ', trim($reference)));
    }
}
