<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreFuelEntryRequest extends FormRequest
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
        if ($this->filled('liters') && ! $this->filled('quantity_litres')) {
            $merge['quantity_litres'] = $this->liters;
        }
        if ($this->filled('price_per_liter') && ! $this->filled('rate_per_litre')) {
            $merge['rate_per_litre'] = $this->price_per_liter;
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
            'entry_date' => "$req|date",
            'fuel_type' => 'nullable|string',
            'quantity_litres' => 'nullable|numeric|min:0.1',
            'liters' => 'nullable|numeric|min:0.1',
            'rate_per_litre' => 'nullable|numeric|min:0.1',
            'price_per_liter' => 'nullable|numeric|min:0.1',
            'total_amount' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'odometer_reading' => "$req|integer|min:0",
            'invoice_no' => 'nullable|string|max:100',
            'fuel_station' => 'nullable|string|max:150',
        ];
    }
}
