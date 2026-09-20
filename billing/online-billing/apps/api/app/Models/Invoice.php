<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'location_id',
        'customer_id',
        'buyer_profile_version_id',
        'walk_in_customer_id',
        'taxpayer_profile_version_id',
        'series_id',
        'invoice_number',
        'status',
        'business_date',
        'accounting_period_id',
        'backdate_authorization_id',
        'currency',
        'posted_at',
        'posted_by_user_id',
        'buyer_snapshot_name',
        'buyer_snapshot_trade_name',
        'buyer_snapshot_tin',
        'buyer_snapshot_branch_code',
        'buyer_snapshot_tax_classification',
        'buyer_snapshot_address',
        'buyer_snapshot_email',
        'buyer_snapshot_phone',
        'issuer_snapshot_name',
        'issuer_snapshot_trade_name',
        'issuer_snapshot_tin',
        'issuer_snapshot_branch_code',
        'issuer_snapshot_tax_classification',
        'issuer_snapshot_address',
        'issuer_snapshot_permit_no',
        'sale_type',
        'base_gross_amount',
        'fuel_surcharge_amount',
        'gross_amount',
        'ppa_amount',
        'discount_amount',
        'net_amount',
        'tax_amount',
        'total_charge_amount',
        'is_fiscal_ready',
        'fiscal_readiness_errors',
        'notes',
        'lock_version',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d',
        'posted_at' => 'datetime',
        'base_gross_amount' => 'string',
        'fuel_surcharge_amount' => 'string',
        'gross_amount' => 'string',
        'ppa_amount' => 'string',
        'discount_amount' => 'string',
        'net_amount' => 'string',
        'tax_amount' => 'string',
        'total_charge_amount' => 'string',
        'is_fiscal_ready' => 'boolean',
        'fiscal_readiness_errors' => 'array',
        'buyer_snapshot_address' => 'array',
        'issuer_snapshot_address' => 'array',
        'lock_version' => 'integer',
    ];

    protected $appends = [
        'buyer_snapshot',
        'issuer_snapshot',
    ];

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

    public function buyerProfileVersion(): BelongsTo
    {
        return $this->belongsTo(BuyerProfileVersion::class);
    }

    public function taxpayerProfileVersion(): BelongsTo
    {
        return $this->belongsTo(TaxpayerProfileVersion::class);
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_number', 'asc');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id')
            ->where('document_type', 'INVOICE')
            ->orderBy('revision_number', 'desc');
    }

    public function outbox(): HasMany
    {
        return $this->hasMany(InvoiceOutbox::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(DocumentSnapshot::class, 'document_id')
            ->where('document_type', 'INVOICE')
            ->orderBy('id', 'desc');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(DocumentArtifact::class, 'document_id')
            ->where('document_type', 'INVOICE')
            ->orderBy('id', 'desc');
    }

    public function canonicalArtifact(): HasOne
    {
        return $this->hasOne(DocumentArtifact::class, 'document_id')
            ->where('document_type', 'INVOICE')
            ->where('artifact_type', 'CANONICAL_PDF')
            ->latestOfMany();
    }

    public function walkInCustomer(): BelongsTo
    {
        return $this->belongsTo(WalkInCustomer::class, 'walk_in_customer_id');
    }

    public function billingRequest(): HasOne
    {
        return $this->hasOne(BillingRequest::class, 'invoice_id');
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(DocumentCorrectionRequest::class);
    }

    public function manualPaymentSubmissionItems(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmissionItem::class);
    }

    public function paymentGroupItems(): HasMany
    {
        return $this->hasMany(PaymentGroupItem::class);
    }

    public function creditCharge(): HasOne
    {
        return $this->hasOne(InvoiceCreditCharge::class);
    }

    public function vipCreditRepaymentAllocations(): HasMany
    {
        return $this->hasMany(VipCreditRepaymentAllocation::class);
    }

    /**
     * Get consolidated immutable buyer snapshot as an associative array.
     */
    public function getBuyerSnapshotAttribute(): ?array
    {
        if (! $this->buyer_snapshot_name) {
            return null;
        }

        return [
            'name' => $this->buyer_snapshot_name,
            'trade_name' => $this->buyer_snapshot_trade_name,
            'tin' => $this->buyer_snapshot_tin,
            'branch_code' => $this->buyer_snapshot_branch_code,
            'tax_classification' => $this->buyer_snapshot_tax_classification,
            'address' => $this->buyer_snapshot_address,
            'email' => $this->buyer_snapshot_email,
            'phone' => $this->buyer_snapshot_phone,
        ];
    }

    /**
     * Get consolidated immutable issuer snapshot as an associative array.
     */
    public function getIssuerSnapshotAttribute(): ?array
    {
        if (! $this->issuer_snapshot_name) {
            return null;
        }

        return [
            'registered_name' => $this->issuer_snapshot_name,
            'trade_name' => $this->issuer_snapshot_trade_name,
            'tin' => $this->issuer_snapshot_tin,
            'branch_code' => $this->issuer_snapshot_branch_code,
            'tax_classification' => $this->issuer_snapshot_tax_classification,
            'address' => $this->issuer_snapshot_address,
            'permit_number' => $this->issuer_snapshot_permit_no,
        ];
    }
}
