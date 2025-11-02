<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'SuperAdmin' || $this->user()->role === 'BranchAdmin';
    }

    public function rules(): array
    {
        return [
            // Basic Information
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches',
            'branch_type' => 'required|in:HeadOffice,RegionalOffice,School,Campus,SubBranch',
            'parent_branch_id' => 'nullable|exists:branches,id',
            
            // Location
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'pincode' => 'required|string|max:10',
            
            // Contact
            'phone' => 'required|string|max:20|unique:branches',
            'email' => 'required|email|max:255|unique:branches',
            'website' => 'nullable|url|max:255',
            
            // Principal
            'principal_name' => 'nullable|string|max:255',
            'principal_contact' => 'nullable|string|max:20',
            'principal_email' => 'nullable|email|max:255',
            
            // Capacity
            'total_capacity' => 'nullable|integer|min:0',
            'current_enrollment' => 'nullable|integer|min:0',
            
            // Status
            'status' => 'nullable|in:Active,Inactive,UnderConstruction,Closed',
            'is_active' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Branch name is required',
            'code.unique' => 'This branch code already exists',
            'email.unique' => 'This email is already registered',
            'phone.unique' => 'This phone number is already registered'
        ];
    }
}

