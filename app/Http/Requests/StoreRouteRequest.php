<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreRouteRequest extends FormRequest
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
            ?? ($id ? DB::table('transport_routes')->where('id', $id)->value('branch_id') : null);

        return [
            'branch_id' => "$req|exists:branches,id",
            'route_number' => [
                "$req", 'string', 'max:50',
                Rule::unique('transport_routes', 'route_number')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'route_name' => "$req|string|max:255",
            'description' => 'nullable|string|max:1000',
            'distance' => 'nullable|numeric|min:0',
            'estimated_time' => 'nullable|integer|min:0',
            'fare' => "$req|numeric|min:0",
            'is_active' => 'boolean',
            // Ordered stops (source of truth in route_stops)
            'stops' => 'nullable|array',
            'stops.*.stop_name' => 'required_with:stops|string|max:255',
            'stops.*.sequence_no' => 'nullable|integer|min:1',
            'stops.*.pickup_time' => 'nullable',
            'stops.*.drop_time' => 'nullable',
            'stops.*.latitude' => 'nullable|numeric',
            'stops.*.longitude' => 'nullable|numeric',
            'stops.*.geofence_radius' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return ['route_number.unique' => 'A route with this number already exists in this branch.'];
    }
}
