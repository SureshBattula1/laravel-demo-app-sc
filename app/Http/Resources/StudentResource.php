<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'admission_number' => $this->admission_number,
            'roll_number' => $this->roll_number,
            
            // Personal Info
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name ?? "{$this->first_name} {$this->last_name}",
            'email' => $this->email,
            'phone' => $this->phone,
            
            // Academic Info
            'grade' => $this->grade,
            'section' => $this->section,
            'academic_year' => $this->academic_year,
            'branch_id' => $this->branch_id,
            
            // Status
            'student_status' => $this->student_status,
            'is_active' => (bool) $this->is_active,
            
            // Dates
            'admission_date' => $this->admission_date,
            'date_of_birth' => $this->date_of_birth,
            
            // Additional
            'gender' => $this->gender ?? null,
            'blood_group' => $this->blood_group ?? null,
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

