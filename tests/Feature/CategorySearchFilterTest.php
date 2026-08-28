<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CategorySearchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function search(string $term): TestResponse
    {
        return $this->withoutMiddleware()->postJson('/api/categories/query', [
            'w'          => [
                ['f' => 'search', 'ao' => 'contains', 'v' => $term, 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);
    }

    private function names(TestResponse $response): array
    {
        return collect($response->json('data.items'))->pluck('name')->all();
    }

    public function test_search_matches_category_name(): void
    {
        // Seeder: 1 "Camisas lino", 2 "Caballero".
        $this->assertSame(['Camisas lino'], $this->names($this->search('Camisas')));
    }

    public function test_search_matches_category_description_only(): void
    {
        // "Prendas para caballero" es sólo la descripción de la categoría 2.
        $this->assertSame(['Caballero'], $this->names($this->search('Prendas')));
    }

    public function test_empty_search_is_ignored(): void
    {
        $baseline = $this->withoutMiddleware()
            ->postJson('/api/categories/query', ['w' => [], 'totalCount' => true])
            ->json('data.totalCount');

        $this->assertSame($baseline, $this->search('   ')->json('data.totalCount'));
    }

    /** El sentinel `in` tambien tiene que funcionar fuera de productos. */
    public function test_in_operator_filters_by_a_list_of_ids(): void
    {
        $extra = Category::create(['name' => 'ZEXTRA', 'description' => 'x', 'status' => 'active']);

        $response = $this->withoutMiddleware()->postJson('/api/categories/query', [
            'w'          => [
                ['f' => 'id', 'ao' => 'in', 'v' => [1, 2], 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();

        $names = $this->names($response);

        $this->assertContains('Camisas lino', $names);
        $this->assertContains('Caballero', $names);
        $this->assertNotContains($extra->name, $names);
    }
}
