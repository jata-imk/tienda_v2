<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_post_product_variants_query_returns_paginated_variants(): void
    {
        $product = Product::has('variants')->firstOrFail();

        $response = $this->withoutMiddleware()->postJson("/api/products/{$product->id}/variants/query", [
            'p' => ['page' => 0, 'per_page' => 2],
            'totalCount' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totalCount', $product->variants()->count())
            ->assertJsonCount(2, 'data.items');
    }

    public function test_post_product_variants_query_filters_by_sku(): void
    {
        $product = Product::has('variants')->firstOrFail();
        $variant = $product->variants->first();

        $response = $this->withoutMiddleware()->postJson("/api/products/{$product->id}/variants/query", [
            'w' => [
                ['f' => 'sku', 'ao' => '==', 'v' => $variant->sku, 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame($variant->sku, $items[0]['sku']);
    }

    public function test_post_product_variants_query_search(): void
    {
        $product = Product::has('variants')->firstOrFail();
        $variant = $product->variants->first();

        $response = $this->withoutMiddleware()->postJson("/api/products/{$product->id}/variants/query", [
            'w' => [
                ['f' => 'search', 'ao' => 'contains', 'v' => $variant->sku, 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $skus = collect($response->json('data.items'))->pluck('sku')->all();
        $this->assertContains($variant->sku, $skus);
    }

    public function test_post_product_variants_query_returns_404_for_invalid_product(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/999999/variants/query', []);

        $response->assertNotFound();
    }
}
