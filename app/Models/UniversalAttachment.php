<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class UniversalAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'module',
        'module_id',
        'attachment_type',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'original_name',
        'description',
        'is_active',
        'uploaded_by',
    ];

    protected $appends = [
        'file_url',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    // Relationships
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors
    public function getFileUrlAttribute(): ?string
    {
        $value = $this->file_path;
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        try {
            if (Storage::disk('public')->exists($value)) {
                return Storage::disk('public')->url($value);
            }
        } catch (\Exception $e) {
            // Fall through to manual URL construction.
        }

        if (str_starts_with($value, 'storage/')) {
            return url($value);
        }

        return url('storage/'.ltrim($value, '/'));
    }

    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    // Helper Methods
    public static function getAttachmentTypes()
    {
        return [
            'certification',
            'resume',
            'document',
            'contract',
            'certificate',
            'license',
            'permit',
            'agreement',
            'report',
            'photo',
            'other',
        ];
    }

    // Scopes
    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByModuleId($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('attachment_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Delete file when model is deleted
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attachment) {
            if ($attachment->file_path && Storage::exists($attachment->file_path)) {
                Storage::delete($attachment->file_path);
            }
        });
    }
}
