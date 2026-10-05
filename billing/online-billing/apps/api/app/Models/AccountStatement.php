<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'location_id', 'customer_id', 'statement_number', 'as_of_date', 'currency',
        'invoice_total', 'payment_total', 'cash_applied_total', 'withholding_applied_total', 'outstanding_total', 'customer_snapshot', 'status',
        'generated_by_user_id', 'generated_at', 'voided_by_user_id', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'as_of_date' => 'date:Y-m-d', 'generated_at' => 'datetime', 'voided_at' => 'datetime',
        'invoice_total' => 'string', 'payment_total' => 'string', 'cash_applied_total' => 'string',
        'withholding_applied_total' => 'string', 'outstanding_total' => 'string', 'customer_snapshot' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountStatementItem::class, 'statement_id')->orderBy('business_date')->orderBy('invoice_number');
    }
}
