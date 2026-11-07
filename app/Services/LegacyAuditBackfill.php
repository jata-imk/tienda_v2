<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\UserSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa a la bitácora el historial que ya existía antes de la auditoría:
 * Kardex (`inventory_movements`), estados de pedidos (`order_status_history`) e
 * inicios de sesión (`user_sessions`).
 *
 * - Los eventos replican la forma de los capturados en vivo (acción, módulo, entidad
 *   y cambios antes/después), con `source = legacy` y `metadata.backfilled = true`.
 * - Actor y rol son los **actuales** del usuario (`metadata.actorDataIsCurrent`): la
 *   tabla original no guardó el nombre/rol que tenía al momento de la acción.
 * - Idempotente: una fila se omite si ya existe su cambio `created` en `audit_changes`,
 *   sea porque la capturó la auditoría en vivo o porque ya se importó.
 */
class LegacyAuditBackfill
{
    public const SOURCES = ['kardex', 'orders', 'sessions'];

    private const RECEIVED_STATUSES = ['partially_received', 'received'];

    private const STATUS_ACTIONS = [
        'confirmed' => 'orders.confirmed',
        'cancelled' => 'orders.cancelled',
        'partially_received' => 'orders.received',
        'received' => 'orders.received',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $actors = [];

    public function __construct(private AuditTrail $trail) {}

    /**
     * @param  list<string>  $sources
     * @return array<string, int>
     */
    public function run(array $sources = self::SOURCES, bool $dryRun = false): array
    {
        $events = [];

        // Los estados de recepción se integran al evento de su recepción, por eso se
        // cargan también cuando solo se importa el Kardex.
        $history = array_intersect(['kardex', 'orders'], $sources) !== []
            ? $this->pending('order_status_history', 'order_status_history')
            : collect();
        $absorbed = [];

        if (in_array('kardex', $sources, true)) {
            array_push($events, ...$this->kardexEvents($history, $absorbed));
        }
        if (in_array('orders', $sources, true)) {
            foreach ($history as $row) {
                if (! isset($absorbed[$row->id])) {
                    $events[] = $this->orderEvent($row);
                }
            }
        }
        if (in_array('sessions', $sources, true)) {
            foreach ($this->pending('user_sessions', 'user_session') as $row) {
                $events[] = $this->loginEvent($row);
            }
        }

        $summary = ['inventoryEvents' => 0, 'receptionEvents' => 0, 'orderEvents' => 0, 'loginEvents' => 0, 'changes' => 0, 'skippedWithoutDate' => 0];
        foreach ($events as $index => $event) {
            if ($event['occurred_at'] === null) {
                $summary['skippedWithoutDate']++;
                unset($events[$index]);

                continue;
            }
            $summary[$event['kind']]++;
            $summary['changes'] += count($event['changes']);
        }
        usort($events, fn ($a, $b) => [$a['occurred_at'], $a['sort']] <=> [$b['occurred_at'], $b['sort']]);

        if (! $dryRun) {
            foreach (array_chunk($events, 200) as $chunk) {
                DB::transaction(fn () => array_map($this->insert(...), $chunk));
            }
        }

        return $summary;
    }

    /**
     * Agrupa los movimientos como los generó una misma petición: mismo segundo,
     * usuario, referencia y notas. Los de recepción (`order_detail`) se agrupan por
     * pedido y absorben el cambio de estado de esa recepción.
     *
     * @param  array<int, true>  $absorbed
     * @return list<array<string, mixed>>
     */
    private function kardexEvents(Collection $history, array &$absorbed): array
    {
        $movements = $this->pending('inventory_movements', 'inventory_movement');
        if ($movements->isEmpty()) {
            return [];
        }

        $isReception = fn ($row) => $row->reference_type === OrderDetail::REFERENCE_TYPE && $row->reference_id !== null;
        $detailOrders = DB::table('order_details')
            ->whereIn('id', $movements->filter($isReception)->pluck('reference_id')->unique()->all())
            ->pluck('id_order', 'id');
        $orders = DB::table('orders')->whereIn('id', $detailOrders->unique()->values()->all())->get()->keyBy('id');

        // Recibido acumulado por línea, recorriendo todas las recepciones en orden.
        $received = [];
        $running = [];
        foreach (DB::table('inventory_movements')->where('reference_type', OrderDetail::REFERENCE_TYPE)
            ->whereNotNull('reference_id')->orderBy('created_at')->orderBy('id')
            ->get(['id', 'reference_id', 'quantity']) as $row) {
            $before = $running[$row->reference_id] ?? 0.0;
            $running[$row->reference_id] = round($before + (float) $row->quantity, 3);
            $received[$row->id] = [$before, $running[$row->reference_id]];
        }

        $groups = $movements->groupBy(fn ($row) => implode('|', [
            $row->created_at, $row->id_user, $row->reference_type,
            $isReception($row) ? ($detailOrders[$row->reference_id] ?? '') : '', md5((string) $row->notes),
        ]));

        $events = [];
        foreach ($groups as $group) {
            $first = $group->first();
            $orderId = $isReception($first) ? ($detailOrders[$first->reference_id] ?? null) : null;
            $changes = [];
            $legacy = ['inventory_movements' => $group->pluck('id')->all()];

            foreach ($group as $row) {
                $this->addChange($changes, 'product_variant', $row->id_product_variant, 'updated',
                    $this->snapshot(new ProductVariant, ['stock' => $row->previous_stock]),
                    $this->snapshot(new ProductVariant, ['stock' => $row->new_stock]));
                $this->addChange($changes, 'inventory_movement', $row->id, 'created', null, $this->snapshot(new InventoryMovement, (array) $row));
            }

            if ($orderId !== null) {
                foreach ($group as $row) {
                    if (isset($received[$row->id])) {
                        $this->addChange($changes, 'order_detail', $row->reference_id, 'updated',
                            $this->snapshot(new OrderDetail, ['quantity_received' => $received[$row->id][0]]),
                            $this->snapshot(new OrderDetail, ['quantity_received' => $received[$row->id][1]]));
                    }
                }
                $status = $history->first(fn ($item) => ! isset($absorbed[$item->id])
                    && (int) $item->id_order === (int) $orderId && (int) $item->id_user === (int) $first->id_user
                    && in_array($item->new_status, self::RECEIVED_STATUSES, true)
                    && $item->created_at !== null && $first->created_at !== null
                    && abs(strtotime($item->created_at) - strtotime($first->created_at)) <= 2);
                if ($status) {
                    $absorbed[$status->id] = true;
                    $legacy['order_status_history'] = [$status->id];
                    $this->addStatusChanges($changes, $status, $orders[$orderId] ?? null);
                }
            }

            $events[] = $this->event(
                $orderId !== null ? 'receptionEvents' : 'inventoryEvents',
                $orderId !== null ? 'orders' : 'inventory',
                $orderId !== null ? 'orders.received' : 'inventory.movements_registered',
                $orderId !== null ? ['order', $orderId] : ['inventory_movement', $first->id],
                $first->id_user, $first->created_at, $legacy, $changes, $first->id,
            );
        }

        return $events;
    }

    private function orderEvent(object $row): array
    {
        $changes = [];
        $this->addStatusChanges($changes, $row, DB::table('orders')->find($row->id_order));

        return $this->event('orderEvents', 'orders', $row->previous_status === null ? 'orders.created' : (self::STATUS_ACTIONS[$row->new_status] ?? 'orders.updated'),
            ['order', $row->id_order], $row->id_user, $row->created_at, ['order_status_history' => [$row->id]], $changes, $row->id);
    }

    private function loginEvent(object $row): array
    {
        $changes = [];
        $this->addChange($changes, 'user_session', $row->id, 'created', null,
            $this->snapshot(new UserSession, ['id_user' => $row->id_user, 'expires_at' => $row->expires_at]));

        // Igual que en vivo: el login no tiene ID de entidad, el usuario es el actor.
        return $this->event('loginEvents', 'security', 'auth.login', ['user', null], $row->id_user, $row->created_at,
            ['user_sessions' => [$row->id]], $changes, $row->id);
    }

    /** Cambio de estado del pedido (si no es la creación) y alta de la fila de historial. */
    private function addStatusChanges(array &$changes, object $status, ?object $order): void
    {
        if ($status->previous_status !== null) {
            $before = ['status' => $status->previous_status];
            $after = ['status' => $status->new_status];
            if ($status->new_status === 'received') {
                $before['date_received'] = null;
                $after['date_received'] = $order?->date_received;
            }
            $this->addChange($changes, 'order', $status->id_order, 'updated',
                $this->snapshot(new Order, $before), $this->snapshot(new Order, $after));
        }
        $this->addChange($changes, 'order_status_history', $status->id, 'created', null, $this->snapshot(new OrderStatusHistory, (array) $status));
    }

    /** Fusiona cambios a la misma entidad dentro del evento, igual que `AuditTrail::record()`. */
    private function addChange(array &$changes, string $entity, int|string $id, string $operation, ?array $before, ?array $after): void
    {
        $key = $entity.':'.$id;
        if (isset($changes[$key])) {
            $before = $changes[$key]['before_values'];
            $operation = $changes[$key]['operation'];
        }
        if ($before !== null && $before === $after) {
            unset($changes[$key]);

            return;
        }
        $changes[$key] = ['entity_type' => $entity, 'entity_id' => (string) $id, 'operation' => $operation, 'before_values' => $before, 'after_values' => $after];
    }

    private function snapshot(Model $model, array $attributes): array
    {
        return $this->trail->snapshot($model, $attributes);
    }

    private function event(string $kind, string $module, string $action, array $entity, ?int $userId, ?string $createdAt, array $legacy, array $changes, int $sort): array
    {
        $actor = $this->actor($userId);

        return [
            'kind' => $kind, 'module' => $module, 'action' => $action, 'entity_type' => $entity[0],
            'entity_id' => $entity[1] === null ? null : (string) $entity[1],
            'actor_id' => $actor['id'] ?? null, 'actor_name' => $actor['user_name'] ?? null,
            'role_code' => $actor['code'] ?? null, 'role_name' => $actor['name'] ?? null,
            'metadata' => ['backfilled' => true, 'actorDataIsCurrent' => $actor !== null, 'legacy' => $legacy],
            'occurred_at' => $createdAt === null ? null
                : CarbonImmutable::parse($createdAt, config('app.timezone'))->utc()->format('Y-m-d H:i:s.u'),
            'changes' => array_values($changes), 'sort' => $sort,
        ];
    }

    private function insert(array $event): void
    {
        $id = DB::table('audit_events')->insertGetId([
            'request_id' => (string) Str::uuid(), 'module' => $event['module'], 'action' => $event['action'],
            'outcome' => 'success', 'actor_id' => $event['actor_id'], 'actor_name' => $event['actor_name'],
            'role_code' => $event['role_code'], 'role_name' => $event['role_name'],
            'entity_type' => $event['entity_type'], 'entity_id' => $event['entity_id'], 'source' => 'legacy',
            'route' => null, 'method' => null, 'ip' => null, 'user_agent' => null,
            'metadata' => $this->json($event['metadata']), 'occurred_at' => $event['occurred_at'],
        ]);
        $rows = array_map(fn ($change) => array_replace($change, [
            'audit_event_id' => $id, 'before_values' => $this->json($change['before_values']), 'after_values' => $this->json($change['after_values']),
        ]), $event['changes']);
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('audit_changes')->insert($chunk);
        }
    }

    /** Filas de la tabla original que aún no tienen su alta registrada en la bitácora. */
    private function pending(string $table, string $entity): Collection
    {
        return DB::table($table)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('audit_changes')
                ->where('audit_changes.entity_type', $entity)
                ->where('audit_changes.operation', 'created')
                ->whereRaw("audit_changes.entity_id = CAST($table.id AS CHAR)"))
            ->orderBy('created_at')->orderBy('id')
            ->get();
    }

    private function actor(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }
        if (! array_key_exists($userId, $this->actors)) {
            $row = DB::table('users')->leftJoin('user_types', 'user_types.id', '=', 'users.id_user_type')
                ->where('users.id', $userId)->first(['users.id', 'users.user_name', 'user_types.code', 'user_types.name']);
            $this->actors[$userId] = $row ? (array) $row : null;
        }

        return $this->actors[$userId];
    }

    private function json(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
