<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    public $timestamps = false;

    protected $table = 'inventario';

    protected $fillable = [
        'category_id', 'status', 'clave', 'name', 'description', 'codebar',
        'price', 'cost', 'stock_control', 'stock', 'discount',
        'type_iva_id', 'rate_iva', 'quota_iva', 'isr', 'imp_esp',
        'date_creation',
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
        'date_creation' => 'datetime',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'category_id');
    }

    public function tipoIva()
    {
        return $this->belongsTo(TipoIva::class, 'type_iva_id');
    }
}
