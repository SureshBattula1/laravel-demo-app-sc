<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use BelongsToTenant;
    //
}
