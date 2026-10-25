<?php

namespace App\Support;

use App\DTOs\Product\VariantInputDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SKU de una variante, misma regla que el frontend
 * (`src/productos/models/product-variant-sku.ts`):
 * `CLAVENORMALIZADA-S{idTalla}-C{idColor}`, ej. `CAM-001` + talla 2 + color 17
 * -> `CAM001-S2-C17`. Se usan los ids del catalogo, no los nombres.
 */
final class VariantSku
{
    public const MAX_LENGTH = 80;

    /**
     * Mayusculas, sin acentos y solo letras/numeros; `PROD` si no queda nada.
     */
    public static function normalizeKey(string $productKey): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($productKey)));

        return $normalized !== '' ? $normalized : 'PROD';
    }

    /**
     * SKU libre para la combinacion. Solo choca si dos claves normalizan igual
     * (`CAM-001` y `CAM 001`); en ese caso se agrega `-2`, `-3`...
     *
     * @param string[] $taken SKUs ya asignados en el mismo envio.
     */
    public static function for(string $productKey, int $sizeId, int $colorId, array $taken = []): string
    {
        $base  = self::base($productKey, $sizeId, $colorId);
        $taken = array_map('strtoupper', $taken);

        $sku = $base;

        for ($n = 2; self::exists($sku, $taken); $n++) {
            $sku = "{$base}-{$n}";
        }

        return $sku;
    }

    /**
     * SKU de la regla sin sufijo anti-choque. Deja 4 caracteres libres para el
     * `-N` sin pasar de MAX_LENGTH.
     */
    public static function base(string $productKey, int $sizeId, int $colorId): string
    {
        $suffix = "-S{$sizeId}-C{$colorId}";

        return substr(self::normalizeKey($productKey), 0, self::MAX_LENGTH - strlen($suffix) - 4) . $suffix;
    }

    /**
     * SKU equivalente tras cambiar la clave del producto, conservando el sufijo
     * anti-choque (`CAM001-S2-C1-2` -> `CAM101-S2-C1-2`). Null si el SKU no
     * sigue la regla con la clave anterior (capturado a mano, o de una clave
     * previa como `GUA0027-...`): esos no se tocan.
     */
    public static function renamed(string $sku, string $oldKey, string $newKey, int $sizeId, int $colorId): ?string
    {
        $pattern = '/^' . preg_quote(self::base($oldKey, $sizeId, $colorId), '/') . '(-\d+)?$/i';

        if (!preg_match($pattern, $sku, $m)) {
            return null;
        }

        return self::base($newKey, $sizeId, $colorId) . ($m[1] ?? '');
    }

    /**
     * SKU final de cada variante del envio, con los mismos indices: respeta el
     * que venga capturado y genera el resto sin chocar con ninguno del envio.
     *
     * @param VariantInputDTO[] $variants
     * @return array<int|string, string>
     */
    public static function resolve(string $productKey, array $variants): array
    {
        $taken = array_values(array_filter(array_map(fn(VariantInputDTO $v) => $v->sku, $variants)));
        $bases = [];

        foreach ($variants as $i => $variant) {
            if ($variant->sku === null) {
                $bases[$i] = self::base($productKey, $variant->sizeId, $variant->colorId);
            }
        }

        // Una sola consulta para el caso comun (ningun choque); los `-N` solo se
        // buscan uno a uno cuando la base ya esta ocupada.
        $existing = $bases === [] ? [] : DB::table('product_variants')
            ->whereIn('sku', array_values($bases))
            ->pluck('sku')
            ->map(fn(string $sku) => strtoupper($sku))
            ->all();

        $skus = [];

        foreach ($variants as $i => $variant) {
            $base = $bases[$i] ?? null;

            if ($base === null) {
                $skus[$i] = $variant->sku;
            } elseif (in_array($base, $existing, true) || in_array($base, array_map('strtoupper', $taken), true)) {
                $skus[$i] = self::for($productKey, $variant->sizeId, $variant->colorId, $taken);
            } else {
                $skus[$i] = $base;
            }

            $taken[] = $skus[$i];
        }

        return $skus;
    }

    /**
     * @param string[] $taken
     */
    private static function exists(string $sku, array $taken): bool
    {
        return in_array(strtoupper($sku), $taken, true)
            || DB::table('product_variants')->where('sku', $sku)->exists();
    }
}
