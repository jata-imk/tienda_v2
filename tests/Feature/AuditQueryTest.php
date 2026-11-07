<?php

namespace Tests\Feature;

use App\Http\Requests\Audit\AuditQueryRequest;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->token = $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'admin'])->assertOk()->json('data.token');
    }

    private function insertEvents(int $count): void
    {
        $rows = [];
        for ($index = 0; $index < $count; $index++) {
            $rows[] = ['request_id' => (string) Str::uuid(), 'module' => 'products', 'action' => 'products.updated',
                'outcome' => 'success', 'actor_id' => 1, 'actor_name' => 'admin', 'role_code' => 'administrator',
                'source' => 'api', 'occurred_at' => '2026-01-01 12:00:00.000000'];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('audit_events')->insert($chunk);
        }
    }

    public function test_list_is_always_paginated_lightweight_and_stable(): void
    {
        $this->insertEvents(128);
        $this->withToken($this->token)->getJson('/api/audit-events')->assertOk()
            ->assertJsonCount(15, 'data.items')->assertJsonPath('data.totalCount', 129)
            ->assertJsonPath('data.perPage', 15)->assertJsonPath('data.hasMore', true)
            ->assertJsonMissingPath('data.items.0.metadata')->assertJsonMissingPath('data.items.0.changes');
        $first = $this->withToken($this->token)->getJson('/api/audit-events?p[page]=2&p[per_page]=10')->assertOk()->json('data.items');
        $second = $this->withToken($this->token)->getJson('/api/audit-events?p[page]=3&p[per_page]=10')->assertOk()->json('data.items');
        $ids = [...array_column($first, 'id'), ...array_column($second, 'id')];
        $this->assertCount(20, array_unique($ids));
        $expected = DB::table('audit_events')->orderByDesc('occurred_at')->orderByDesc('id')->offset(10)->limit(20)->pluck('id')->all();
        $this->assertSame($expected, $ids);
        $post = $this->withToken($this->token)->postJson('/api/audit-events/query', ['p' => ['page' => 10, 'per_page' => 10]])->assertOk();
        $this->assertSame($first, $post->json('data.items'));
        $this->withToken($this->token)->postJson('/api/audit-events/query', [])->assertOk()->assertJsonCount(15, 'data.items');
    }

    public function test_grid_filters_projection_dates_and_total_count_false_are_supported(): void
    {
        $this->insertEvents(25);
        $counts = [];
        DB::listen(function ($query) use (&$counts) {
            if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, 'audit_events')) {
                $counts[] = $query->sql;
            }
        });
        $this->withToken($this->token)->postJson('/api/audit-events/query', [
            'p' => ['r' => 0, 's' => 5], 'f' => ['id', 'action'],
            'w' => [['f' => 'module', 'ao' => '==', 'v' => 'products', 'lo' => '&&']],
            'dateFrom' => '2026-01-01', 'dateTo' => '2026-01-01', 'totalCount' => false,
        ])->assertOk()->assertJsonCount(5, 'data.items')->assertJsonMissingPath('data.totalCount')
            ->assertJsonMissingPath('data.items.0.actorName')->assertJsonPath('data.hasMore', true);
        $this->assertSame([], $counts);
        $this->withToken($this->token)->postJson('/api/audit-events/query', [
            'w' => [['f' => 'occurredAt', 'ao' => '==', 'v' => '2026-01-01T06:00:00-06:00']],
        ])->assertOk()->assertJsonPath('data.totalCount', 25);
        $this->withToken($this->token)->postJson('/api/audit-events/query', [
            'w' => ['module' => 'products'], 'dateFrom' => '2026-01-02',
        ])->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_shared_grid_payload_is_accepted_like_other_grids(): void
    {
        $this->insertEvents(25);
        $query = fn (array $payload) => $this->withToken($this->token)->postJson('/api/audit-events/query', $payload);

        // Payload exacto del grid compartido (el mismo que se envía a /users/query).
        $query(['p' => ['r' => 0, 's' => 10], 'f' => [], 'o' => ['column' => 'occurredAt', 'direction' => 'desc'], 'w' => [], 'totalCount' => true])
            ->assertOk()->assertJsonCount(10, 'data.items')->assertJsonPath('data.perPage', 10)
            ->assertJsonPath('data.totalCount', 26)->assertJsonPath('data.summary', [26])
            ->assertJsonStructure(['data' => ['items' => [AuditQueryRequest::EVENT_FIELDS]]]);

        // null equivale a omitir; direction/lo sin distinguir mayúsculas; llaves extra ignoradas.
        $query(['p' => null, 'f' => null, 'o' => null, 'w' => null, 'dateFrom' => null, 'entityId' => null])
            ->assertOk()->assertJsonCount(15, 'data.items')->assertJsonPath('data.totalCount', 26);
        $query(['p' => ['r' => 0, 's' => 5, 'extra' => 1], 'o' => ['column' => 'id', 'direction' => 'DESC', 'extra' => 1]])
            ->assertOk()->assertJsonPath('data.items.0.id', DB::table('audit_events')->max('id'));
        $query(['w' => [['f' => 'module', 'ao' => '==', 'v' => 'products', 'lo' => 'AND', 'extra' => 'x']]])
            ->assertOk()->assertJsonPath('data.totalCount', 25);

        // Operadores con el mismo significado que mapGridOperator().
        $query(['w' => [['f' => 'module', 'ao' => 'anyof', 'v' => ['products']]]])->assertOk()->assertJsonPath('data.totalCount', 25);
        $query(['w' => [['f' => 'module', 'ao' => 'noneof', 'v' => ['products']]]])->assertOk()->assertJsonPath('data.totalCount', 1);
        $query(['w' => [['f' => 'module', 'ao' => 'unknown', 'v' => 'products']]])->assertOk()->assertJsonPath('data.totalCount', 25);

        // totalCount no booleano se interpreta como false, igual que en los demás grids.
        $query(['totalCount' => 'abc'])->assertOk()->assertJsonMissingPath('data.totalCount');

        // dateTo sin dateFrom es válido.
        $query(['dateTo' => '2026-01-01'])->assertOk()->assertJsonPath('data.totalCount', 25);

        $id = DB::table('audit_events')->min('id');
        $this->withToken($this->token)->getJson("/api/audit-events/$id/changes?f=&w=&o[direction]=DESC&p[page]=1&p[per_page]=10")->assertOk();
    }

    public function test_changes_have_separate_mandatory_pagination_and_detail_contains_metadata(): void
    {
        $this->insertEvents(1);
        $id = DB::table('audit_events')->max('id');
        for ($index = 1; $index <= 33; $index++) {
            DB::table('audit_changes')->insert(['audit_event_id' => $id, 'entity_type' => 'product_variant', 'entity_id' => (string) $index,
                'operation' => 'updated', 'before_values' => '{"stock":"1.000"}', 'after_values' => '{"stock":"2.000"}']);
        }
        $this->withToken($this->token)->getJson("/api/audit-events/$id")->assertOk()->assertJsonStructure(['data' => ['metadata', 'occurredAt']])->assertJsonMissingPath('data.changes');
        $this->withToken($this->token)->getJson("/api/audit-events/$id/changes")->assertOk()
            ->assertJsonCount(15, 'data.items')->assertJsonPath('data.totalCount', 33)
            ->assertJsonPath('data.items.0.beforeValues.stock', '1.000')->assertJsonPath('data.items.0.afterValues.stock', '2.000');
        $this->withToken($this->token)->getJson("/api/audit-events/$id/changes?p[page]=3")->assertOk()->assertJsonCount(3, 'data.items');
        $this->withToken($this->token)->getJson('/api/audit-events/999999')->assertNotFound();
        $this->withToken($this->token)->getJson('/api/audit-events/999999/changes')->assertNotFound();
    }

    public function test_entity_filters_match_the_same_affected_record(): void
    {
        $this->insertEvents(1);
        $id = DB::table('audit_events')->max('id');
        foreach (['product' => '5', 'product_variant' => '2'] as $entity => $entityId) {
            DB::table('audit_changes')->insert(['audit_event_id' => $id, 'entity_type' => $entity, 'entity_id' => $entityId,
                'operation' => 'updated', 'before_values' => '{}', 'after_values' => '{}']);
        }
        $this->withToken($this->token)->getJson('/api/audit-events?entityType=product&entityId=5')->assertOk()->assertJsonPath('data.totalCount', 1);
        $this->withToken($this->token)->getJson('/api/audit-events?entityType=product&entityId=2')->assertOk()->assertJsonPath('data.totalCount', 0);
    }

    public function test_pagination_and_query_fields_are_strictly_validated(): void
    {
        foreach ([
            ['p' => ['per_page' => 101]], ['p' => ['s' => 0]], ['p' => ['r' => -1]],
            ['f' => ['password']], ['o' => ['column' => 'metadata']], ['o' => ['direction' => 'unsafe']],
            ['f' => ['metadata']], ['f' => ['beforeValues']], ['w' => ['token_hash' => 'secret']],
            ['w' => [['f' => 'metadata', 'ao' => '==', 'v' => 'x']]], ['w' => [['f' => 'action', 'ao' => ['raw'], 'v' => 'x']]],
            ['w' => [['f' => 'action', 'ao' => 'between', 'v' => []]]], ['w' => [['f' => 'action', 'ao' => 'in', 'v' => []]]],
            ['p' => ['s' => 1000000]], ['dateFrom' => 'xyz'], ['dateFrom' => '2026-02-01', 'dateTo' => '2026-01-01'],
            ['w' => ['occurredAt' => 'invalid-date']],
            ['w' => [['f' => 'occurredAt', 'ao' => 'contains', 'v' => '2026-01-01']]],
        ] as $payload) {
            $this->withToken($this->token)->postJson('/api/audit-events/query', $payload)->assertUnprocessable();
        }
    }

    public function test_only_administrators_can_consult_audit_and_denials_are_logged(): void
    {
        $this->getJson('/api/audit-events')->assertUnauthorized();
        $this->assertDatabaseHas('audit_events', ['action' => 'security.unauthenticated', 'outcome' => 'rejected', 'actor_id' => null]);
        $seller = User::create(['id_user_type' => UserType::where('code', 'seller')->value('id'), 'first_name' => 'Seller', 'last_name' => 'Test',
            'user_name' => 'seller-audit', 'email' => 'seller-audit@example.com', 'password' => 'password123', 'status' => 'active']);
        $token = $this->postJson('/api/login', ['userName' => 'seller-audit', 'password' => 'password123'])->assertOk()->json('data.token');
        foreach (['/api/audit-events', '/api/audit-events/1', '/api/audit-events/1/changes'] as $url) {
            $this->withToken($token)->getJson($url)->assertForbidden();
        }
        $this->withToken($token)->postJson('/api/audit-events/query', [])->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['action' => 'security.access_denied', 'actor_id' => $seller->id, 'role_code' => 'seller']);
    }

    public function test_request_id_correlates_response_and_event(): void
    {
        $response = $this->withToken($this->token)->putJson('/api/colors/1', ['name' => 'Audited color'])->assertOk();
        $this->assertDatabaseHas('audit_events', ['request_id' => $response->headers->get('X-Request-Id'), 'action' => 'colors.updated', 'actor_id' => 1]);
    }

    public function test_frontend_can_read_request_id_across_origins(): void
    {
        $this->withToken($this->token)->withHeader('Origin', 'https://frontend.example.com')
            ->getJson('/api/audit-events')->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertHeader('Access-Control-Expose-Headers', 'X-Request-Id');
    }
}
