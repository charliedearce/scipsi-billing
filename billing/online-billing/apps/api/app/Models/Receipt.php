<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Receipt extends Model
{
    use HasFactory;

    public const KIND_OFFICIAL = 'OFFICIAL';

    public const KIND_ACKNOWLEDGEMENT = 'ACKNOWLEDGEMENT';

    /** @var array<int, string> */
    public const KINDS = [self::KIND_OFFICIAL, self::KIND_ACKNOWLEDGEMENT];

    protected $fillable = [
        'organization_id', 'location_id', 'customer_id', 'series_id', 'posting_source_id',
        'receipt_number', 'status', 'receipt_kind', 'counts_as_official_receipt',
        'business_date', 'accounting_period_id', 'backdate_authorization_id', 'currency', 'payer_snapshot',
        'cash_received_amount', 'withholding_received_amount', 'applied_amount',
        'unapplied_amount', 'lock_version', 'posted_by_user_id', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d', 'payer_snapshot' => 'array', 'posted_at' => 'datetime',
            'counts_as_official_receipt' => 'boolean',
            'cash_received_amount' => 'string', 'withholding_received_amount' => 'string',
            'applied_amount' => 'string', 'unapplied_amount' => 'string', 'lock_version' => 'integer',
        ];
    }

    public function isOfficialReceipt(): bool
    {
        return $this->receipt_kind === self::KIND_OFFICIAL && $this->counts_as_official_receipt;
    }

    public function isAcknowledgement(): bool
    {
        return $this->receipt_kind === self::KIND_ACKNOWLEDGEMENT;
    }

    /** Document-series / Studio kind used when allocating and rendering this receipt. */
    public function documentClass(): string
    {
        return $this->isAcknowledgement() ? 'ACKNOWLEDGEMENT_RECEIPT' : 'COLLECTION_RECEIPT';
    }

    /**
     * Fiscal / BIR-facing official-receipt issuances only.
     * Acknowledgement receipts settle internally but never appear here.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfficialFiscal($query)
    {
        return $query->where('receipt_kind', self::KIND_OFFICIAL)
            ->where('counts_as_official_receipt', true);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'series_id');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function backdateAuthorization(): BelongsTo
    {
        return $this->belongsTo(BackdateAuthorization::class, 'backdate_authorization_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function postingSource(): BelongsTo
    {
        return $this->belongsTo(ReceiptPostingSource::class, 'posting_source_id');
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(ReceiptTender::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function withholdingApplications(): HasMany
    {
        return $this->hasMany(WithholdingApplication::class);
    }

    public function canonicalArtifact(): HasOne
    {
        return $this->hasOne(DocumentArtifact::class, 'document_id')->where('document_type', 'RECEIPT')->where('artifact_type', 'CANONICAL_PDF')->latestOfMany();
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(DocumentCorrectionRequest::class);
    }

    public function manualPaymentSubmission(): HasOne
    {
        return $this->hasOne(ManualPaymentSubmission::class);
    }
}
