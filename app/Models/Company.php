<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';

    public $timestamps = false;

    protected $casts = [
        'date_creation' => 'datetime',
        'stock_control' => 'boolean',
    ];

    protected $fillable = [
        'status', 'company_name', 'rfc', 'razon_social', 'regimen_fiscal',
        'img', 'street', 'num_ext', 'cross_one', 'cross_two', 'cp',
        'colony', 'city', 'stock_control', 'integers_q', 'decimals_q', 'date_creation',
    ];
}
