<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'period_code', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by_user_id', 'notes'];

    protected $casts = ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'closed_at' => 'datetime'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
