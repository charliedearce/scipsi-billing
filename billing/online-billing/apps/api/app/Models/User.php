<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['organization_id', 'name', 'email', 'status', 'phone', 'password', 'lock_version'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'user_locations')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function customerLinks(): HasMany
    {
        return $this->hasMany(CustomerUserLink::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_user_links')
            ->withPivot(['authority_role', 'is_active', 'linked_at'])
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasRole(string|array $roles): bool
    {
        $roleNames = is_array($roles) ? $roles : func_get_args();

        return $this->roles->pluck('name')->intersect($roleNames)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('Administrator')) {
            return true;
        }

        return in_array($permission, $this->getEffectivePermissions(), true);
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    public function getEffectivePermissions(): array
    {
        if ($this->hasRole('Administrator')) {
            return Permission::pluck('name')->all();
        }

        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public function assignRole(string|Role $role): static
    {
        $roleModel = is_string($role) ? Role::where('name', $role)->firstOrFail() : $role;
        $this->roles()->syncWithoutDetaching([$roleModel->id]);

        return $this;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->hasRole('Administrator')) {
            return true;
        }

        $effective = $this->getEffectivePermissions();

        return ! empty(array_intersect($permissions, $effective));
    }

    public function canAccessCustomer(int $customerId): bool
    {
        return $this->customers()
            ->where('customers.id', $customerId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function canAccessLocation(int $locationId): bool
    {
        if ($this->hasRole('Administrator')) {
            return true;
        }

        return $this->locations()->where('locations.id', $locationId)->exists();
    }

    public function canAccessOrganization(int $organizationId): bool
    {
        return $this->organization_id === $organizationId;
    }
}
