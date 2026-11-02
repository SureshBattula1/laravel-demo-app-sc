<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'uuid',
        'company_id',
        'school_id',
        'branch_id',
        'academic_year_id',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'impersonator_id',
        'module',
        'action',
        'severity',
        'description',
        'auditable_type',
        'auditable_id',
        'record_label',
        'old_values',
        'new_values',
        'changed_fields',
        'metadata',
        'request_method',
        'request_url',
        'route_name',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'device_info',
        'signature_hash',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
            if (empty($model->signature_hash)) {
                $model->signature_hash = self::computeSignature($model);
            }
        });
    }

    public static function computeSignature(self $model): string
    {
        $payload = implode('|', [
            (string) ($model->uuid ?? ''),
            (string) ($model->user_id ?? ''),
            (string) ($model->branch_id ?? ''),
            (string) ($model->module ?? ''),
            (string) ($model->action ?? ''),
            (string) ($model->auditable_type ?? ''),
            (string) ($model->auditable_id ?? ''),
            json_encode($model->old_values ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($model->new_values ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key', 'school-audit-key'));
    }

    public function verifySignature(): bool
    {
        if (empty($this->signature_hash)) {
            return false;
        }

        return hash_equals($this->signature_hash, self::computeSignature($this));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
