<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInfo extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'company_info';

    protected $fillable = [
        'name', 'rfc', 'legal_name', 'tax_regime', 'id_currency', 'logo',
        'street', 'external_number', 'cross_street_one', 'cross_street_two',
        'postal_code', 'neighborhood', 'city',
        'stock_control', 'quantity_integers', 'quantity_decimals',
        'grid_settings', 'status',
    ];

    protected $casts = [
        'stock_control' => 'boolean',
        'grid_settings' => 'array',
    ];

    /** Moneda base en la que opera el negocio. */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'id_currency');
    }
}
