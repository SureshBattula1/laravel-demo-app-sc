<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'student_id' => "$req|exists:users,id",
            'route_id' => "$req|exists:transport_routes,id",
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'pickup_stop_id' => 'nullable|exists:route_stops,id',
            'drop_stop_id' => 'nullable|exists:route_stops,id',
            'stop_name' => 'nullable|string|max:255',
            'pickup_time' => 'nullable',
            'drop_time' => 'nullable',
            'monthly_fee' => "$req|numeric|min:0",
            'status' => 'nullable|in:Active,Inactive',
        ];
    }
}
