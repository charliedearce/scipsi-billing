<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        ?int $organizationId = null
    ): AuditLog {
        $actor = $actor ?? (auth()->check() ? auth()->user() : null);
        $orgId = $organizationId ?? ($actor?->organization_id);

        return AuditLog::create([
            'organization_id' => $orgId,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? (string) $auditable->getKey() : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
