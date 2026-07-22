<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'currencies';

    protected $fillable = ['name', 'code', 'symbol', 'status'];
}
