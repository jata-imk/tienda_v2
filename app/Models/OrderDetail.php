<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use NullsUpdatedAtOnCreate;

    /** `reference_type` de los movimientos generados al recibir una linea. */
    public const REFERENCE_TYPE = 'order_detail';

    protected $table = 'order_details';

    protected $fillable = [
        'id_order', 'id_product_variant', 'quantity_ordered',
        'quantity_received', 'unit_cost', 'subtotal', 'notes',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_cost'         => 'decimal:2',
        'subtotal'          => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'id_product_variant');
    }
}
