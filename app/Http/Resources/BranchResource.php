<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'branch_type' => $this->branch_type,
            
            // Location
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'pincode' => $this->pincode,
            'region' => $this->region,
            
            // Contact
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website ?? null,
            
            // Principal
            'principal_name' => $this->principal_name,
            'principal_contact' => $this->principal_contact,
            'principal_email' => $this->principal_email,
            
            // Hierarchy
            'parent_branch_id' => $this->parent_branch_id,
            'has_parent' => (bool) $this->parent_branch_id,
            'children_count' => $this->children_count ?? 0,
            'has_children' => ($this->children_count ?? 0) > 0,
            
            // Capacity
            'total_capacity' => $this->total_capacity ?? 0,
            'current_enrollment' => $this->current_enrollment ?? 0,
            'capacity_utilization' => $this->capacity_utilization ?? 0,
            
            // Status
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'is_main_branch' => (bool) $this->is_main_branch,
            
            // Dates
            'established_date' => $this->established_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships (when loaded)
            'parent' => $this->whenLoaded('parentBranch'),
            'children' => $this->whenLoaded('childBranches'),
        ];
    }
}

