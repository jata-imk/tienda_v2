<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'sizes';

    protected $fillable = ['id_size_group', 'name', 'sort_order', 'status'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function sizeGroup()
    {
        return $this->belongsTo(SizeGroup::class, 'id_size_group');
    }
}
