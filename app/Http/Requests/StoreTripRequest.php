<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('trip_date') && ($this->trip_date === '' || $this->trip_date === 'null')) {
            $this->merge(['trip_date' => null]);
        }

        if (! $this->filled('branch_id')) {
            $branchId = null;
            if ($this->filled('route_id')) {
                $branchId = DB::table('transport_routes')->where('id', $this->route_id)->value('branch_id');
            }
            if (! $branchId && $this->filled('vehicle_id')) {
                $branchId = DB::table('vehicles')->where('id', $this->vehicle_id)->value('branch_id');
            }
            if (! $branchId) {
                $branchId = $this->user()?->branch_id ?? DB::table('branches')->value('id');
            }
            if ($branchId) {
                $this->merge(['branch_id' => $branchId]);
            }
        }
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'branch_id' => 'nullable|exists:branches,id',
            'route_id' => "$req|exists:transport_routes,id",
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'transport_driver_id' => 'nullable|exists:transport_drivers,id',
            'trip_date' => 'nullable|date',
            'trip_type' => 'nullable|string',
            'status' => 'nullable|string',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'odometer_start' => 'nullable|integer',
            'odometer_end' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
