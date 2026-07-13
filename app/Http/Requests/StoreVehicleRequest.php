<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $req = $isUpdate ? 'sometimes' : 'required';
        $id = $this->route('id');
        $branchId = $this->input('branch_id')
            ?? ($id ? DB::table('vehicles')->where('id', $id)->value('branch_id') : null);

        return [
            'branch_id' => "$req|exists:branches,id",
            'vehicle_number' => [
                "$req", 'string', 'max:50',
                Rule::unique('vehicles', 'vehicle_number')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'vehicle_type' => "$req|in:Bus,Van,Car",
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'capacity' => "$req|integer|min:1",
            'insurance_expiry' => 'nullable|date',
            'fitness_expiry' => 'nullable|date',
            'transport_driver_id' => 'nullable|exists:transport_drivers,id',
            'route_id' => 'nullable|exists:transport_routes,id',
            'status' => 'nullable|in:Active,Maintenance,Inactive',
        ];
    }

    public function messages(): array
    {
        return ['vehicle_number.unique' => 'A vehicle with this number already exists in this branch.'];
    }
}
