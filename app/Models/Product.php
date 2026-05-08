<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'id_category', 'key', 'name', 'description', 'code_bar', 'size',
        'price', 'cost', 'stock_control', 'stock', 'discount',
        'type_iva', 'rate_iva', 'quota_iva', 'isr', 'imp_esp', 'status',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'cost'          => 'decimal:2',
        'stock_control' => 'boolean',
        'stock'         => 'decimal:3',
        'discount'      => 'decimal:2',
        'rate_iva'      => 'decimal:2',
        'quota_iva'     => 'decimal:2',
        'isr'           => 'decimal:2',
        'imp_esp'       => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category');
    }
}
