<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoIva extends Model
{
    public $timestamps = false;

    protected $table = 'tipos_iva';

    protected $fillable = ['name', 'description', 'date_creation'];

    protected $casts = [
        'date_creation' => 'datetime',
    ];

    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'type_iva_id');
    }
}
