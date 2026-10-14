<?php

namespace Tests\Feature\Concerns;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Atajos para los tests de pedidos. Requiere el seeder de desarrollo
 * (`CAM-001` con 6 variantes, costo 600) y usa al usuario 1 (administrador)
 * sin pasar por el middleware JWT, igual que InventoryMovementTest.
 */
trait BuildsPurchaseOrders
{
    protected User $admin;

    protected function bootPurchaseOrders(): void
    {
        $this->seed();
        $this->admin = User::findOrFail(1);
    }

    protected function asAdmin(): static
    {
        $this->actingAs($this->admin, 'api');

        return $this->withoutMiddleware();
    }

    protected function variant(string $sku): ProductVariant
    {
        return ProductVariant::where('sku', $sku)->firstOrFail();
    }

    /**
     * @param array<int, array<string, mixed>> $details
     * @param array<string, mixed> $header
     */
    protected function createOrder(array $details, array $header = []): TestResponse
    {
        return $this->asAdmin()->postJson('/api/orders', $header + ['details' => $details]);
    }

    /**
     * Crea y confirma un pedido; devuelve su JSON de respuesta (`data`).
     *
     * @param array<int, array<string, mixed>> $details
     * @return array<string, mixed>
     */
    protected function confirmedOrder(array $details, array $header = []): array
    {
        $id = $this->createOrder($details, $header)->assertCreated()->json('data.id');

        return $this->asAdmin()->postJson("/api/orders/{$id}/confirm")->assertOk()->json('data');
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    protected function receive(int $orderId, array $lines, ?string $notes = null): TestResponse
    {
        return $this->asAdmin()->postJson("/api/orders/{$orderId}/receive", array_filter([
            'notes' => $notes,
            'lines' => $lines,
        ], fn ($v) => $v !== null));
    }
}
