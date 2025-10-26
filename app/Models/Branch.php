<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'parent_branch_id', 'branch_type', 'address', 'city', 'state', 
        'country', 'pincode', 'latitude', 'longitude', 'timezone', 'region',
        'phone', 'email', 'website', 'fax', 'emergency_contact',
        'principal_name', 'principal_contact', 'principal_email',
        'established_date', 'opening_date', 'closing_date',
        'affiliation_number', 'board', 'accreditations', 'logo',
        'total_capacity', 'current_enrollment', 'facilities',
        'academic_year_start', 'academic_year_end', 'current_academic_year', 'grades_offered',
        'tax_id', 'bank_name', 'bank_account_number', 'ifsc_code',
        'is_main_branch', 'is_residential', 'has_hostel', 'has_transport', 
        'has_library', 'has_lab', 'has_canteen', 'has_sports',
        'is_active', 'status', 'settings'
    ];

    protected function casts(): array
    {
        return [
            'is_main_branch' => 'boolean',
            'is_residential' => 'boolean',
            'has_hostel' => 'boolean',
            'has_transport' => 'boolean',
            'has_library' => 'boolean',
            'has_lab' => 'boolean',
            'has_canteen' => 'boolean',
            'has_sports' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'facilities' => 'array',
            'grades_offered' => 'array',
            'accreditations' => 'array',
            'established_date' => 'date',
            'opening_date' => 'date',
            'closing_date' => 'date',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    // Hierarchical relationships
    public function parentBranch()
    {
        return $this->belongsTo(Branch::class, 'parent_branch_id');
    }

    public function childBranches()
    {
        return $this->hasMany(Branch::class, 'parent_branch_id');
    }

    public function allDescendants()
    {
        return $this->childBranches()->with('allDescendants');
    }

    // Get all ancestor branches (recursive)
    public function getAncestors()
    {
        $ancestors = collect();
        $parent = $this->parentBranch;
        
        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parentBranch;
        }
        
        return $ancestors;
    }

    // Get all descendant branch IDs (including self)
    // ✅ OPTIMIZED: Uses recursive CTE for 1 query instead of N queries
    public function getDescendantIds($includesSelf = true)
    {
        // Use recursive Common Table Expression (CTE) for efficiency
        // This replaces the N+1 recursive approach with a single query
        $descendants = DB::select("
            WITH RECURSIVE branch_tree AS (
                -- Anchor: Start with child branches
                SELECT id, parent_branch_id
                FROM branches
                WHERE parent_branch_id = ?
                AND deleted_at IS NULL
                
                UNION ALL
                
                -- Recursive: Get children of children
                SELECT b.id, b.parent_branch_id
                FROM branches b
                INNER JOIN branch_tree bt ON b.parent_branch_id = bt.id
                WHERE b.deleted_at IS NULL
            )
            SELECT id FROM branch_tree
        ", [$this->id]);
        
        $ids = collect($descendants)->pluck('id')->toArray();
        
        if ($includesSelf) {
            array_unshift($ids, $this->id);
        }
        
        return $ids;
    }

    // User relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function students()
    {
        return $this->hasMany(User::class)->where('role', 'Student');
    }

    public function teachers()
    {
        return $this->hasMany(User::class)->where('role', 'Teacher');
    }

    public function staff()
    {
        return $this->hasMany(User::class)->whereIn('role', ['Staff', 'BranchAdmin']);
    }

    public function parents()
    {
        return $this->hasMany(User::class)->where('role', 'Parent');
    }

    // Other relationships
    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function branchSettings()
    {
        return $this->hasMany(BranchSetting::class);
    }

    public function universalAttachments()
    {
        return \App\Models\UniversalAttachment::where('module', 'branch')
            ->where('module_id', $this->id);
    }

    public function analytics()
    {
        return $this->hasMany(BranchAnalytic::class);
    }

    public function transfersFrom()
    {
        return $this->hasMany(BranchTransfer::class, 'from_branch_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(BranchTransfer::class, 'to_branch_id');
    }

    // Helper methods
    public function isHeadOffice()
    {
        return $this->branch_type === 'HeadOffice';
    }

    public function isSchool()
    {
        return $this->branch_type === 'School';
    }

    public function hasParent()
    {
        return !is_null($this->parent_branch_id);
    }

    public function hasChildren()
    {
        return $this->childBranches()->count() > 0;
    }

    public function isActive()
    {
        return $this->is_active && $this->status === 'Active';
    }

    public function getCapacityUtilization()
    {
        if ($this->total_capacity == 0) return 0;
        return round(($this->current_enrollment / $this->total_capacity) * 100, 2);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'Active');
    }

    public function scopeHeadOffices($query)
    {
        return $query->where('branch_type', 'HeadOffice');
    }

    public function scopeSchools($query)
    {
        return $query->where('branch_type', 'School');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_branch_id');
    }

    public function scopeInRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeInCity($query, $city)
    {
        return $query->where('city', $city);
    }
}
