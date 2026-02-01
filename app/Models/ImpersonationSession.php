<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpersonationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_admin_id',
        'impersonated_user_id',
        'token',
        'ip_address',
        'user_agent',
        'started_at',
        'ended_at',
        'reason',
        'actions_log',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'actions_log' => 'array',
        ];
    }

    // Relationships
    public function companyAdmin()
    {
        return $this->belongsTo(User::class, 'company_admin_id');
    }

    public function impersonatedUser()
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function end(): void
    {
        $this->update([
            'status' => 'Ended',
            'ended_at' => now(),
        ]);
    }

    public function logAction(string $action, array $details = []): void
    {
        $actions = $this->actions_log ?? [];
        $actions[] = [
            'action' => $action,
            'details' => $details,
            'timestamp' => now()->toDateTimeString(),
        ];
        $this->update(['actions_log' => $actions]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeForCompanyAdmin($query, int $companyAdminId)
    {
        return $query->where('company_admin_id', $companyAdminId);
    }
}

