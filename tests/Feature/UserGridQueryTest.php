<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGridQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_post_users_query_supports_pagination_and_total_count(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/users/query', [
            'p' => ['page' => 0, 'per_page' => 1],
            'totalCount' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totalCount', User::count())
            ->assertJsonCount(1, 'data.items');
    }

    public function test_post_users_query_search_virtual_field(): void
    {
        $admin = User::first();

        $response = $this->withoutMiddleware()->postJson('/api/users/query', [
            'w' => [
                ['f' => 'search', 'ao' => 'contains', 'v' => $admin->user_name, 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $usernames = collect($response->json('data.items'))->pluck('userName')->all();
        $this->assertContains($admin->user_name, $usernames);
    }

    public function test_post_users_query_filters_by_status(): void
    {
        $user = User::first();
        $user->update(['status' => 'inactive']);

        $response = $this->withoutMiddleware()->postJson('/api/users/query', [
            'w' => [
                ['f' => 'status', 'ao' => '==', 'v' => 'inactive', 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame('inactive', $item['status']);
        }
    }

    public function test_get_users_supports_grid_query_parameters(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/users?p[page]=1&p[per_page]=1&totalCount=true');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totalCount', User::count())
            ->assertJsonCount(1, 'data.items');
    }
}
