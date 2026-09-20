<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'location_id',
        'key',
        'value',
        'type',
        'lock_version',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'lock_version' => 'integer',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
