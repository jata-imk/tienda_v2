<?php

namespace Tests\Feature;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyGridQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_post_currencies_query_supports_pagination_and_total_count(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/currencies/query', [
            'p' => ['page' => 0, 'per_page' => 1],
            'totalCount' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totalCount', Currency::count())
            ->assertJsonCount(1, 'data.items');
    }

    public function test_post_currencies_query_search_virtual_field(): void
    {
        $currency = Currency::first();

        $response = $this->withoutMiddleware()->postJson('/api/currencies/query', [
            'w' => [
                ['f' => 'search', 'ao' => 'contains', 'v' => $currency->code, 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $codes = collect($response->json('data.items'))->pluck('code')->all();
        $this->assertContains($currency->code, $codes);
    }

    public function test_get_currencies_supports_grid_query_parameters(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/currencies?p[page]=1&p[per_page]=1&totalCount=true');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totalCount', Currency::count())
            ->assertJsonCount(1, 'data.items');
    }
}
