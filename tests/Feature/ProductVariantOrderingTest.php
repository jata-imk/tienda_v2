<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Orden de presentacion de las variantes: por color alfabetico y, dentro de
 * cada color, por `sizes.sort_order`.
 *
 * Datos del seeder, producto 1 (CAM-001): colores Blanco (id 1) y Azul marino
 * (id 2); tallas 34, 36, 38 con sort_order 20, 30, 40. Alfabeticamente
 * "Azul marino" va antes que "Blanco", asi que el orden esperado NO coincide
 * ni con el id de la variante ni con el id del color.
 */
class ProductVariantOrderingTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'CAM-001-34-AZM',
        'CAM-001-36-AZM',
        'CAM-001-38-AZM',
        'CAM-001-34-BLA',
        'CAM-001-36-BLA',
        'CAM-001-38-BLA',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_product_listing_returns_variants_ordered_by_color_then_size(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/products');

        $response->assertOk();

        $product = collect($response->json('data.items'))->firstWhere('id', 1);

        $this->assertSame(self::EXPECTED, array_column($product['variants'], 'sku'));
    }

    public function test_product_query_returns_variants_ordered_by_color_then_size(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/query', []);

        $response->assertOk();

        $product = collect($response->json('data.items'))->firstWhere('id', 1);

        $this->assertSame(self::EXPECTED, array_column($product['variants'], 'sku'));
    }

    public function test_show_returns_variants_ordered_by_color_then_size(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/products/1');

        $response->assertOk();

        $this->assertSame(self::EXPECTED, array_column($response->json('data.variants'), 'sku'));
    }

    public function test_variants_endpoint_returns_them_ordered_by_color_then_size(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/products/1/variants');

        $response->assertOk();

        $this->assertSame(self::EXPECTED, array_column($response->json('data.items'), 'sku'));
    }

    public function test_explicit_order_overrides_the_default(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants/query', [
            'o' => ['column' => 'sku', 'direction' => 'desc'],
        ]);

        $response->assertOk();

        $skus = array_column($response->json('data.items'), 'sku');

        $expected = $skus;
        rsort($expected);

        $this->assertSame($expected, $skus, 'Un `o` explicito debe ganarle al orden por defecto.');
    }

    /**
     * Guard de regresion: el orden por defecto no debe introducir joins a
     * `colors`/`sizes`, porque los filtros de variante usan columnas sin
     * calificar (`status`, `id`) y un join las volveria ambiguas.
     */
    public function test_filtering_by_status_still_works_alongside_the_default_order(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants/query', [
            'w' => ['status' => 'active'],
        ]);

        $response->assertOk();
        $this->assertSame(self::EXPECTED, array_column($response->json('data.items'), 'sku'));
    }

    public function test_search_filter_still_works_alongside_the_default_order(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants/query', [
            'w' => ['search' => 'Azul'],
        ]);

        $response->assertOk();

        $this->assertSame(
            ['CAM-001-34-AZM', 'CAM-001-36-AZM', 'CAM-001-38-AZM'],
            array_column($response->json('data.items'), 'sku')
        );
    }
}
