<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use App\Support\CategoryKey;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'categories';

    protected $fillable = ['key', 'name', 'description', 'status'];

    protected static function booted(): void
    {
        // Sin clave capturada se deriva del nombre (`Camisa` -> `CAM`).
        static::creating(function (Category $category) {
            if (blank($category->key)) {
                $category->key = CategoryKey::generate((string) $category->name);
            }
        });
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product', 'id_category', 'id_product');
    }
}
