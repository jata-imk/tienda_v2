<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'product_images';

    protected $fillable = ['id_product', 'id_color', 'path', 'path_thumb'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'id_color');
    }
}
