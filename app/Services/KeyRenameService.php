<?php

namespace App\Services;

use App\DTOs\Product\KeyRenamePlan;
use App\Exceptions\KeyRenameConflictException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SizeGroup;
use App\Support\VariantSku;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Renombrado opcional en cascada cuando cambia una clave:
 *
 * - producto `CAM-001` -> `CAM-101`: sus SKU de la regla (`CAM001-S2-C1` -> `CAM101-S2-C1`);
 * - categoria `CAM` -> `CMS`: los productos con esa categoria principal y prefijo
 *   `CAM-` (incluye `CAM-NIN-...`) y los SKU de cada uno;
 * - grupo de tallas `NIN` -> `INF`: los productos del grupo con ese segmento
 *   (`GUA-NIN-003` -> `GUA-INF-003`; sin clave nueva -> `GUA-003`) y sus SKU.
 *
 * Solo se toca lo que sigue el patron automatico (`CAT[-SEG...]-NNN` y la regla
 * del SKU); una clave o SKU capturado a mano no se reconoce y se queda igual.
 * Todo o nada: con un solo choque no se aplica ningun cambio.
 */
class KeyRenameService
{
    /** Resto de una clave automatica despues del prefijo: `[-SEG...]-NNN`. */
    private const AUTO_KEY_TAIL = '((?:-[A-Z0-9]+)*-\d+)';

    /**
     * Guardado con renombrado opcional, el mismo flujo para producto,
     * categoria y grupo de tallas:
     *
     * 1. bloquea la fila como PRIMERA lectura de la transaccion (misma regla
     *    que ProductKeyService::nextForInsert()): la foto de InnoDB se toma ya
     *    con el lock, y las altas con clave automatica de esa categoria esperan;
     * 2. calcula el plan con la clave anterior;
     * 3. guarda (`$save`) y aplica el plan. Un choque revierte todo.
     *
     * @template TModel of Model
     *
     * @param TModel                       $model
     * @param Closure(TModel): KeyRenamePlan $planFor
     * @param Closure(TModel): void          $save
     *
     * @throws KeyRenameConflictException
     */
    public function saveWithCascade(Model $model, bool $cascade, Closure $planFor, Closure $save): void
    {
        DB::transaction(function () use ($model, $cascade, $planFor, $save) {
            $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $plan   = $cascade ? $planFor($locked) : null;

            $save($locked);

            if ($plan !== null) {
                $this->apply($plan);
            }
        });
    }

    public function forProduct(Product $product, string $newKey): KeyRenamePlan
    {
        return $this->plan(collect([$product]), fn(Product $p) => $newKey);
    }

    public function forCategory(Category $category, string $newKey): KeyRenamePlan
    {
        $pattern = '/^' . preg_quote($category->key, '/') . self::AUTO_KEY_TAIL . '$/i';

        return $this->plan(
            Product::where('id_main_category', $category->id)->get(['id', 'key']),
            fn(Product $p) => preg_match($pattern, $p->key, $m) ? $newKey . $m[1] : null,
        );
    }

    /**
     * El segmento es la segunda parte de la clave (`CAT-SEG-...`). Un grupo sin
     * clave anterior no dejo segmento que reconocer: plan vacio.
     */
    public function forSizeGroup(SizeGroup $sizeGroup, ?string $newKey): KeyRenamePlan
    {
        if (blank($sizeGroup->key)) {
            return new KeyRenamePlan();
        }

        $pattern = '/^([A-Z0-9]+)-' . preg_quote($sizeGroup->key, '/') . self::AUTO_KEY_TAIL . '$/i';
        $segment = filled($newKey) ? '-' . $newKey : '';

        return $this->plan(
            Product::where('id_size_group', $sizeGroup->id)->get(['id', 'key']),
            fn(Product $p) => preg_match($pattern, $p->key, $m) ? $m[1] . $segment . $m[2] : null,
        );
    }

