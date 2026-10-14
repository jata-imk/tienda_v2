<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'product_variants';

    protected $fillable = [
        'id_product', 'id_size', 'id_color', 'sku', 'code_bar', 'stock', 'status',
    ];

    protected $casts = [
        'stock' => 'decimal:3',
    ];

    /**
     * Status con el que debe nacer una variante. Un producto inactivo no puede
     * tener variantes activas, asi que la variante hereda su status; si el
     * producto esta activo se respeta lo pedido, porque una variante inactiva
     * en un producto activo es un estado legitimo (talla descontinuada).
     */
    public static function statusUnder(Product $product, string $requested): string
    {
        return $product->status === 'inactive' ? 'inactive' : $requested;
    }

    /**
     * Orden de presentacion de la matriz talla x color: por color alfabetico y,
     * dentro de cada color, por talla. Es el orden por defecto de las respuestas
     * que incluyen variantes.
     */
    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        return $query->orderByColor();
    }

    /** Por color y, dentro de cada color, por talla. */
    public function scopeOrderByColor(Builder $query, string $direction = 'asc'): Builder
    {
        return $query
            ->orderBy(self::colorNameForOrdering(), $direction)
            ->orderBy(self::sizeOrderForOrdering());
    }

    /** Por talla y, dentro de cada talla, por color. */
    public function scopeOrderBySize(Builder $query, string $direction = 'asc'): Builder
    {
        return $query
            ->orderBy(self::sizeOrderForOrdering(), $direction)
            ->orderBy(self::colorNameForOrdering());
    }

    /**
     * Subconsultas correlacionadas, no JOIN, a proposito: los filtros de variante
     * (ProductVariantService::applyVariantCondition y applyGridCondition) usan
     * columnas sin calificar como `status` e `id`, que un join a `colors`/`sizes`
     * volveria ambiguas.
     *
     * `colors` no tiene columna de orden, asi que se ordena por nombre. La talla
     * usa `sizes.sort_order` — la misma convencion que CatalogService y
     * SizeService— para que las tallas por letra (CH, M, G, XG) salgan bien.
     */
    private static function colorNameForOrdering()
    {
        return Color::select('name')->whereColumn('colors.id', 'product_variants.id_color')->take(1);
    }

    private static function sizeOrderForOrdering()
    {
        return Size::select('sort_order')->whereColumn('sizes.id', 'product_variants.id_size')->take(1);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'id_size');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'id_color');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'id_product_variant');
    }
}
