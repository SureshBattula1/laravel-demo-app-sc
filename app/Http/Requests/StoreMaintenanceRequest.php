<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (! $this->filled('branch_id')) {
            $branchId = null;
            if ($this->filled('vehicle_id')) {
                $branchId = DB::table('vehicles')->where('id', $this->vehicle_id)->value('branch_id');
            }
            if (! $branchId) {
                $branchId = $this->user()?->branch_id ?? DB::table('branches')->value('id');
            }
            if ($branchId) {
                $merge['branch_id'] = $branchId;
            }
        }
        if ($this->filled('maintenance_type') && ! $this->filled('service_type')) {
            $merge['service_type'] = $this->maintenance_type;
        }
        if ($this->filled('notes') && ! $this->filled('description')) {
            $merge['description'] = $this->notes;
        }
        if ($this->filled('service_center') && ! $this->filled('garage_name')) {
            $merge['garage_name'] = $this->service_center;
        }
        if ($this->filled('next_service_due_date') && ! $this->filled('next_service_date')) {
            $merge['next_service_date'] = $this->next_service_due_date;
        }
        if ($this->filled('next_service_due_odometer') && ! $this->filled('next_service_km')) {
            $merge['next_service_km'] = $this->next_service_due_odometer;
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'branch_id' => 'nullable|exists:branches,id',
            'vehicle_id' => "$req|exists:vehicles,id",
            'service_date' => "$req|date",
            'service_type' => 'nullable|string',
            'maintenance_type' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'amount' => 'nullable|numeric|min:0',
            'garage_name' => 'nullable|string|max:255',
            'service_center' => 'nullable|string|max:255',
            'next_service_date' => 'nullable|date',
            'next_service_due_date' => 'nullable|date',
            'next_service_km' => 'nullable|integer|min:0',
            'next_service_due_odometer' => 'nullable|integer|min:0',
            'status' => 'nullable|string',
        ];
    }
}
