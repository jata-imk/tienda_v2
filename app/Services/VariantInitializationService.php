<?php

namespace App\Services;

use App\Models\Color;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Support\VariantSku;
use Illuminate\Auth\AuthenticationException;

class VariantInitializationService
{
    public const INITIAL_REFERENCE = 'variant_initialization';
    public const BACKFILL_REFERENCE = 'variant_backfill';

    public function actorId(): int
    {
        return (int) (auth()->id() ?? throw new AuthenticationException);
    }

    /** The caller holds the product lock and the enclosing audit transaction. */
    public function createMissing(Product $product, Size $size, Color $color, int $userId, string $reference = self::INITIAL_REFERENCE): ?ProductVariant
    {
        if (ProductVariant::where('id_product', $product->id)->where('id_size', $size->id)->where('id_color', $color->id)->exists()) {
            return null;
        }

        $variant = ProductVariant::create([
            'id_product' => $product->id,
            'id_size' => $size->id,
            'id_color' => $color->id,
            'sku' => VariantSku::for($product->key, $size->id, $color->id),
            'code_bar' => null,
            'stock' => 0,
            'status' => $product->status === 'active' && $size->status === 'active' && $color->status === 'active' ? 'active' : 'inactive',
        ]);
        $this->recordZero($variant, $userId, $reference);

        return $variant;
    }

    public function recordZero(ProductVariant $variant, int $userId, string $reference = self::INITIAL_REFERENCE): void
    {
        InventoryMovement::create([
            'id_product_variant' => $variant->id,
            'movement_type' => 'entry',
            'quantity' => 0,
            'previous_stock' => 0,
            'new_stock' => 0,
            'reference_type' => $reference,
            'reference_id' => null,
            'notes' => $reference === self::BACKFILL_REFERENCE ? 'Regularización de variante con stock cero' : 'Inicialización de variante con stock cero',
            'id_user' => $userId,
        ]);
    }
}
