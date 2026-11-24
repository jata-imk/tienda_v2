<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\SizeGroup;
use App\Models\User;
use App\Services\VariantInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VariantInitializationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private int $productId;
    private int $groupId;
    private int $otherGroupId;
    private int $sizeId;
    private int $whiteId;
    private int $blueId;
    private int $beigeId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $this->productId = $product->id;
        $this->groupId = $product->id_size_group;
        $this->otherGroupId = SizeGroup::whereKeyNot($this->groupId)->value('id');
        $this->sizeId = Size::where('id_size_group', $this->groupId)->where('name', '34')->value('id');
        $this->whiteId = Color::where('name', 'Blanco')->value('id');
        $this->blueId = Color::where('name', 'Azul marino')->value('id');
        $this->beigeId = Color::where('name', 'Beige')->value('id');
        $this->userId = User::where('user_name', 'admin')->value('id');
        $this->token = $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'admin'])->assertOk()->json('data.token');
    }

    public function test_new_size_creates_zero_variants_and_audits_each_change(): void
    {
        Color::findOrFail($this->blueId)->update(['status' => 'inactive']);
        $sizeId = $this->withToken($this->token)->postJson('/api/sizes', [
            'idSizeGroup' => $this->groupId, 'name' => 'NUEVA',
        ])->assertCreated()->json('data.id');

        $variants = ProductVariant::where('id_product', $this->productId)->where('id_size', $sizeId)->orderBy('id_color')->get();
        $this->assertCount(2, $variants);
        $this->assertSame(['active', 'inactive'], $variants->pluck('status')->all());
        foreach ($variants as $variant) {
            $this->assertDatabaseHas('inventory_movements', [
                'id_product_variant' => $variant->id, 'quantity' => 0, 'previous_stock' => 0, 'new_stock' => 0,
                'reference_type' => VariantInitializationService::INITIAL_REFERENCE, 'id_user' => $this->userId,
            ]);
        }
        $event = DB::table('audit_events')->where('action', 'sizes.created')->latest('id')->first();
        $this->assertNotNull($event);
        $changes = DB::table('audit_changes')->where('audit_event_id', $event->id)->pluck('entity_type')->all();
        $this->assertSame(2, count(array_filter($changes, fn ($entity) => $entity === 'product_variant')));
        $this->assertSame(2, count(array_filter($changes, fn ($entity) => $entity === 'inventory_movement')));
    }

    public function test_size_and_all_generated_variants_roll_back_if_audit_fails(): void
    {
        $before = ProductVariant::count();
        DB::listen(function ($query) {
            if (preg_match('/insert into ["`]audit_changes["`]/i', $query->sql)) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        $this->withToken($this->token)->postJson('/api/sizes', [
            'idSizeGroup' => $this->groupId, 'name' => 'REVERSIBLE',
        ])->assertStatus(503);
        $this->assertDatabaseMissing('sizes', ['name' => 'REVERSIBLE']);
        $this->assertSame($before, ProductVariant::count());
    }

    public function test_inactive_entities_produce_inactive_variants_and_products_without_colors_are_reported(): void
    {
        $original = Product::findOrFail($this->productId);
        $inactiveProduct = $original->replicate();
        $inactiveProduct->key = 'TEST-INACTIVE';
        $inactiveProduct->status = 'inactive';
        $inactiveProduct->save();
        ProductVariant::create([
            'id_product' => $inactiveProduct->id, 'id_size' => $this->sizeId, 'id_color' => $this->whiteId,
            'sku' => 'TEST-INACTIVE-34', 'stock' => 0, 'status' => 'inactive',
        ]);
        $noColors = $original->replicate();
        $noColors->key = 'TEST-NO-COLORS';
        $noColors->save();

        $inactiveSizeId = $this->withToken($this->token)->postJson('/api/sizes', [
            'idSizeGroup' => $this->groupId, 'name' => 'TALLA INACTIVA', 'status' => 'inactive',
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('product_variants', ['id_product' => $this->productId, 'id_size' => $inactiveSizeId, 'status' => 'inactive']);
        $this->assertDatabaseHas('product_variants', ['id_product' => $inactiveProduct->id, 'id_size' => $inactiveSizeId, 'status' => 'inactive']);
        $event = DB::table('audit_events')->where('action', 'sizes.created')->latest('id')->first();
        $this->assertContains($noColors->id, json_decode($event->metadata, true)['productsWithoutColors']);

        $activeSizeId = $this->withToken($this->token)->postJson('/api/sizes', [
            'idSizeGroup' => $this->groupId, 'name' => 'TALLA ACTIVA',
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('product_variants', ['id_product' => $inactiveProduct->id, 'id_size' => $activeSizeId, 'status' => 'inactive']);
    }

    public function test_manual_zero_initialization_and_existing_zero_resubmission(): void
    {
        $existing = ProductVariant::where('id_product', $this->productId)->where('id_size', $this->sizeId)->where('id_color', $this->whiteId)->firstOrFail();
        $response = $this->withToken($this->token)->postJson("/api/products/{$this->productId}/variants", [
            'variants' => [
                ['idSize' => $this->sizeId, 'idColor' => $this->whiteId, 'stock' => 0, 'sku' => $existing->sku, 'status' => 'inactive'],
                ['idSize' => $this->sizeId, 'idColor' => $this->beigeId, 'stock' => 0],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.items');
        $this->assertSame((float) $existing->stock, (float) $existing->fresh()->stock);
        $this->assertSame('active', $existing->fresh()->status);
        $newId = $response->json('data.items.1.id');
        $this->assertDatabaseHas('inventory_movements', ['id_product_variant' => $newId, 'quantity' => 0,
            'reference_type' => VariantInitializationService::INITIAL_REFERENCE, 'id_user' => $this->userId]);
        $this->withToken($this->token)->postJson("/api/products/{$this->productId}/variants", [
            'variants' => [['idSize' => $this->sizeId, 'idColor' => $this->whiteId, 'stock' => 1]],
        ])->assertUnprocessable();
    }

    public function test_product_creation_records_zero_with_authenticated_user(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/products', [
            'categories' => [Product::findOrFail($this->productId)->id_main_category], 'idSizeGroup' => $this->groupId, 'name' => 'Producto cero', 'price' => 10, 'cost' => 5,
            'stockControl' => true, 'typeIva' => 1,
            'variants' => [['idSize' => $this->sizeId, 'idColor' => $this->whiteId, 'stock' => 0]],
        ])->assertCreated();
        $variantId = $response->json('data.variants.0.id');
        $this->assertDatabaseHas('inventory_movements', ['id_product_variant' => $variantId, 'quantity' => 0,
            'reference_type' => VariantInitializationService::INITIAL_REFERENCE, 'id_user' => $this->userId]);
    }

    public function test_backfill_dry_run_and_apply_are_idempotent_and_do_not_change_stock(): void
    {
        $variantsBefore = ProductVariant::count();
        $movementsBefore = InventoryMovement::count();
        $stockBefore = (float) ProductVariant::sum('stock');
        $oldZero = ProductVariant::where('stock', 0)->whereDoesntHave('movements')->firstOrFail();

        $this->artisan('inventory:backfill-variants', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame($variantsBefore, ProductVariant::count());
        $this->assertSame($movementsBefore, InventoryMovement::count());
        $this->artisan('inventory:backfill-variants')->assertExitCode(2);
        $this->artisan('inventory:backfill-variants', ['--user-id' => $this->userId])->assertSuccessful();
        $this->assertGreaterThan($variantsBefore, ProductVariant::count());
        $this->assertSame($stockBefore, (float) ProductVariant::sum('stock'));
        $this->assertDatabaseHas('inventory_movements', ['id_product_variant' => $oldZero->id, 'quantity' => 0,
            'reference_type' => VariantInitializationService::BACKFILL_REFERENCE, 'id_user' => $this->userId]);
        $this->assertDatabaseHas('audit_events', ['action' => 'variants.backfilled', 'actor_id' => $this->userId]);
        $this->assertDatabaseHas('audit_events', ['action' => 'variants.zero_stock_backfilled', 'actor_id' => $this->userId]);
        $variantsAfter = ProductVariant::count();
        $movementsAfter = InventoryMovement::count();
        $eventsAfter = DB::table('audit_events')->count();
        $this->artisan('inventory:backfill-variants', ['--user-id' => $this->userId])->assertSuccessful();
        $this->assertSame($variantsAfter, ProductVariant::count());
        $this->assertSame($movementsAfter, InventoryMovement::count());
        $this->assertSame($eventsAfter, DB::table('audit_events')->count());
    }

    public function test_group_changes_cannot_make_variants_incompatible(): void
    {
        $size = Size::findOrFail($this->sizeId);
        $this->withToken($this->token)->putJson("/api/products/{$this->productId}", ['idSizeGroup' => $this->otherGroupId])->assertUnprocessable();
        $this->withToken($this->token)->putJson("/api/sizes/{$size->id}", ['idSizeGroup' => $this->otherGroupId])->assertUnprocessable();
        $this->assertSame($this->groupId, (int) Product::findOrFail($this->productId)->id_size_group);
        $this->assertSame($this->groupId, (int) $size->fresh()->id_size_group);
    }

    public function test_zero_initialization_is_not_an_out_of_stock_alert(): void
    {
        $sizeId = $this->withToken($this->token)->postJson('/api/sizes', [
            'idSizeGroup' => $this->groupId, 'name' => 'SIN INVENTARIO',
        ])->assertCreated()->json('data.id');
        $response = $this->withToken($this->token)->getJson('/api/dashboard?lowStockThreshold=0')->assertOk();
        $this->assertNotContains('SIN INVENTARIO', array_column($response->json('data.criticalStockBySize'), 'size'));

        $variant = ProductVariant::where('id_product', $this->productId)->where('id_size', $sizeId)->firstOrFail();
        InventoryMovement::create(['id_product_variant' => $variant->id, 'movement_type' => 'entry', 'quantity' => 1,
            'previous_stock' => 0, 'new_stock' => 1, 'id_user' => $this->userId]);
        $response = $this->withToken($this->token)->getJson('/api/dashboard?lowStockThreshold=0')->assertOk();
        $this->assertContains('SIN INVENTARIO', array_column($response->json('data.criticalStockBySize'), 'size'));
    }
}
