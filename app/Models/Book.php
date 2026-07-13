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
        'isbn',
        'category',
        'publisher',
        'published_year',
        'language',
        'edition',
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
}
