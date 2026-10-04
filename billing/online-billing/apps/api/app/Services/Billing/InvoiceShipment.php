<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Vessel;
use Illuminate\Validation\ValidationException;

class InvoiceShipment
{
    public static function rules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'vessel_id' => [$presence, 'integer'],
            'voyage' => [$presence, 'string', 'max:10', 'regex:/^[0-9]{1,10}$/'],
            'notes' => [$presence, 'string', 'max:150', "regex:/^[A-Za-z0-9][A-Za-z0-9 .,'\\/-]{0,149}$/"],
            'movement_type' => [$presence, 'in:IN,OUT'],
            'route_type' => [$presence, 'in:DOMESTIC,FOREIGN'],
        ];
    }

    /**
     * @return array{vessel_id:int,vessel_name:string,voyage:string,notes:string,movement_type:string,route_type:string}
     */
    public static function resolve(int $organizationId, array $data): array
    {
        $vessel = Vessel::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->find($data['vessel_id'] ?? 0);

        if (! $vessel) {
            throw ValidationException::withMessages([
                'vessel_id' => ['Select a vessel from the vessel list.'],
            ]);
        }

        return [
            'vessel_id' => $vessel->id,
            'vessel_name' => $vessel->name,
            'voyage' => (string) $data['voyage'],
            'notes' => (string) $data['notes'],
            'movement_type' => (string) $data['movement_type'],
            'route_type' => (string) $data['route_type'],
        ];
    }

    public static function assertComplete(Invoice $invoice): void
    {
        if (
            ! $invoice->vessel_id
            || $invoice->voyage === null || $invoice->voyage === ''
            || $invoice->notes === null || trim((string) $invoice->notes) === ''
            || ! in_array($invoice->movement_type, ['IN', 'OUT'], true)
            || ! in_array($invoice->route_type, ['DOMESTIC', 'FOREIGN'], true)
        ) {
            throw ValidationException::withMessages([
                'shipment' => ['Vessel, voyage number, notes, IN/OUT type and domestic or foreign route are required before posting.'],
            ]);
        }
    }
}
