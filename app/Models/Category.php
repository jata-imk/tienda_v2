<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'categories';

    protected $fillable = ['name', 'description', 'status'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product', 'id_category', 'id_product');
    }
}