    /**
     * Aplica el plan dentro de la transaccion del llamador. Dos pasadas por
     * tabla: un valor nuevo puede ser el actual de otra fila del mismo plan
     * (`CAM`->`CA`: el SKU nuevo de `CAM-M01` es `CAM01-...`, el actual de
     * `CAM-01`), y en una sola pasada el indice unico chocaria segun el orden.
     *
     * @throws KeyRenameConflictException
     */
    private function apply(KeyRenamePlan $plan): void
    {
        if ($plan->hasConflicts()) {
            throw new KeyRenameConflictException($plan);
        }

        $now = now();

        foreach ([['products', 'key', $plan->products], ['product_variants', 'sku', $plan->variants]] as [$table, $column, $rows]) {
            $final = array_column($rows, 'to', 'id');

            $this->bulkSet($table, $column, array_map(fn(int $id) => "~rename~{$id}", array_combine(array_keys($final), array_keys($final))), $now);
            $this->bulkSet($table, $column, $final, $now);
        }
    }

    /**
     * `UPDATE ... SET col = CASE id WHEN ... END` por lotes, en lugar de un
     * UPDATE por fila.
     *
     * @param array<int, string> $values id => valor
     */
    private function bulkSet(string $table, string $column, array $values, \DateTimeInterface $now): void
    {
        $grammar = DB::getQueryGrammar();

        foreach (array_chunk($values, 500, true) as $chunk) {
            $bindings = [];

            foreach ($chunk as $id => $value) {
                array_push($bindings, $id, $value);
            }

            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));

            DB::update(
                sprintf(
                    'UPDATE %s SET %s = CASE %s %s END, %s = ? WHERE %s IN (%s)',
                    $grammar->wrapTable($table),
                    $grammar->wrap($column),
                    $grammar->wrap('id'),
                    implode(' ', array_fill(0, count($chunk), 'WHEN ? THEN ?')),
                    $grammar->wrap('updated_at'),
                    $grammar->wrap('id'),
                    $placeholders,
                ),
                [...$bindings, $now, ...array_keys($chunk)],
            );
        }
    }

    /**
     * @param Collection<int, Product>          $products candidatos
     * @param callable(Product): (string|null)  $rename   clave nueva, o null si no sigue el patron
     */
    private function plan(Collection $products, callable $rename): KeyRenamePlan
    {
        $productRows = [];

        foreach ($products as $product) {
            $to = $rename($product);

            if ($to !== null && strtoupper($to) !== strtoupper($product->key)) {
                $productRows[] = ['id' => $product->id, 'from' => $product->key, 'to' => strtoupper($to)];
            }
        }

        $variantRows = $this->variantRows($productRows);

        return new KeyRenamePlan(
            products:  $productRows,
            variants:  $variantRows,
            conflicts: [
                ...$this->conflicts('product', 'products', 'key', $productRows),
                ...$this->conflicts('variant', 'product_variants', 'sku', $variantRows),
            ],
        );
    }

    /**
     * @param list<array{id: int, from: string, to: string}> $productRows
     * @return list<array{id: int, idProduct: int, from: string, to: string}>
     */
    private function variantRows(array $productRows): array
    {
        $byProduct = collect($productRows)->keyBy('id');
        $rows      = [];

        $variants = ProductVariant::whereIn('id_product', $byProduct->keys())
            ->orderBy('id')
            ->get(['id', 'id_product', 'id_size', 'id_color', 'sku']);

        foreach ($variants as $variant) {
            $product = $byProduct[$variant->id_product];
            $to      = VariantSku::renamed($variant->sku, $product['from'], $product['to'], $variant->id_size, $variant->id_color);

            if ($to !== null && $to !== $variant->sku) {
                $rows[] = ['id' => $variant->id, 'idProduct' => $variant->id_product, 'from' => $variant->sku, 'to' => $to];
            }
        }

        return $rows;
    }

    /**
     * Claves nuevas que ya usa un registro que no se renombra, o que se
     * repiten dentro del plan. Comparacion sin distinguir mayusculas, como la
     * collation de MariaDB.
     *
     * @param list<array{id: int, to: string}> $rows
     * @return list<array{type: string, key: string}>
     */
    private function conflicts(string $type, string $table, string $column, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $targets = array_map(fn(array $row) => strtoupper($row['to']), $rows);

        $taken = DB::table($table)
            ->whereIn($column, $targets)
            ->whereNotIn('id', array_column($rows, 'id'))
            ->pluck($column)
            ->map(fn(string $value) => strtoupper($value));

        $repeated = collect($targets)->duplicates();

        return $taken->merge($repeated)
            ->unique()
            ->values()
            ->map(fn(string $key) => ['type' => $type, 'key' => $key])
            ->all();
    }
}
