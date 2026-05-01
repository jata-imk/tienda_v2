<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoMoneda extends Model
{
    public $timestamps = false;

    protected $table = 'tipos_moneda';

    protected $fillable = ['status', 'name', 'code', 'symbol', 'date_creation'];

    protected $casts = [
        'date_creation' => 'datetime',
    ];
}
