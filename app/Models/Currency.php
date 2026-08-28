<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'currencies';

    protected $fillable = ['name', 'code', 'symbol', 'exchange_rate', 'status'];

    // Sin el cast, MariaDB devuelve el decimal como string ("17.250000").
    protected $casts = [
        'exchange_rate' => 'float',
    ];
}
