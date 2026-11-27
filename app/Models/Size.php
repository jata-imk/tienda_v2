<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'sizes';

    protected $fillable = ['id_size_group', 'name', 'sort_order', 'status'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Grupo fijo A-Z; el ID mantiene separados los grupos con el mismo nombre. */
    public function scopeOrderedByGroup(Builder $query): Builder
    {
        // La subconsulta evita ambigüedad en los filtros por id, name y status.
        return $query->orderBy(
            SizeGroup::select('name')->whereColumn('size_groups.id', 'sizes.id_size_group')->limit(1)
        )->orderBy('sizes.id_size_group');
    }

    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        return $query->orderedByGroup()->orderBy('sizes.sort_order')->orderBy('sizes.id');
    }

    public function sizeGroup()
    {
        return $this->belongsTo(SizeGroup::class, 'id_size_group');
    }
}
