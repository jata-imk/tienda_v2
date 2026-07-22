<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'colors';

    protected $fillable = ['name', 'hex_color', 'status'];
}
