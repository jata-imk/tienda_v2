<?php

namespace App\Services;

use App\DTOs\InventoryMovement\RegisterMovementsDTO;
use App\Exceptions\InventoryDomainException;
use App\Models\InventoryMovement;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    use AppliesGridConditions;

    private const MOVEMENT_RELATIONS = ['variant.product', 'variant.size', 'variant.color', 'user', 'orderDetail.order'];

    /**
     * Listado o consulta paginada del historial de movimientos (Kardex).
     *
     * @param array<string, mixed> $filters
     * @return array{items: mixed, total: ?int, page?: int, pages?: int}|Collection
     */
    public function index(array $filters = []): array|Collection
    {
        $query = InventoryMovement::with(self::MOVEMENT_RELATIONS);

        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applyMovementCondition']);
            } else {
                foreach ($filters['w'] as $column => $value) {
                    $this->applyMovementCondition($query, [
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
            $this->applyGridOrder($query, $filters, 'inventory_movements');
        } else {
            $query->orderByDesc('id');
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
     * Resuelve una condicion de movimiento de inventario soportando relaciones y virtual `search`.
     *
     * @param mixed $query
     * @param array<string, mixed> $cond
     */
    public function applyMovementCondition($query, array $cond, string $boolean): void
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

            $folio = OrderService::parseFolio($value);

            $query->$method(function ($q) use ($likeVal, $folio) {
                $q->where('notes', 'like', $likeVal)
                  ->orWhere('reference_type', 'like', $likeVal)
                  ->when($folio !== null, fn($fq) => $fq->orWhere(
                      fn($oq) => $this->whereFromOrder($oq, fn($dq) => $dq->whereHas('order', fn($o) => $o->where('folio', $folio))),
                  ))
                  ->orWhereHas('user', fn($uq) => $uq->where('user_name', 'like', $likeVal))
                  ->orWhereHas('variant', function ($vq) use ($likeVal) {
                      $vq->where('sku', 'like', $likeVal)
                         ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', $likeVal)->orWhere('key', 'like', $likeVal));
                  });
            });

            return;
        }

        if ($column === 'id_product' || $column === 'product') {
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? null;
            $method   = $or ? 'orWhereHas' : 'whereHas';

            $query->$method('variant', function ($vq) use ($operator, $value) {
                if ($operator === 'in' || is_array($value)) {
                    $vq->whereIn('id_product', (array) $value);
                } else {
                    $vq->where('id_product', $operator, $value);
                }
            });

            return;
        }

        if ($column === 'sku') {
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? null;
            $method   = $or ? 'orWhereHas' : 'whereHas';

            $query->$method('variant', function ($vq) use ($operator, $value) {
                $vq->where('sku', $operator, $value);
            });

            return;
        }

        if ($column === 'id_order') {
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? null;
            $method   = $or ? 'orWhere' : 'where';

            $query->$method(fn($q) => $this->whereFromOrder($q, function ($dq) use ($operator, $value) {
                if ($operator === 'in' || is_array($value)) {
                    $dq->whereIn('id_order', (array) $value);
                } else {
                    $dq->where('id_order', $operator, $value);
                }
            }));

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }

    /**
     * Movimientos generados por una linea de pedido que cumple `$detailScope`.
     * El filtro por `reference_type` va primero: sin el, un `reference_id`
     * manual que coincida con el id de una linea se colaria.
     */
    private function whereFromOrder($query, callable $detailScope): void
    {
        $query->where('reference_type', OrderDetail::REFERENCE_TYPE)
              ->whereHas('orderDetail', $detailScope);
    }

    /**
     * Tipos de movimiento que incrementan la existencia.
     * Los demas (sale, adjustment) la disminuyen. `purchase` solo lo genera
     * OrderService al recibir un pedido: los requests manuales no lo aceptan.
     */
    private const INCREASE_TYPES = ['entry', 'return', 'cancel', 'purchase'];

    /**
     * Registra uno o varios movimientos sobre variantes de un mismo producto
     * y actualiza su stock de forma atomica: si cualquier linea falla, no se
     * aplica ninguna.
     *
     * @return array{movements: InventoryMovement[], totalStock: float}
     *
     * @throws InventoryDomainException si el producto no controla existencias,
     *         alguna variante no existe / no pertenece al producto, o el stock
     *         quedaria negativo.
     */
    public function register(RegisterMovementsDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $product = Product::find($dto->productId);

            if (!$product) {
                throw new InventoryDomainException('Producto no encontrado.');
            }

            if (!$product->stock_control) {
                throw new InventoryDomainException('El producto no maneja existencias.');
            }

            $variantIds = array_map(fn ($line) => $line->productVariantId, $dto->lines);

            // Se bloquean todas las variantes en una sola consulta, ordenada
            // por PK, para fijar un orden de bloqueo global: sin esto, dos
            // peticiones concurrentes que toquen las mismas variantes en
            // orden distinto pueden producir deadlock en MariaDB.
            $variants = ProductVariant::whereIn('id', $variantIds)
                ->with(['size', 'color'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($variantIds as $variantId) {
                $variant = $variants->get($variantId);

                if (!$variant) {
                    throw new InventoryDomainException("La variante {$variantId} no existe.");
                }

                if ($variant->id_product !== $dto->productId) {
                    throw new InventoryDomainException("La variante {$variantId} no pertenece al producto.");
                }
            }

            $movements = [];

            foreach ($dto->lines as $line) {
                $variant       = $variants->get($line->productVariantId);
                $previousStock = (float) $variant->stock;
                $signedQty     = in_array($line->movementType, self::INCREASE_TYPES, true)
                    ? $line->quantity
                    : -$line->quantity;
                $newStock = $previousStock + $signedQty;

                if ($newStock < 0) {
                    $variantLabel = trim(($variant->color?->name ?? '').' / '.($variant->size?->name ?? ''), ' /');

                    throw new InventoryDomainException(
                        "La variante {$variantLabel} no tiene existencia suficiente.",
                        [
                            'idProductVariant'  => $variant->id,
                            'currentStock'      => $previousStock,
                            'requestedQuantity' => $line->quantity,
                        ],
                    );
                }

                $variant->update(['stock' => $newStock]);

                $movements[] = InventoryMovement::create([
                    'id_product_variant' => $variant->id,
                    'movement_type'      => $line->movementType,
                    'quantity'           => $line->quantity,
                    'previous_stock'     => $previousStock,
                    'new_stock'          => $newStock,
                    'reference_type'     => $dto->referenceType,
                    'reference_id'       => $line->referenceId ?? $dto->referenceId,
                    'notes'              => $dto->notes,
                    'id_user'            => $dto->userId,
                ]);
            }

            $totalStock = (float) ProductVariant::where('id_product', $dto->productId)
                ->where('status', 'active')
                ->sum('stock');

            return [
                'movements'  => $movements,
                'totalStock' => $totalStock,
            ];
        });
    }
}
