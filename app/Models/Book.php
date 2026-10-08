<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'school_id',
        'title',
        'author',
        'author_id',
        'isbn',
        'category',
        'category_id',
        'publisher',
        'publisher_id',
        'published_year',
        'language',
        'edition',
        'ddc_code',
        'call_number',
        'pages',
        'total_copies',
        'available_copies',
        'location',
        'description',
        'cover_image',
        'is_active',
    ];

    protected $casts = [
        'published_year' => 'integer',
        'pages' => 'integer',
        'total_copies' => 'integer',
        'available_copies' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class);
    }

    public function categoryMaster(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function authorMaster(): BelongsTo
    {
        return $this->belongsTo(LibraryAuthor::class, 'author_id');
    }

    public function publisherMaster(): BelongsTo
    {
        return $this->belongsTo(LibraryPublisher::class, 'publisher_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(LibrarySubject::class, 'book_library_subject', 'book_id', 'library_subject_id')->withTimestamps();
    }

    public function copies(): HasMany
    {
        return $this->hasMany(LibraryBookCopy::class, 'book_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(LibraryReservation::class, 'book_id');
    }
}
