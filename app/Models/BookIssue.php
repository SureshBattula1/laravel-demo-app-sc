<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BookIssue extends Model
{
    use BelongsToTenant;
    //
}
