<?php

namespace Tests;

use App\Models\Organization;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function invoiceShipmentPayload(?int $organizationId = null, array $overrides = []): array
    {
        $orgId = $organizationId ?? Organization::query()->value('id');
        $name = $overrides['vessel_name'] ?? 'HONDURAS';
        unset($overrides['vessel_name']);
        $vessel = Vessel::query()->firstOrCreate(
            ['organization_id' => $orgId, 'name' => $name],
            [
                'is_active' => true,
                'typical_route' => $overrides['route_type'] ?? 'DOMESTIC',
            ]
        );

        return array_merge([
            'vessel_id' => $vessel->id,
            'voyage' => '102',
            'notes' => 'Test notes 102',
            'movement_type' => 'IN',
            'route_type' => 'DOMESTIC',
        ], $overrides);
    }
}
