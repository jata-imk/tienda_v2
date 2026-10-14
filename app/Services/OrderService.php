<?php

namespace App\Services;

use App\DTOs\InventoryMovement\MovementLineDTO;
use App\DTOs\InventoryMovement\RegisterMovementsDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Order\OrderLineDTO;
use App\DTOs\Order\OrderTransitionDTO;
use App\DTOs\Order\ReceiveOrderDTO;
use App\DTOs\Order\UpdateOrderDTO;
use App\Enums\OrderStatus;
use App\Exceptions\OrderDomainException;
use App\Models\CompanyInfo;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos de compra a proveedor. Toda transicion de estatus pasa por
 * `transition()`, y la recepcion es el unico punto que toca existencias
 * (via InventoryMovementService::register con el tipo exclusivo `purchase`).
 */
class OrderService
{
    use AppliesGridConditions;

    private const ORDER_RELATIONS = ['details.variant.product', 'details.variant.size', 'details.variant.color', 'currency', 'user'];

    /** Intentos de la transaccion ante deadlock (folio, recepciones concurrentes). */
    private const TRANSACTION_ATTEMPTS = 3;

    /**
     * Mapa de transiciones permitidas. `received` y `cancelled` son finales;
     * `partially_received` solo avanza a `received` (nunca se cancela).
     */
    private const ALLOWED_TRANSITIONS = [
        'draft'              => ['confirmed', 'cancelled'],
        'confirmed'          => ['partially_received', 'received', 'cancelled'],
        'partially_received' => ['received'],
        'received'           => [],
        'cancelled'          => [],
    ];

    /** Memo de la moneda base; la instancia vive una peticion (scoped). */
    private ?int $baseCurrencyId = null;

    public function __construct(private InventoryMovementService $movementService) {}

