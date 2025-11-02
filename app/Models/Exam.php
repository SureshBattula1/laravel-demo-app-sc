<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // 'id', // Not fillable - auto-increment
        'exam_term_id',
        'branch_id',
        'name',
        'exam_type',
        'academic_year',
        'start_date',
        'end_date',
        'total_marks',
        'passing_marks',
        'description',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'total_marks' => 'decimal:2',
        'passing_marks' => 'decimal:2'
    ];

    // Note: Currently using integer IDs instead of UUIDs
    protected $keyType = 'int';
    public $incrementing = true;

    // Removed UUID boot method since table uses integer IDs
    // protected static function boot()
    // {
    //     parent::boot();
    //     static::creating(function ($model) {
    //         if (empty($model->id)) {
    //             $model->id = (string) Str::uuid();
    //         }
    //     });
    // }

    // Relationships
    public function examTerm()
    {
        return $this->belongsTo(ExamTerm::class, 'exam_term_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function schedules()
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function results()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
