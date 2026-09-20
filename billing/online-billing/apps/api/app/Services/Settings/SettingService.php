<?php

namespace App\Services\Settings;

use App\Exceptions\ConcurrencyException;
use App\Models\AppSetting;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function get(string $key, Organization $organization, ?Location $location = null): mixed
    {
        $def = SettingRegistry::getDefinition($key);

        if ($location && $def['scope'] === 'location') {
            $locSetting = AppSetting::where('organization_id', $organization->id)
                ->where('location_id', $location->id)
                ->where('key', $key)
                ->first();

            if ($locSetting !== null) {
                return $locSetting->value;
            }
        }

        $orgSetting = AppSetting::where('organization_id', $organization->id)
            ->whereNull('location_id')
            ->where('key', $key)
            ->first();

        if ($orgSetting !== null) {
            return $orgSetting->value;
        }

        return $def['default'];
    }

    public function set(
        string $key,
        mixed $rawValue,
        Organization $organization,
        ?Location $location = null,
        ?int $expectedVersion = null,
        ?User $actor = null
    ): AppSetting {
        $validatedValue = SettingRegistry::validate($key, $rawValue, $location !== null);
        $def = SettingRegistry::getDefinition($key);

        return DB::transaction(function () use ($key, $validatedValue, $def, $organization, $location, $expectedVersion, $actor) {
            $query = AppSetting::where('organization_id', $organization->id)
                ->where('key', $key);

            if ($location) {
                $query->where('location_id', $location->id);
            } else {
                $query->whereNull('location_id');
            }

            /** @var AppSetting|null $setting */
            $setting = $query->lockForUpdate()->first();

            if ($setting) {
                if ($expectedVersion !== null && $setting->lock_version !== $expectedVersion) {
                    throw new ConcurrencyException("Setting '{$key}' version mismatch. Expected {$expectedVersion}, found {$setting->lock_version}.");
                }

                $oldValue = $setting->value;
                $setting->value = $validatedValue;
                $setting->lock_version += 1;
                $setting->updated_by = $actor?->id;
                $setting->save();

                AuditLogger::log(
                    action: 'setting.updated',
                    auditable: $setting,
                    oldValues: ['value' => $oldValue],
                    newValues: ['value' => $validatedValue],
                    actor: $actor,
                    organizationId: $organization->id
                );

                return $setting;
            }

            if ($expectedVersion !== null && $expectedVersion !== 0) {
                throw new ConcurrencyException("Setting '{$key}' does not exist yet. Expected version {$expectedVersion} is invalid.");
            }

            $setting = AppSetting::create([
                'organization_id' => $organization->id,
                'location_id' => $location?->id,
                'key' => $key,
                'value' => $validatedValue,
                'type' => $def['type'],
                'lock_version' => 1,
                'updated_by' => $actor?->id,
            ]);

            AuditLogger::log(
                action: 'setting.created',
                auditable: $setting,
                oldValues: null,
                newValues: ['value' => $validatedValue],
                actor: $actor,
                organizationId: $organization->id
            );

            return $setting;
        });
    }
}
