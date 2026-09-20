<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'channel',
        'template_class',
        'current_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NotificationTemplateVersion::class, 'template_id');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(NotificationTemplateVersion::class, 'template_id')
            ->where('status', 'active');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(NotificationTemplateVersion::class, 'template_id')
            ->ofMany(['version' => 'max']);
    }
}
