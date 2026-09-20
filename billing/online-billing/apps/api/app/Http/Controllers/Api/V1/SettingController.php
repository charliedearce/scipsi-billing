<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Location;
use App\Models\User;
use App\Services\Settings\SettingRegistry;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settingService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $org = $actor->organization;

        $locationId = $request->input('location_id');
        $location = $locationId ? Location::where('organization_id', $org->id)->findOrFail($locationId) : null;

        $definitions = SettingRegistry::all();
        $results = [];

        foreach ($definitions as $key => $def) {
            $effectiveValue = $this->settingService->get($key, $org, $location);

            $query = AppSetting::where('organization_id', $org->id)->where('key', $key);
            if ($location && $def['scope'] === 'location') {
                $query->where('location_id', $location->id);
            } else {
                $query->whereNull('location_id');
            }
            $record = $query->first();

            $results[] = [
                'key' => $key,
                'value' => $effectiveValue,
                'type' => $def['type'],
                'scope' => $def['scope'],
                'description' => $def['description'],
                'lock_version' => $record?->lock_version ?? 0,
                'updated_at' => $record?->updated_at,
            ];
        }

        return response()->json($results);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $org = $actor->organization;

        if (! SettingRegistry::isAllowlisted($key)) {
            return response()->json([
                'error' => [
                    'code' => 'UNKNOWN_SETTING',
                    'message' => "Setting key '{$key}' is not in the allowlisted registry.",
                ],
            ], 404);
        }

        $locationId = $request->input('location_id');
        $location = $locationId ? Location::where('organization_id', $org->id)->findOrFail($locationId) : null;

        $value = $this->settingService->get($key, $org, $location);
        $def = SettingRegistry::getDefinition($key);

        $query = AppSetting::where('organization_id', $org->id)->where('key', $key);
        if ($location && $def['scope'] === 'location') {
            $query->where('location_id', $location->id);
        } else {
            $query->whereNull('location_id');
        }
        $record = $query->first();

        return response()->json([
            'key' => $key,
            'value' => $value,
            'type' => $def['type'],
            'scope' => $def['scope'],
            'description' => $def['description'],
            'lock_version' => $record?->lock_version ?? 0,
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $org = $actor->organization;

        if (SettingRegistry::isForbiddenSecret($key)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN_SETTING',
                    'message' => "Setting key '{$key}' represents a deployment secret and is forbidden from web storage.",
                ],
            ], 403);
        }

        if (! SettingRegistry::isAllowlisted($key)) {
            return response()->json([
                'error' => [
                    'code' => 'UNKNOWN_SETTING',
                    'message' => "Setting key '{$key}' is not in the allowlisted registry.",
                ],
            ], 422);
        }

        $request->validate([
            'value' => 'required',
            'location_id' => 'nullable|integer',
            'lock_version' => 'nullable|integer',
        ]);

        $locationId = $request->input('location_id');
        $location = $locationId ? Location::where('organization_id', $org->id)->findOrFail($locationId) : null;
        $expectedVersion = $request->input('lock_version');

        try {
            $setting = $this->settingService->set(
                key: $key,
                rawValue: $request->input('value'),
                organization: $org,
                location: $location,
                expectedVersion: $expectedVersion,
                actor: $actor
            );

            return response()->json([
                'key' => $setting->key,
                'value' => $setting->value,
                'type' => $setting->type,
                'lock_version' => $setting->lock_version,
                'updated_at' => $setting->updated_at,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_SETTING_VALUE',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
