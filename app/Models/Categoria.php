<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    public $timestamps = false;

    protected $table = 'categorias';

    protected $fillable = ['status', 'name', 'description', 'date_creation'];

    protected $casts = [
        'date_creation' => 'datetime',
    ];

    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'category_id');
    }
}
