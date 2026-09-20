<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditAccountVersion extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_HELD = 'HELD';

    public const STATUS_DISABLED = 'DISABLED';

    protected $fillable = [
        'customer_credit_account_id', 'version_number', 'status', 'credit_limit_mode_override',
        'credit_limit_amount_override', 'payment_terms_days_override', 'due_date_basis_override',
        'overdue_restriction_override', 'overdue_grace_days_override', 'overdue_amount_threshold_override',
        'effective_from', 'effective_to', 'created_by_user_id', 'reason', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit_amount_override' => 'string', 'payment_terms_days_override' => 'integer',
            'overdue_grace_days_override' => 'integer', 'overdue_amount_threshold_override' => 'string',
            'effective_from' => 'datetime', 'effective_to' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditAccount::class, 'customer_credit_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
