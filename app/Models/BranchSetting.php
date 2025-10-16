<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'setting_key',
        'setting_value',
        'setting_type',
        'category',
        'description'
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'string',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Get typed value based on setting_type
    public function getTypedValue()
    {
        return match($this->setting_type) {
            'boolean' => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($this->setting_value) ? (float)$this->setting_value : 0,
            'json' => json_decode($this->setting_value, true),
            default => $this->setting_value
        };
    }

    // Set value with proper type
    public function setTypedValue($value)
    {
        $this->setting_value = match($this->setting_type) {
            'boolean' => $value ? '1' : '0',
            'json' => is_array($value) ? json_encode($value) : $value,
            default => (string)$value
        };
    }

    // Scope to get settings by category
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}

