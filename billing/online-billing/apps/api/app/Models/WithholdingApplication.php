<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithholdingApplication extends Model
{
    protected $fillable = ['receipt_id', 'invoice_id', 'certificate_id', 'applied_amount'];

    protected function casts(): array
    {
        return ['applied_amount' => 'string'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(CustomerWithholdingCertificate::class, 'certificate_id');
    }
}
