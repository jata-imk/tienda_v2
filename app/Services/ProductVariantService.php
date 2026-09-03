<?php

namespace App\Services;

use App\DTOs\Product\CreateVariantsDTO;
use App\DTOs\Product\UpdateVariantDTO;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edicion y baja de variantes sobre un producto ya creado. El `stock` de
 * una variante existente no se toca aqui: eso vive en InventoryMovementService.
 */
class ProductVariantService
{
    use AppliesGridConditions;

    public function listByProduct(int $productId): Collection
    {
        return ProductVariant::with(['size', 'color'])
            ->where('id_product', $productId)
            ->get();
    }

    /**
     * Consulta paginada o filtrada de las variantes de un producto.
     *
     * @param array<string, mixed> $filters
     * @return array{items: mixed, total: ?int, page?: int, pages?: int}|Collection
     */
    public function queryByProduct(int $productId, array $filters = []): array|Collection
    {
        $query = ProductVariant::with(['size', 'color'])
            ->where('id_product', $productId);

        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applyVariantCondition']);
            } else {
                foreach ($filters['w'] as $column => $value) {
                    $this->applyVariantCondition($query, [
                        'column'   => $column,
                        'operator' => is_array($value) ? 'in' : '=',
                        'value'    => $value,
                        'logic'    => 'and',
                    ], 'and');
                }
            }
        }

        if (!empty($filters['f'])) {
            $query->select($filters['f']);
        }

        if (!empty($filters['o'])) {
            $query->orderBy($filters['o']['column'] ?? 'id', $filters['o']['direction'] ?? 'asc');
        }

        $totalCount = isset($filters['totalCount'])
            ? (bool) filter_var($filters['totalCount'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if (!empty($filters['p'])) {
            $page    = (int) ($filters['p']['page'] ?? 1);
            $perPage = (int) ($filters['p']['per_page'] ?? 15);
            $items   = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => $items->items(),
                'total' => $totalCount ? $items->total() : null,
                'page'  => $items->currentPage(),
                'pages' => $items->lastPage(),
            ];
        }

        $items = $query->get();

        if ($totalCount) {
            return ['items' => $items, 'total' => $items->count()];
        }

        return $items;
    }

    /**
     * Resuelve una condicion de variante de producto soportando relaciones y campo virtual `search`.
     *
     * @param mixed $query
     * @param array<string, mixed> $cond
     */
    public function applyVariantCondition($query, array $cond, string $boolean): void
    {
        $or     = $boolean === 'or';
        $column = $cond['column'];

        if ($column === 'search') {
            $value = trim(trim((string) ($cond['value'] ?? ''), '%'));
            if ($value === '') {
                return;
            }

            $likeVal = '%' . addcslashes($value, '%_\\') . '%';
            $method  = $or ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($likeVal) {
                $q->where('sku', 'like', $likeVal)
                  ->orWhere('code_bar', 'like', $likeVal)
                  ->orWhereHas('size', fn($sq) => $sq->where('name', 'like', $likeVal))
                  ->orWhereHas('color', fn($cq) => $cq->where('name', 'like', $likeVal));
            });

            return;
        }

        if ($column === 'size' || $column === 'size_name') {
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? null;
            $method   = $or ? 'orWhereHas' : 'whereHas';

            $query->$method('size', function ($sq) use ($operator, $value) {
                $sq->where('name', $operator, $value);
            });

            return;
        }

        if ($column === 'color' || $column === 'color_name') {
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? null;
            $method   = $or ? 'orWhereHas' : 'whereHas';

            $query->$method('color', function ($cq) use ($operator, $value) {
                $cq->where('name', $operator, $value);
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }

    /**
     * Crea las variantes y, si se pidio `initialMovement`, un movimiento por
     * cada una con existencia inicial > 0. Todo dentro de una transaccion.
     *
     * @return Collection<int, ProductVariant>
     */
    public function store(Product $product, CreateVariantsDTO $dto): Collection
    {
        return DB::transaction(function () use ($product, $dto) {
            $created = new Collection();

            foreach ($dto->variants as $variantInput) {
                $variant = ProductVariant::create([
                    'id_product' => $product->id,
                    'id_size'    => $variantInput->sizeId,
                    'id_color'   => $variantInput->colorId,
                    'sku'        => $variantInput->sku,
                    'code_bar'   => $variantInput->codeBar,
                    'stock'      => $variantInput->stock,
                    'status'     => $variantInput->status,
                ]);

                // Movimiento inicial por cada variante con existencia > 0.
                if ($dto->initialMovement !== null && $variantInput->stock > 0) {
                    InventoryMovement::create([
                        'id_product_variant' => $variant->id,
                        'movement_type'      => $dto->initialMovement->movementType,
                        'quantity'           => $variantInput->stock,
                        'previous_stock'     => 0,
                        'new_stock'          => $variantInput->stock,
                        'reference_type'     => $dto->initialMovement->referenceType,
                        'reference_id'       => $dto->initialMovement->referenceId,
                        'notes'              => $dto->initialMovement->notes,
                        'id_user'            => $dto->initialMovement->userId,
                    ]);
                }

                $created->push($variant->load(['size', 'color']));
            }

            return $created;
        });
    }

    public function update(ProductVariant $variant, UpdateVariantDTO $dto): ProductVariant
    {
        $fields = array_filter([
            'sku'      => $dto->sku,
            'code_bar' => $dto->codeBar,
            'status'   => $dto->status,
        ], fn($v) => $v !== null);

        if ($fields !== []) {
            $variant->update($fields);
        }

        return $variant->fresh(['size', 'color']);
    }

    /**
     * Baja logica: conserva la fila y sus movimientos historicos, pero deja de
     * sumar en el `totalStock` del producto.
     */
    public function deactivate(ProductVariant $variant): ProductVariant
    {
        $variant->update(['status' => 'inactive']);

        return $variant->fresh(['size', 'color']);
    }

    /** Existencia vigente del producto (solo variantes activas). */
    public function totalStock(Product $product): float
    {
        return (float) $product->fresh('variants')->total_stock;
    }
}
