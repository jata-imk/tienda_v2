<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriz de acceso de pedidos, con JWT y middleware reales: administrador
 * hace todo, almacen consulta y recibe, vendedor no tiene acceso.
 */
class OrderRoleTest extends TestCase
{
    use RefreshDatabase;

    private int $variantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->variantId = ProductVariant::where('sku', 'CAM-001-34-BLA')->value('id');
    }

    public function test_administrator_runs_the_whole_lifecycle(): void
    {
        $token = $this->tokenFor(UserRole::Administrator);

        $order = $this->withToken($token)->postJson('/api/orders', [
            'details' => [['idProductVariant' => $this->variantId, 'quantityOrdered' => 2]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->putJson("/api/orders/{$order['id']}", ['notes' => 'ok'])->assertOk();
        $this->withToken($token)->postJson("/api/orders/{$order['id']}/confirm")->assertOk();
        $this->withToken($token)->postJson("/api/orders/{$order['id']}/receive", [
            'lines' => [['detailId' => $order['details'][0]['id'], 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('data.order.status', 'received');
    }

    public function test_warehouse_can_query_and_receive_but_not_manage_orders(): void
    {
        $order = $this->confirmedOrderAsAdmin();
        $token = $this->tokenFor(UserRole::Warehouse);

        $this->withToken($token)->postJson('/api/orders/query')->assertOk();
        $this->withToken($token)->getJson("/api/orders/{$order['id']}")->assertOk();
        $this->withToken($token)->getJson("/api/orders/{$order['id']}/history")->assertOk();
        $this->withToken($token)->get("/api/orders/{$order['id']}/pdf")->assertOk();

        $receipt = $this->withToken($token)->postJson("/api/orders/{$order['id']}/receive", [
            'lines' => [['detailId' => $order['details'][0]['id'], 'quantity' => 1]],
        ])->assertOk();

        $warehouseUserId = User::where('user_name', 'warehouse-user')->value('id');
        $this->assertSame($warehouseUserId, $receipt->json('data.movements.0.idUser'), 'El movimiento se atribuye a quien recibe.');

        $this->withToken($token)->postJson('/api/orders', [
            'details' => [['idProductVariant' => $this->variantId, 'quantityOrdered' => 1]],
        ])->assertForbidden();
        $this->withToken($token)->putJson("/api/orders/{$order['id']}", ['notes' => 'x'])->assertForbidden();
        $this->withToken($token)->postJson("/api/orders/{$order['id']}/confirm")->assertForbidden();
        $this->withToken($token)->postJson("/api/orders/{$order['id']}/cancel")->assertForbidden();
    }

    public function test_seller_has_no_access_to_orders(): void
    {
        $order = $this->confirmedOrderAsAdmin();
        $token = $this->tokenFor(UserRole::Seller);

        $this->withToken($token)->postJson('/api/orders/query')->assertForbidden();
        $this->withToken($token)->getJson("/api/orders/{$order['id']}")->assertForbidden();
        $this->withToken($token)->getJson("/api/orders/{$order['id']}/history")->assertForbidden();
        $this->withToken($token)->get("/api/orders/{$order['id']}/pdf")->assertForbidden();
        $this->withToken($token)->postJson("/api/orders/{$order['id']}/receive", [
            'lines' => [['detailId' => $order['details'][0]['id'], 'quantity' => 1]],
        ])->assertForbidden();
        $this->withToken($token)->postJson('/api/orders', [])->assertForbidden();
        $this->withToken($token)->postJson("/api/orders/{$order['id']}/cancel")->assertForbidden();
    }

    private function confirmedOrderAsAdmin(): array
    {
        $token = $this->tokenFor(UserRole::Administrator);

        $id = $this->withToken($token)->postJson('/api/orders', [
            'details' => [['idProductVariant' => $this->variantId, 'quantityOrdered' => 5]],
        ])->assertCreated()->json('data.id');

        return $this->withToken($token)->postJson("/api/orders/{$id}/confirm")->assertOk()->json('data');
    }

    private function tokenFor(UserRole $role): string
    {
        $userName = "{$role->value}-user";

        $user = User::firstOrCreate(['user_name' => $userName], [
            'id_user_type' => UserType::where('code', $role->value)->value('id'),
            'first_name'   => 'Test',
            'last_name'    => 'User',
            'email'        => "{$userName}@example.com",
            'password'     => 'password123',
            'status'       => 'active',
        ]);

        return $this->postJson('/api/login', [
            'userName' => $user->user_name,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }
}
