<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'products';

    protected $fillable = [
        'id_size_group', 'key', 'name', 'description', 'code_bar',
        'image', 'image_thumb',
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

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product', 'id_product', 'id_category');
    }

    public function sizeGroup()
    {
        return $this->belongsTo(SizeGroup::class, 'id_size_group');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'id_product');
    }

    public function colorImages()
    {
        return $this->hasMany(ProductImage::class, 'id_product');
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
