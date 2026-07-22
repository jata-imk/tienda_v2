<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'inventory_movements';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_product_variant', 'movement_type', 'quantity', 'previous_stock',
        'new_stock', 'reference_type', 'reference_id', 'notes', 'id_user',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'previous_stock' => 'decimal:3',
        'new_stock'      => 'decimal:3',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'id_product_variant');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
