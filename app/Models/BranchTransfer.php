<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BranchTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'from_branch_id',
        'to_branch_id',
        'transfer_type',
        'transfer_date',
        'effective_date',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'remarks',
        'metadata'
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'effective_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('from_branch_id', $branchId)
            ->orWhere('to_branch_id', $branchId);
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'Pending';
    }

    public function isApproved()
    {
        return $this->status === 'Approved';
    }

    public function isCompleted()
    {
        return $this->status === 'Completed';
    }

    public function canBeApproved()
    {
        return $this->status === 'Pending';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['Pending', 'Approved']);
    }
}

