<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TransportRoute extends Model
{
    use BelongsToTenant;
    //
}
