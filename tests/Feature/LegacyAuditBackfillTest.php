<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyAuditBackfillTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function api(): static
    {
        return $this->withToken($this->token);
    }

    private function order(int $variantId, float $quantity): array
    {
        return $this->api()->postJson('/api/orders', ['details' => [['idProductVariant' => $variantId, 'quantityOrdered' => $quantity]]])
            ->assertCreated()->json('data');
    }

    private function receive(array $order, float $quantity, string $notes): void
    {
        $this->api()->postJson("/api/orders/{$order['id']}/receive", ['notes' => $notes, 'lines' => [['detailId' => $order['details'][0]['id'], 'quantity' => $quantity]]])->assertOk();
    }

    /** Historial previo a la auditoría: se genera con la captura apagada. */
    private function legacyHistory(): array
    {
        config(['audit.modules' => []]);
        $this->token = $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'admin'])->assertOk()->json('data.token');
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail(); // stock 3

        $order = $this->order($variant->id, 10);
        $this->api()->postJson("/api/orders/{$order['id']}/confirm")->assertOk();
        $this->receive($order, 4, 'Remesa 1');
        $this->receive($order, 6, 'Remesa 2');

        $cancelled = $this->order($variant->id, 1);
        $this->api()->postJson("/api/orders/{$cancelled['id']}/cancel")->assertOk();

        $this->api()->postJson('/api/inventory/movements', [
            'idProduct' => Product::where('key', 'CAM-001')->value('id'), 'idUser' => 1, 'referenceType' => 'manual_adjustment', 'notes' => 'Conteo',
            'movements' => [['idProductVariant' => $variant->id, 'movementType' => 'adjustment', 'quantity' => 2]],
        ])->assertCreated();

        $this->assertSame(0, DB::table('audit_events')->count());
        config(['audit.modules' => ['security', 'users', 'inventory', 'orders', 'products', 'catalogs', 'configuration', 'documents']]);

        return ['variant' => $variant, 'order' => $order, 'cancelled' => $cancelled];
    }

    private function changes(object $event): array
    {
        return DB::table('audit_changes')->where('audit_event_id', $event->id)->orderBy('id')->get()
            ->map(fn ($row) => ['entity' => $row->entity_type, 'id' => $row->entity_id, 'operation' => $row->operation,
                'before' => json_decode((string) $row->before_values, true), 'after' => json_decode((string) $row->after_values, true)])
            ->all();
    }

    public function test_dry_run_counts_without_writing(): void
    {
        $this->legacyHistory();

        $this->artisan('audit:backfill-legacy', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('audit_events')->count());
    }

    public function test_imports_kardex_order_statuses_and_logins_once(): void
    {
        ['variant' => $variant, 'order' => $order, 'cancelled' => $cancelled] = $this->legacyHistory();

        $this->artisan('audit:backfill-legacy')->assertSuccessful();

        $events = DB::table('audit_events')->orderBy('occurred_at')->orderBy('id')->get();
        $this->assertSame(['legacy'], $events->pluck('source')->unique()->values()->all());
        $this->assertSame(['admin'], $events->pluck('actor_name')->unique()->values()->all());
        // Los movimientos iniciales del seeder también son historial: un evento de inventario por petición.
        $this->assertEquals(
            ['auth.login' => 1, 'orders.cancelled' => 1, 'orders.confirmed' => 1, 'orders.created' => 2, 'orders.received' => 2],
            $events->where('module', '!=', 'inventory')->countBy('action')->sortKeys()->all(),
        );
        $metadata = json_decode($events->first()->metadata, true);
        $this->assertTrue($metadata['backfilled']);
        $this->assertTrue($metadata['actorDataIsCurrent']);

        // Primera recepción: un solo evento con existencias, línea, estado e historial.
        [$partial, $full] = $events->where('action', 'orders.received')->values()->all();
        $this->assertSame(['order', (string) $order['id']], [$partial->entity_type, $partial->entity_id]);
        $changes = collect($this->changes($partial))->keyBy('entity');
        $this->assertSame(['stock' => '3.000'], $changes['product_variant']['before']);
        $this->assertSame(['stock' => '7.000'], $changes['product_variant']['after']);
        $this->assertSame('purchase', $changes['inventory_movement']['after']['movementType']);
        $this->assertSame(['quantityReceived' => '0.000'], $changes['order_detail']['before']);
        $this->assertSame(['quantityReceived' => '4.000'], $changes['order_detail']['after']);
        $this->assertSame(['status' => 'confirmed'], $changes['order']['before']);
        $this->assertSame(['status' => 'partially_received'], $changes['order']['after']);
        $this->assertSame('created', $changes['order_status_history']['operation']);

        $fullChanges = collect($this->changes($full))->keyBy('entity');
        $this->assertSame('received', $fullChanges['order']['after']['status']);
        $this->assertNotNull($fullChanges['order']['after']['dateReceived']);
        $this->assertSame(['quantityReceived' => '10.000'], $fullChanges['order_detail']['after']);

        $cancel = $events->firstWhere('action', 'orders.cancelled');
        $this->assertSame((string) $cancelled['id'], $cancel->entity_id);

        $manualId = DB::table('inventory_movements')->where('notes', 'Conteo')->value('id');
        $manual = $events->first(fn ($event) => (json_decode($event->metadata, true)['legacy']['inventory_movements'] ?? null) === [$manualId]);
        $this->assertSame('inventory.movements_registered', $manual->action);
        $this->assertSame('inventory', $manual->module);
        $manualChanges = collect($this->changes($manual))->keyBy('entity');
        $this->assertSame(['stock' => '13.000'], $manualChanges['product_variant']['before']);
        $this->assertSame(['stock' => '11.000'], $manualChanges['product_variant']['after']);

        $login = $events->firstWhere('action', 'auth.login');
        $this->assertSame(['security', 'user', null], [$login->module, $login->entity_type, $login->entity_id]);
        $this->assertSame(['user_session'], array_column($this->changes($login), 'entity'));

        // Cada fila original queda cubierta y la fecha se guarda en UTC.
        foreach (['inventory_movements' => 'inventory_movement', 'order_status_history' => 'order_status_history', 'user_sessions' => 'user_session'] as $table => $entity) {
            $this->assertSame(DB::table($table)->count(), DB::table('audit_changes')->where('entity_type', $entity)->where('operation', 'created')->count(), $table);
        }
        $this->assertSame(
            \Carbon\CarbonImmutable::parse(DB::table('user_sessions')->value('created_at'), config('app.timezone'))->utc()->format('Y-m-d H:i:s'),
            substr($login->occurred_at, 0, 19),
        );

        // Volver a ejecutarlo no duplica.
        $this->artisan('audit:backfill-legacy')->assertSuccessful();
        $this->assertSame($events->count(), DB::table('audit_events')->count());

        $this->api()->postJson('/api/audit-events/query', ['w' => ['source' => 'legacy'], 'p' => ['s' => 100]])
            ->assertOk()->assertJsonPath('data.totalCount', $events->count());
    }

    public function test_rows_already_captured_live_are_not_imported_again(): void
    {
        $this->legacyHistory();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        // Recepción capturada en vivo después de activar la auditoría.
        $order = $this->order($variant->id, 2);
        $this->api()->postJson("/api/orders/{$order['id']}/confirm")->assertOk();
        $this->receive($order, 2, 'Remesa en vivo');
        $live = DB::table('audit_events')->where('action', 'orders.received')->sole();

        $this->artisan('audit:backfill-legacy')->assertSuccessful();

        $this->assertSame(1, DB::table('audit_events')->where('action', 'orders.received')->where('source', 'api')->count());
        $this->assertSame(2, DB::table('audit_events')->where('action', 'orders.received')->where('source', 'legacy')->count());

        // Mismo formato de cambios que la captura en vivo.
        $shape = fn ($event) => collect($this->changes($event))
            ->map(fn ($change) => [$change['entity'], $change['operation'], array_keys($change['before'] ?? []), array_keys($change['after'] ?? [])])
            ->sortBy(fn ($item) => $item[0])->values()->all();
        $legacy = DB::table('audit_events')->where('action', 'orders.received')->where('source', 'legacy')->orderByDesc('id')->first();
        $this->assertSame($shape($live), $shape($legacy));
    }

    public function test_only_option_limits_sources_and_rejects_unknown_ones(): void
    {
        $this->legacyHistory();

        $this->artisan('audit:backfill-legacy', ['--only' => ['sessions']])->assertSuccessful();
        $this->assertSame(['auth.login'], DB::table('audit_events')->pluck('action')->unique()->values()->all());

        $this->artisan('audit:backfill-legacy', ['--only' => ['sales']])->assertFailed();
    }
}
