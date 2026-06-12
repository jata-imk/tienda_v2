<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SizeGroup extends Model
{
    protected $table = 'size_groups';

    protected $fillable = ['name', 'description', 'status'];

    public function sizes()
    {
        return $this->hasMany(Size::class, 'id_size_group');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'id_size_group');
    }
}
