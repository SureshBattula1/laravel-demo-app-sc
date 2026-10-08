<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryAuthor extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'school_id',
        'branch_id',
        'name',
        'biography',
        'nationality',
        'born_year',
        'website',
        'is_active',
    ];

    protected $casts = [
        'born_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id');
    }
}
