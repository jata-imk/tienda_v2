<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'id_category', 'id_size_group', 'key', 'name', 'description', 'code_bar',
        'price', 'cost', 'stock_control', 'discount',
        'type_iva', 'rate_iva', 'quota_iva', 'isr', 'imp_esp', 'status',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'cost'          => 'decimal:2',
        'stock_control' => 'boolean',
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

    public function sizeGroup()
    {
        return $this->belongsTo(SizeGroup::class, 'id_size_group');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'id_product');
    }

    /**
     * Existencia total: suma del stock de las variantes activas.
     */
    public function getTotalStockAttribute(): float
    {
        return (float) $this->variants
            ->where('status', 'active')
            ->sum('stock');
    }
}
