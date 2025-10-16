<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['SuperAdmin', 'BranchAdmin']);
    }

    public function rules(): array
    {
        return [
            // User Information
            'first_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'last_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            
            // Academic Information
            'branch_id' => 'required|exists:branches,id',
            'admission_number' => 'required|string|unique:students,admission_number',
            'admission_date' => 'required|date|before_or_equal:today',
            'grade' => 'required|string|in:1,2,3,4,5,6,7,8,9,10,11,12',
            'section' => 'nullable|string|max:10',
            'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
            'roll_number' => 'nullable|string|max:50',
            
            // Personal Information
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:Male,Female,Other',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            
            // Address
            'current_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'country' => 'nullable|string|max:100',
            
            // Parent Information
            'father_name' => 'required|string|max:255',
            'father_phone' => 'required|string|max:20',
            'father_email' => 'nullable|email',
            'mother_name' => 'required|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            
            // Emergency Contact
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20'
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'First name should contain only letters',
            'last_name.regex' => 'Last name should contain only letters',
            'password.regex' => 'Password must contain uppercase, lowercase, and number',
            'academic_year.regex' => 'Academic year must be in format YYYY-YYYY (e.g., 2024-2025)',
            'admission_number.unique' => 'This admission number already exists'
        ];
    }
}

