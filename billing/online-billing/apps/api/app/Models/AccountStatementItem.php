<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountStatementItem extends Model
{
    use HasFactory;

    protected $fillable = ['statement_id', 'invoice_id', 'invoice_number', 'business_date', 'invoice_amount', 'payment_amount', 'outstanding_amount', 'snapshot'];

    protected $casts = ['business_date' => 'date:Y-m-d', 'invoice_amount' => 'string', 'payment_amount' => 'string', 'outstanding_amount' => 'string', 'snapshot' => 'array'];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(AccountStatement::class, 'statement_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
