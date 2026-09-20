<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalkInCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'location_id',
        'shell_customer_id',
        'buyer_name',
        'buyer_tin',
        'buyer_branch_code',
        'buyer_address',
        'contact_mobile',
        'contact_email',
        'customer_id',
        'linked_at',
        'linked_by_user_id',
        'created_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'linked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }

    /**
     * The internal shell Customer created at walk-in time for invoice FK purposes.
     * Always present; never changes after creation.
     */
    public function shellCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'shell_customer_id');
    }

    /**
     * The portal Customer record linked to this walk-in record (after a successful claim).
     * Starts NULL; set only when a portal user successfully claims a walk-in invoice.
     */
    public function portalCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Invoices created from this walk-in record.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'walk_in_customer_id');
    }

    public function isLinked(): bool
    {
        return $this->customer_id !== null;
    }
}
