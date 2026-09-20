<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transmittal extends Model
{
    use HasFactory;

    public const KIND_YELLOW_INVOICE = 'YELLOW_INVOICE';

    public const KIND_WHITE_RECEIPT = 'WHITE_RECEIPT';

    protected $fillable = [
        'organization_id', 'location_id', 'transmittal_number', 'kind', 'as_of_date', 'currency',
        'source_item_count', 'summary', 'status', 'generated_by_user_id', 'generated_at',
        'voided_by_user_id', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date:Y-m-d',
            'summary' => 'array',
            'generated_at' => 'datetime',
            'voided_at' => 'datetime',
            'source_item_count' => 'integer',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function yellowItems(): HasMany
    {
        return $this->hasMany(YellowTransmittalItem::class)->orderBy('business_date')->orderBy('invoice_number');
    }

    public function whiteItems(): HasMany
    {
        return $this->hasMany(WhiteTransmittalItem::class)->orderBy('business_date')->orderBy('receipt_number');
    }
}