    /**
     * Extrae el numero de folio de un texto `PED-0000012`, `PED-12`, `0000012`
     * o `12`. null si no lo es.
     */
    public static function parseFolio(string $value): ?int
    {
        $pattern = '/^(?:'.preg_quote(Order::DEFAULT_KEY, '/').'\s*-?\s*)?(\d{1,9})$/i';

        return preg_match($pattern, trim($value), $m) ? (int) $m[1] : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: mixed, total: ?int, page?: int, pages?: int}|Collection
     */
    public function index(array $filters = []): array|Collection
    {
        $query = Order::with(['currency', 'user']);

        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applyOrderCondition']);
            } else {
                foreach ($filters['w'] as $column => $value) {
                    $this->applyOrderCondition($query, [
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
            $this->applyGridOrder($query, $filters, 'orders');
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
     * Resuelve una condicion de pedido soportando relaciones y el campo virtual `search`.
     *
     * @param mixed $query
     * @param array<string, mixed> $cond
     */
    public function applyOrderCondition($query, array $cond, string $boolean): void
    {
        $or     = $boolean === 'or';
        $column = $cond['column'];

        if ($column === 'search') {
            $value = trim(trim((string) ($cond['value'] ?? ''), '%'));
            if ($value === '') {
                return;
            }

            $likeVal = '%' . addcslashes($value, '%_\\') . '%';
            $folio   = self::parseFolio($value);
            $method  = $or ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($likeVal, $folio) {
                $q->where('notes', 'like', $likeVal)
                  ->orWhere('supplier_name', 'like', $likeVal)
                  ->when($folio !== null, fn($fq) => $fq->orWhere('folio', $folio))
                  ->orWhereHas('user', fn($uq) => $uq->where('user_name', 'like', $likeVal));
            });

            return;
        }

        if ($column === 'id_product' || $column === 'sku') {
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? null;
            $method   = $or ? 'orWhereHas' : 'whereHas';
            $target   = $column === 'id_product' ? 'id_product' : 'sku';

            $query->$method('details.variant', function ($vq) use ($operator, $value, $target) {
                if ($operator === 'in' || is_array($value)) {
                    $vq->whereIn($target, (array) $value);
                } else {
                    $vq->where($target, $operator, $value);
                }
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }

    public function show(int $id): ?Order
    {
        return Order::with(self::ORDER_RELATIONS)->find($id);
    }

    public function store(CreateOrderDTO $dto, int $userId): Order
    {
        $order = DB::transaction(function () use ($dto, $userId) {
            $currency = $this->resolveCurrency($dto->currencyId);

            $folio = $this->nextFolio(Order::DEFAULT_KEY);

            $order = Order::create([
                'key'              => Order::DEFAULT_KEY,
                'folio'            => $folio,
                'status'           => OrderStatus::Draft,
                'id_currency'      => $currency->id,
                'exchange_rate'    => $this->isBaseCurrency($currency) ? null : $dto->exchangeRate,
                'supplier_name'    => $dto->supplierName,
                'supplier_details' => $dto->supplierDetails,
                'shipping_address' => $dto->shippingAddress,
                'notes'            => $dto->notes,
                'date_order'       => $dto->dateOrder ?? now()->toDateString(),
                'date_expected'    => $dto->dateExpected,
                'id_user'          => $userId,
            ]);

            $this->syncLines($order, $dto->lines);
            $this->recalculateTotals($order);
            $this->writeHistory($order, null, OrderStatus::Draft, $userId, null);

            return $order;
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($order->id);
    }

    /**
     * Solo en draft. Las lineas enviadas reemplazan el conjunto completo.
     */
    public function update(int $id, UpdateOrderDTO $dto): ?Order
    {
        $found = DB::transaction(function () use ($id, $dto) {
            $order = Order::lockForUpdate()->find($id);

            if (!$order) {
                return false;
            }

            $this->assertDraft($order, 'Solo se puede editar un pedido en borrador.');

            $attributes = $dto->attributes;

            if (array_key_exists('id_currency', $attributes)) {
                $attributes['id_currency'] = $this->resolveCurrency($attributes['id_currency'])->id;

                // Un tipo de cambio capturado para otra moneda no aplica a la nueva.
                if ($attributes['id_currency'] !== $order->id_currency && !array_key_exists('exchange_rate', $attributes)) {
                    $attributes['exchange_rate'] = null;
                }
            }

            $order->fill($attributes);

            if ($this->isBaseCurrency($order->currency()->first())) {
                $order->exchange_rate = null;
            }

            $order->save();

            if ($dto->lines !== null) {
                $this->syncLines($order, $dto->lines);
            }

            $this->recalculateTotals($order);

            return true;
        });

        return $found ? $this->show($id) : null;
    }

    public function confirm(int $id, OrderTransitionDTO $dto, int $userId): ?Order
    {
        $found = DB::transaction(function () use ($id, $dto, $userId) {
            $order = Order::lockForUpdate()->find($id);

            if (!$order) {
                return false;
            }

            $this->assertDraft($order, 'Solo se puede confirmar un pedido en borrador.');

            if (!$order->details()->exists()) {
                throw new OrderDomainException('El pedido no tiene lineas.');
            }

            // Se congela el tipo de cambio: a partir de aqui cambiar
            // `currencies.exchange_rate` ya no afecta al pedido.
            $order->exchange_rate = $this->effectiveExchangeRate($order);

            $this->transition($order, OrderStatus::Confirmed, $dto->notes, $userId);

            return true;
        });

        return $found ? $this->show($id) : null;
    }

    public function cancel(int $id, OrderTransitionDTO $dto, int $userId): ?Order
    {
        $found = DB::transaction(function () use ($id, $dto, $userId) {
            $order = Order::lockForUpdate()->find($id);

            if (!$order) {
                return false;
            }

            if ($order->status === OrderStatus::PartiallyReceived) {
                throw OrderDomainException::conflict('Un pedido con recepciones parciales no se puede cancelar.');
            }

            // Defensa extra: un pedido confirmado nunca deberia tener recepciones.
            if ($order->details()->where('quantity_received', '>', 0)->exists()) {
                throw OrderDomainException::conflict('El pedido ya tiene recepciones y no se puede cancelar.');
            }

            $this->transition($order, OrderStatus::Cancelled, $dto->notes, $userId);

            return true;
        });

        return $found ? $this->show($id) : null;
    }

    /**
     * Recibe mercancia contra las lineas del pedido. Todo en una transaccion:
     * si cualquier linea falla no se aplica nada.
     *
     * Orden de bloqueo: pedido → lineas (por PK) → variantes (register() las
     * bloquea por PK, y los productos se procesan en orden de id). Un orden
     * fijo evita deadlocks entre recepciones concurrentes.
     *
     * @return array{order: Order, movements: \App\Models\InventoryMovement[]}|null
     */
    public function receive(int $id, ReceiveOrderDTO $dto, int $userId): ?array
    {
        $result = DB::transaction(function () use ($id, $dto, $userId) {
            $order = Order::lockForUpdate()->find($id);

            if (!$order) {
                return null;
            }

            if (!in_array($order->status, [OrderStatus::Confirmed, OrderStatus::PartiallyReceived], true)) {
                throw OrderDomainException::conflict('Solo se puede recibir un pedido confirmado o parcialmente recibido.');
            }

            $detailIds = array_map(fn ($line) => $line->detailId, $dto->lines);

            $details = OrderDetail::where('id_order', $order->id)
                ->whereIn('id', $detailIds)
                ->with('variant')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($dto->lines as $line) {
                $detail = $details->get($line->detailId);

                if (!$detail) {
                    throw new OrderDomainException(
                        "La linea {$line->detailId} no pertenece al pedido.",
                        422,
                        ['detailId' => $line->detailId],
                    );
                }

                $ordered  = (float) $detail->quantity_ordered;
                $received = (float) $detail->quantity_received;

                if (round($received + $line->quantity, 3) > round($ordered, 3)) {
                    throw new OrderDomainException(
                        'La cantidad recibida excede lo pendiente de la linea.',
                        422,
                        [
                            'detailId'          => $detail->id,
                            'quantityOrdered'   => $ordered,
                            'quantityReceived'  => $received,
                            'requestedQuantity' => $line->quantity,
                        ],
                    );
                }
            }

            $byProduct = [];

            foreach ($dto->lines as $line) {
                $detail = $details->get($line->detailId);
                $byProduct[$detail->variant->id_product][] = new MovementLineDTO(
                    productVariantId: $detail->id_product_variant,
                    movementType:     'purchase',
                    quantity:         $line->quantity,
                    referenceId:      $detail->id,
                );
            }

            ksort($byProduct);

            $notes     = trim("Recepción {$order->folio_label}. ".($dto->notes ?? ''));
            $movements = [];

            foreach ($byProduct as $productId => $lines) {
                $registered = $this->movementService->register(new RegisterMovementsDTO(
                    productId:     $productId,
                    userId:        $userId,
                    referenceType: OrderDetail::REFERENCE_TYPE,
                    referenceId:   null,
                    notes:         $notes,
                    lines:         $lines,
                ));

                array_push($movements, ...$registered['movements']);
            }

            foreach ($dto->lines as $line) {
                $detail = $details->get($line->detailId);
                $detail->update([
                    'quantity_received' => round((float) $detail->quantity_received + $line->quantity, 3),
                ]);
            }

            $pending = OrderDetail::where('id_order', $order->id)
                ->whereColumn('quantity_received', '<', 'quantity_ordered')
                ->exists();

            $newStatus = $pending ? OrderStatus::PartiallyReceived : OrderStatus::Received;

            if ($newStatus === OrderStatus::Received) {
                $order->date_received = now();
            }

            if ($order->status !== $newStatus) {
                $this->transition($order, $newStatus, $dto->notes, $userId);
            } else {
                $order->save();
            }

            return ['orderId' => $order->id, 'movements' => $movements];
        }, self::TRANSACTION_ATTEMPTS);

        if ($result === null) {
            return null;
        }

        return [
            'order'     => $this->show($result['orderId']),
            'movements' => $result['movements'],
        ];
    }

    public function history(int $id): ?Collection
    {
        if (!Order::whereKey($id)->exists()) {
            return null;
        }

        return OrderStatusHistory::with('user')
            ->where('id_order', $id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Tipo de cambio vigente del pedido: el congelado si ya existe; si no, el
     * de `currencies`. La moneda base siempre vale 1.
     */
    public function effectiveExchangeRate(Order $order): float
    {
        $currency = $order->relationLoaded('currency') ? $order->currency : $order->currency()->first();

        if ($this->isBaseCurrency($currency)) {
            return 1.0;
        }

        return (float) ($order->exchange_rate ?? $currency->exchange_rate);
    }

    /**
     * Moneda base del negocio: la de `company_info`; si no esta configurada, MXN.
     */
    public function baseCurrencyId(): ?int
    {
        return $this->baseCurrencyId ??= CompanyInfo::query()->value('id_currency')
            ?? Currency::where('code', 'MXN')->value('id');
    }

    private function isBaseCurrency(?Currency $currency): bool
    {
        return $currency !== null && $currency->id === $this->baseCurrencyId();
    }

    private function resolveCurrency(?int $currencyId): Currency
    {
        $currency = Currency::find($currencyId ?? $this->baseCurrencyId());

        if (!$currency) {
            throw new OrderDomainException('No hay moneda base configurada.');
        }

        if ($currency->status !== 'active') {
            throw new OrderDomainException('La moneda no esta activa.');
        }

        return $currency;
    }

    /**
     * Siguiente folio de la serie. La fila de `document_sequences` queda
     * bloqueada hasta el commit, asi que las altas concurrentes esperan su turno
     * en lugar de chocar; si la transaccion se revierte, el folio no se consume.
     * El unique (key, folio) de `orders` sigue como red de seguridad.
     */
    private function nextFolio(string $key): int
    {
        $last = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->value('last_folio');

        if ($last === null) {
            throw new \RuntimeException("No existe la serie de folios {$key} en document_sequences.");
        }

        $folio = (int) $last + 1;

        DB::table('document_sequences')->where('key', $key)->update(['last_folio' => $folio]);

        return $folio;
    }

    private function assertDraft(Order $order, string $message): void
    {
        if ($order->status !== OrderStatus::Draft) {
            throw OrderDomainException::conflict($message, ['status' => $order->status->value]);
        }
    }

    /**
     * Reemplaza las lineas del pedido por las del DTO, identificadas por
     * variante: actualiza las existentes, crea las nuevas y borra las ausentes.
     *
     * @param OrderLineDTO[] $lines
     */
    private function syncLines(Order $order, array $lines): void
    {
        $variantIds = array_map(fn ($line) => $line->productVariantId, $lines);

        $variants = ProductVariant::with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $rate     = null;
        $existing = $order->details()->get()->keyBy('id_product_variant');

        foreach ($lines as $line) {
            $variant = $variants->get($line->productVariantId);

            if (!$variant) {
                throw new OrderDomainException("La variante {$line->productVariantId} no existe.");
            }

            $context = ['idProductVariant' => $variant->id, 'sku' => $variant->sku];

            if (!$variant->product->stock_control) {
                throw new OrderDomainException("El producto de la variante {$variant->sku} no maneja existencias.", 422, $context);
            }

            if ($variant->product->status !== 'active' || $variant->status !== 'active') {
                throw new OrderDomainException("La variante {$variant->sku} no esta activa.", 422, $context);
            }

            $unitCost = $line->unitCost;

            if ($unitCost === null) {
                $rate     ??= $this->effectiveExchangeRate($order);
                $unitCost = round((float) $variant->product->cost / $rate, 2);
            }

            $attributes = [
                'quantity_ordered' => $line->quantityOrdered,
                'unit_cost'        => $unitCost,
                'subtotal'         => round($line->quantityOrdered * $unitCost, 2),
                'notes'            => $line->notes,
            ];

            $detail = $existing->get($variant->id);

            if ($detail) {
                $detail->update($attributes);
            } else {
                $order->details()->create($attributes + ['id_product_variant' => $variant->id]);
            }
        }

        $order->details()->whereNotIn('id_product_variant', $variantIds)->delete();
    }

    private function recalculateTotals(Order $order): void
    {
        $details = $order->details()->get();

        foreach ($details as $detail) {
            $subtotal = round((float) $detail->quantity_ordered * (float) $detail->unit_cost, 2);

            if ((float) $detail->subtotal !== $subtotal) {
                $detail->update(['subtotal' => $subtotal]);
            }
        }

        $order->update([
            'total_items'  => round($details->sum(fn ($d) => (float) $d->quantity_ordered), 3),
            'total_amount' => round($details->sum(fn ($d) => round((float) $d->quantity_ordered * (float) $d->unit_cost, 2)), 2),
        ]);
    }

    /**
     * Unico punto donde cambia el status de un pedido.
     */
    private function transition(Order $order, OrderStatus $to, ?string $notes, int $userId): void
    {
        $from = $order->status;

        if (!in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value], true)) {
            throw OrderDomainException::conflict(
                "No se puede pasar el pedido de {$from->value} a {$to->value}.",
                ['status' => $from->value],
            );
        }

        $order->status = $to;
        $order->save();

        $this->writeHistory($order, $from, $to, $userId, $notes);
    }

    private function writeHistory(Order $order, ?OrderStatus $from, OrderStatus $to, int $userId, ?string $notes): void
    {
        OrderStatusHistory::create([
            'id_order'        => $order->id,
            'previous_status' => $from?->value,
            'new_status'      => $to->value,
            'id_user'         => $userId,
            'notes'           => $notes,
        ]);
    }
}
