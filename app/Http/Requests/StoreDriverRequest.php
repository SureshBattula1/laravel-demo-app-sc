<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by permission:transport.* on the route
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $req = $isUpdate ? 'sometimes' : 'required';
        $id = $this->route('id');
        $branchId = $this->input('branch_id')
            ?? ($id ? DB::table('transport_drivers')->where('id', $id)->value('branch_id') : null);

        return [
            'branch_id' => "$req|exists:branches,id",
            'name' => "$req|string|max:255",
            'phone' => 'nullable|string|max:20',
            'license_number' => [
                'nullable', 'string', 'max:60',
                Rule::unique('transport_drivers', 'license_number')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'license_expiry' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return ['license_number.unique' => 'A driver with this license number already exists in this branch.'];
    }
}
