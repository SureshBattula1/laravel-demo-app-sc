<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('branch_id')) {
            $branchId = null;
            if ($this->filled('vehicle_id')) {
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
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'expense_date' => "$req|date",
            'category' => 'nullable|string|max:100',
            'expense_category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'amount' => "$req|numeric|min:0.01",
            'paid_by' => 'nullable|string|max:100',
            'reference_no' => 'nullable|string|max:100',
            'invoice_no' => 'nullable|string|max:100',
            'attachment_url' => 'nullable|string|max:500',
        ];
    }
}
