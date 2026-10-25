<?php

namespace App\Services;

use App\DTOs\Product\NextProductKeyDTO;
use App\Models\Category;
use App\Models\SizeGroup;
use Illuminate\Support\Facades\DB;

/**
 * Clave de producto: `CAT[-GRUPO][-SEGMENTO]-NNN`.
 *
 * - `CAT`: clave de la categoria principal (`GUA`).
 * - `GRUPO`: clave del grupo de tallas, si tiene (`NIN` para Niños).
 * - `SEGMENTO`: opcional, capturado en el alta (`DAM`).
 * - `NNN`: consecutivo de 3 digitos por prefijo completo; parte del maximo que
 *   ya exista, sin rellenar huecos (`CAM-001`, `CAM-007` -> `CAM-008`).
 *   `CAM-NIN-xxx` no cuenta para `CAM`.
 */
class ProductKeyService
{
    public const DIGITS = 3;

    /**
     * Unico punto donde se normalizan las partes de la clave: la vista previa
     * y el alta pasan por aqui, asi que no pueden diferir.
     */
    public function prefix(Category $mainCategory, ?SizeGroup $sizeGroup = null, ?string $segment = null): string
    {
        $segment = $segment !== null ? strtoupper(trim($segment)) : null;

        return implode('-', array_filter([
            strtoupper($mainCategory->key),
            $sizeGroup?->key ? strtoupper($sizeGroup->key) : null,
            $segment !== '' ? $segment : null,
        ]));
    }

    /**
     * Vista previa para `GET /products/next-key`: no bloquea ni reserva.
     */
    public function preview(NextProductKeyDTO $dto): string
    {
        return $this->next(
            Category::findOrFail($dto->mainCategoryId),
            $dto->sizeGroupId !== null ? SizeGroup::find($dto->sizeGroupId) : null,
            $dto->keySegment,
        );
    }

    /**
     * Siguiente clave para un alta. Bloquea la fila de la categoria principal
     * (fila existente, sin los gap locks que daria un `MAX ... FOR UPDATE` sobre
     * `products`; ver `OrderService::nextFolio()`) y serializa las altas de esa
     * categoria.
     *
     * Debe ser la PRIMERA lectura de la transaccion: con REPEATABLE READ la
     * foto de InnoDB se fija en la primera lectura no bloqueante. Si algo se
     * leyera antes, la consulta de claves usaria esa foto vieja aun despues de
     * esperar el lock y no veria la clave que acaba de insertar el alta
     * anterior. Leyendo primero con `FOR UPDATE`, la foto se toma ya con el
     * lock en mano.
     */
    public function nextForInsert(int $mainCategoryId, ?int $sizeGroupId = null, ?string $segment = null): string
    {
        $mainCategory = Category::whereKey($mainCategoryId)->lockForUpdate()->firstOrFail();

        return $this->next(
            $mainCategory,
            $sizeGroupId !== null ? SizeGroup::find($sizeGroupId) : null,
            $segment,
        );
    }

    private function next(Category $mainCategory, ?SizeGroup $sizeGroup, ?string $segment): string
    {
        $prefix  = $this->prefix($mainCategory, $sizeGroup, $segment);
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/i';

        $max = DB::table('products')
            ->where('key', 'like', addcslashes($prefix, '%_\\') . '-%')
            ->pluck('key')
            ->reduce(function (int $max, string $key) use ($pattern) {
                return preg_match($pattern, $key, $m) ? max($max, (int) $m[1]) : $max;
            }, 0);

        return $prefix . '-' . str_pad((string) ($max + 1), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
