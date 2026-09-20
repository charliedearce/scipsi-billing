<?php

namespace App\Services\Identity;

use App\Exceptions\ConcurrencyException;
use App\Exceptions\LastAdminException;
use App\Exceptions\PrivilegeEscalationException;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function createUser(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $orgId = $data['organization_id'] ?? $actor?->organization_id;

            if ($actor && ! $actor->hasRole('Administrator') && $actor->organization_id !== $orgId) {
                throw new PrivilegeEscalationException('Cannot create a user outside your authorized organization.');
            }

            $roleIds = $data['role_ids'] ?? [];
            if (! empty($roleIds) && $actor && ! $actor->hasRole('Administrator')) {
                $hasAdminRole = Role::whereIn('id', $roleIds)->where('name', 'Administrator')->exists();
                if ($hasAdminRole) {
                    throw new PrivilegeEscalationException('Only administrators can assign the Administrator role.');
                }
            }

            $user = User::create([
                'organization_id' => $orgId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
                'phone' => $data['phone'] ?? null,
                'lock_version' => 1,
            ]);

            if (! empty($roleIds)) {
                $user->roles()->sync($roleIds);
            }

            if (! empty($data['location_ids'])) {
                $user->locations()->sync($data['location_ids']);
            }

            AuditLogger::log(
                action: 'user.created',
                auditable: $user,
                oldValues: null,
                newValues: ['name' => $user->name, 'email' => $user->email, 'status' => $user->status],
                actor: $actor,
                organizationId: $orgId
            );

            return $user;
        });
    }

    public function updateUser(User $target, array $data, ?int $expectedVersion = null, ?User $actor = null): User
    {
        return DB::transaction(function () use ($target, $data, $expectedVersion, $actor) {
            /** @var User $user */
            $user = User::where('id', $target->id)->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $user->lock_version !== $expectedVersion) {
                throw new ConcurrencyException("User version mismatch. Expected {$expectedVersion}, found {$user->lock_version}.");
            }

            $oldValues = $user->only(['name', 'email', 'status', 'phone', 'organization_id']);

            // If updating status away from active, check last-admin protection
            if (isset($data['status']) && $data['status'] !== 'active' && $user->status === 'active') {
                $this->ensureNotLastAdmin($user);
            }

            // Role updates and privilege escalation checks
            if (isset($data['role_ids'])) {
                $newRoleIds = $data['role_ids'];
                if ($actor && ! $actor->hasRole('Administrator')) {
                    $hasAdminRole = Role::whereIn('id', $newRoleIds)->where('name', 'Administrator')->exists();
                    if ($hasAdminRole) {
                        throw new PrivilegeEscalationException('Only administrators can assign the Administrator role.');
                    }
                }

                $willHaveAdmin = Role::whereIn('id', $newRoleIds)->where('name', 'Administrator')->exists();
                if ($user->hasRole('Administrator') && ! $willHaveAdmin) {
                    $this->ensureNotLastAdmin($user);
                }

                $user->roles()->sync($newRoleIds);
            }

            if (isset($data['location_ids'])) {
                $user->locations()->sync($data['location_ids']);
            }

            if (isset($data['name'])) {
                $user->name = $data['name'];
            }
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }
            if (isset($data['phone'])) {
                $user->phone = $data['phone'];
            }
            if (isset($data['status'])) {
                $user->status = $data['status'];
            }
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->lock_version += 1;
            $user->save();

            // If user became suspended, immediately revoke sessions
            if ($user->status === 'suspended') {
                $this->revokeSessions($user, $actor);
            }

            AuditLogger::log(
                action: 'user.updated',
                auditable: $user,
                oldValues: $oldValues,
                newValues: $user->only(['name', 'email', 'status', 'phone']),
                actor: $actor,
                organizationId: $user->organization_id
            );

            return $user;
        });
    }

    public function suspendUser(User $target, ?int $expectedVersion = null, ?User $actor = null): User
    {
        return DB::transaction(function () use ($target, $expectedVersion, $actor) {
            /** @var User $user */
            $user = User::where('id', $target->id)->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $user->lock_version !== $expectedVersion) {
                throw new ConcurrencyException("User version mismatch. Expected {$expectedVersion}, found {$user->lock_version}.");
            }

            $this->ensureNotLastAdmin($user);

            $user->status = 'suspended';
            $user->lock_version += 1;
            $user->save();

            $this->revokeSessions($user, $actor);

            AuditLogger::log(
                action: 'user.suspended',
                auditable: $user,
                oldValues: ['status' => 'active'],
                newValues: ['status' => 'suspended'],
                actor: $actor,
                organizationId: $user->organization_id
            );

            return $user;
        });
    }

    public function activateUser(User $target, ?int $expectedVersion = null, ?User $actor = null): User
    {
        return DB::transaction(function () use ($target, $expectedVersion, $actor) {
            /** @var User $user */
            $user = User::where('id', $target->id)->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $user->lock_version !== $expectedVersion) {
                throw new ConcurrencyException("User version mismatch. Expected {$expectedVersion}, found {$user->lock_version}.");
            }

            $user->status = 'active';
            $user->lock_version += 1;
            $user->save();

            AuditLogger::log(
                action: 'user.activated',
                auditable: $user,
                oldValues: ['status' => 'suspended'],
                newValues: ['status' => 'active'],
                actor: $actor,
                organizationId: $user->organization_id
            );

            return $user;
        });
    }

    public function revokeSessions(User $target, ?User $actor = null): void
    {
        // Revoke Sanctum tokens
        $target->tokens()->delete();

        // Revoke database sessions if sessions table exists
        DB::table('sessions')->where('user_id', $target->id)->delete();

        AuditLogger::log(
            action: 'user.sessions_revoked',
            auditable: $target,
            oldValues: null,
            newValues: null,
            actor: $actor,
            organizationId: $target->organization_id
        );
    }

    public function ensureNotLastAdmin(User $target): void
    {
        if ($target->hasRole('Administrator') && $target->status === 'active') {
            $activeAdminCount = User::where('organization_id', $target->organization_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))
                ->count();

            if ($activeAdminCount <= 1) {
                throw new LastAdminException('Cannot modify or suspend the last active administrator for this organization.');
            }
        }
    }
}
