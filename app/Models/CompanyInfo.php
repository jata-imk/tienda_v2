<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyInfo extends Model
{
    protected $table = 'company_info';

    protected $fillable = [
        'name', 'rfc', 'legal_name', 'tax_regime', 'logo',
        'street', 'external_number', 'cross_street_one', 'cross_street_two',
        'postal_code', 'neighborhood', 'city',
        'stock_control', 'quantity_integers', 'quantity_decimals',
        'grid_settings', 'status',
    ];

    protected $casts = [
        'stock_control' => 'boolean',
        'grid_settings' => 'array',
    ];
}
