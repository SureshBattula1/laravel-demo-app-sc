<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportHistory extends Model
{
    protected $fillable = [
        'batch_id',
        'entity_type',
        'uploaded_by',
        'branch_id',
        'file_name',
        'file_size',
        'import_context',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'status',
        'uploaded_at',
        'validation_started_at',
        'validation_completed_at',
        'import_started_at',
        'import_completed_at',
        'error_message',
        'error_report_path',
    ];

    protected $casts = [
        'import_context' => 'array',
        'uploaded_at' => 'datetime',
        'validation_started_at' => 'datetime',
        'validation_completed_at' => 'datetime',
        'import_started_at' => 'datetime',
        'import_completed_at' => 'datetime',
    ];

    /**
     * Get the user who uploaded this import
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the branch this import belongs to
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get student import records for this batch
     */
    public function studentImports(): HasMany
    {
        return $this->hasMany(StudentImport::class, 'batch_id', 'batch_id');
    }

    /**
     * Get teacher import records for this batch
     */
    public function teacherImports(): HasMany
    {
        return $this->hasMany(TeacherImport::class, 'batch_id', 'batch_id');
    }

    /**
     * Scope to get imports by entity type
     */
    public function scopeEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to get imports by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent imports
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

