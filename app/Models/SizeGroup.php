<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class SizeGroup extends Model
{
    use NullsUpdatedAtOnCreate;

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
