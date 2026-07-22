<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'product_variants';

    protected $fillable = [
        'id_product', 'id_size', 'id_color', 'sku', 'code_bar', 'stock', 'status',
    ];

    protected $casts = [
        'stock' => 'decimal:3',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'id_size');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'id_color');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'id_product_variant');
    }
}
