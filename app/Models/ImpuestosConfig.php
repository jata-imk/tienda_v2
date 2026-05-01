<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpuestosConfig extends Model
{
    public $timestamps = false;

    protected $table = 'impuestos_config';

    protected $fillable = ['iva', 'isr', 'imp_esp', 'date_creation', 'date_update'];

    protected $casts = [
        'iva'          => 'decimal:2',
        'isr'          => 'decimal:2',
        'imp_esp'      => 'decimal:2',
        'date_creation' => 'datetime',
        'date_update'   => 'datetime',
    ];
}
