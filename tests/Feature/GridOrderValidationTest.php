<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `o[column]` y `o[direction]` llegan del cliente sin filtrar y se pasaban
 * crudos al `orderBy`. Una columna inexistente producia
 * `1054 Unknown column ... in 'ORDER BY'` y una direccion distinta de asc/desc
 * un `InvalidArgumentException`: ambos 500 en MariaDB. Ahora son 422.
 *
 * SQLite no reproduce el 1054 (cita con comillas dobles y degrada un
 * identificador no resuelto a literal de texto), asi que estos tests fijan el
 * contrato por la respuesta HTTP, no por el error del motor.
 */
class GridOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_unknown_order_column_is_rejected_instead_of_crashing(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/query', [
            'o' => ['column' => 'sku', 'direction' => 'asc'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('o.column');
    }

    public function test_invalid_order_direction_is_rejected(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/query', [
            'o' => ['column' => 'key', 'direction' => 'ascendente'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('o.direction');
    }

    public function test_a_valid_order_still_works(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/query', [
            'o' => ['column' => 'key', 'direction' => 'desc'],
        ]);

        $response->assertOk();

        $keys = array_column($response->json('data.items'), 'key');
        $expected = $keys;
        rsort($expected);

        $this->assertSame($expected, $keys);
    }

    /**
     * El guard vive en el trait compartido, asi que cubre los nueve servicios
     * de grid y no solo productos.
     */
    public function test_the_guard_covers_the_other_grid_services(): void
    {
        foreach (['categories', 'colors', 'sizes', 'size-groups', 'currencies', 'users'] as $resource) {
            $response = $this->withoutMiddleware()->postJson("/api/{$resource}/query", [
                'o' => ['column' => 'columna_que_no_existe', 'direction' => 'asc'],
            ]);

            $response->assertStatus(422, "El recurso {$resource} deberia rechazar la columna.");
            $response->assertJsonValidationErrors('o.column');
        }
    }

    public function test_inventory_movements_reject_unknown_columns_too(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/inventory/movements/query', [
                'o' => ['column' => 'columna_que_no_existe', 'direction' => 'asc'],
            ])
            ->assertStatus(422);
    }

    public function test_variants_can_be_ordered_by_color(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants/query', [
            'o' => ['column' => 'color', 'direction' => 'asc'],
        ]);

        $response->assertOk();

        $this->assertSame(
            ['CAM-001-34-AZM', 'CAM-001-36-AZM', 'CAM-001-38-AZM', 'CAM-001-34-BLA', 'CAM-001-36-BLA', 'CAM-001-38-BLA'],
            array_column($response->json('data.items'), 'sku')
        );
    }

    public function test_variants_can_be_ordered_by_color_descending(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants/query', [
            'o' => ['column' => 'color', 'direction' => 'desc'],
        ]);

        $response->assertOk();

        $this->assertSame(
            ['CAM-001-34-BLA', 'CAM-001-36-BLA', 'CAM-001-38-BLA', 'CAM-001-34-AZM', 'CAM-001-36-AZM', 'CAM-001-38-AZM'],
            array_column($response->json('data.items'), 'sku')
        );
    }

    /**
     * La talla ordena por `sizes.sort_order`, no por su nombre ni su id: asi las
     * tallas por letra (CH, M, G, XG) tambien salen bien.
     */
    public function test_variants_can_be_ordered_by_size(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants/query', [
            'o' => ['column' => 'size', 'direction' => 'asc'],
        ]);

        $response->assertOk();

        $this->assertSame(
            ['CAM-001-34-AZM', 'CAM-001-34-BLA', 'CAM-001-36-AZM', 'CAM-001-36-BLA', 'CAM-001-38-AZM', 'CAM-001-38-BLA'],
            array_column($response->json('data.items'), 'sku')
        );
    }

    public function test_variants_still_reject_an_unknown_column(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products/1/variants/query', [
                'o' => ['column' => 'hexColor', 'direction' => 'asc'],
            ])
            ->assertStatus(422);
    }

    /**
     * Guard de regresion: los ordenamientos por defecto de los servicios que
     * tienen uno propio no deben romperse al meter la validacion.
     */
    public function test_default_orderings_are_preserved(): void
    {
        $sizes = $this->withoutMiddleware()->postJson('/api/sizes/query', [])->assertOk()->json('data.items');
        $sortOrders = array_column($sizes, 'sortOrder');
        $sorted = $sortOrders;
        sort($sorted);
        $this->assertSame($sorted, $sortOrders, 'Las tallas deben seguir ordenadas por sortOrder.');

        $movements = $this->withoutMiddleware()
            ->postJson('/api/inventory/movements/query', [])
            ->assertOk()
            ->json('data.items');

        $ids = array_column($movements, 'id');
        $expected = $ids;
        rsort($expected);
        $this->assertSame($expected, $ids, 'Los movimientos deben seguir en id desc.');
    }
}
