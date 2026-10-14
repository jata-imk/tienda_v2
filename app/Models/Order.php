<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use NullsUpdatedAtOnCreate;

    public const DEFAULT_KEY = 'PED';

    protected $table = 'orders';

    protected $fillable = [
        'key', 'folio', 'status', 'id_currency', 'exchange_rate',
        'total_items', 'total_amount', 'supplier_name', 'supplier_details',
        'shipping_address', 'notes', 'date_order', 'date_expected',
        'date_received', 'id_user',
    ];

    protected $casts = [
        'folio'         => 'integer',
        'status'        => OrderStatus::class,
        'exchange_rate' => 'float',
        'total_items'   => 'decimal:3',
        'total_amount'  => 'decimal:2',
        'date_order'    => 'date',
        'date_expected' => 'date',
        'date_received' => 'datetime',
    ];

    /** Digitos del folio visible: alcanza hasta 9,999,999 pedidos por serie. */
    public const FOLIO_DIGITS = 7;

    /** Folio visible, relleno con ceros: `PED-0000012`. */
    public function getFolioLabelAttribute(): string
    {
        return sprintf('%s-%0'.self::FOLIO_DIGITS.'d', $this->key, $this->folio);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class, 'id_order');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'id_currency');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function history()
    {
        return $this->hasMany(OrderStatusHistory::class, 'id_order');
    }
}
