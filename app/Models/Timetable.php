<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    use BelongsToTenant;
    //
}
