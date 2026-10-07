<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class BulkAssignTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // 1. Resolve student_ids to valid user IDs
        if ($this->has('student_ids') && is_array($this->student_ids)) {
            $resolved = [];
            foreach ($this->student_ids as $sid) {
                if (empty($sid)) {
                    continue;
                }
                $userId = DB::table('users')->where('id', $sid)->value('id');
                if (! $userId) {
                    $userId = DB::table('students')->where('id', $sid)->value('user_id');
                }
                if ($userId) {
                    $resolved[] = (int) $userId;
                }
            }
            if (! empty($resolved)) {
                $this->merge(['student_ids' => array_unique($resolved)]);
            }
        }

        // 2. Sanitize nullable stops and times
        $mergeData = [];
        if ($this->has('pickup_stop_id') && ($this->pickup_stop_id === '' || $this->pickup_stop_id === 'null')) {
            $mergeData['pickup_stop_id'] = null;
        }
        if ($this->has('drop_stop_id') && ($this->drop_stop_id === '' || $this->drop_stop_id === 'null')) {
            $mergeData['drop_stop_id'] = null;
        }
        if ($this->has('pickup_time') && ($this->pickup_time === '' || $this->pickup_time === 'null')) {
            $mergeData['pickup_time'] = null;
        }
        if ($this->has('drop_time') && ($this->drop_time === '' || $this->drop_time === 'null')) {
            $mergeData['drop_time'] = null;
        }
        if ($this->has('due_date') && ($this->due_date === '' || $this->due_date === 'null')) {
            $mergeData['due_date'] = null;
        }

        // 3. Fallback annual/monthly fee to route fare if missing
        $fee = $this->input('annual_fee') ?? $this->input('monthly_fee');
        if ($fee === null && $this->filled('route_id')) {
            $routeFare = DB::table('transport_routes')->where('id', $this->route_id)->value('fare');
            if ($routeFare !== null) {
                $fee = (float) $routeFare;
            }
        }
        if ($fee !== null) {
            $mergeData['annual_fee'] = (float) $fee;
            $mergeData['monthly_fee'] = (float) $fee;
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        return [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|exists:users,id',
            'route_id' => 'required|exists:transport_routes,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'pickup_stop_id' => 'nullable|exists:route_stops,id',
            'drop_stop_id' => 'nullable|exists:route_stops,id',
            'stop_name' => 'nullable|string|max:255',
            'pickup_time' => 'nullable',
            'drop_time' => 'nullable',
            'annual_fee' => 'nullable|numeric|min:0',
            'monthly_fee' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:Active,Inactive',
        ];
    }
}
