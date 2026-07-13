<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookIssue extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'book_id',
        'branch_id',
        'school_id',
        'academic_year_id',
        'student_id',
        'teacher_id',
        'borrower_type',
        'issue_date',
        'due_date',
        'return_date',
        'status',
        'fine_amount',
        'fine_paid',
        'fine_paid_at',
        'remarks',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'fine_amount' => 'decimal:2',
        'fine_paid' => 'boolean',
        'fine_paid_at' => 'datetime',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** The borrowing user, whichever column is set. */
    public function borrower(): BelongsTo
    {
        return $this->borrower_type === 'Teacher' ? $this->teacher() : $this->student();
    }
}
