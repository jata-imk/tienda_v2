<?php

namespace Tests\Feature;

use App\Models\Size;
use App\Models\SizeGroup;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SizeOrderingTest extends TestCase
{
    use RefreshDatabase;

    private array $sizes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        // IDs deliberadamente opuestos al orden alfabético de los grupos.
        $kids = SizeGroup::create(['name' => 'Niños']);
        $adults = SizeGroup::create(['name' => 'Adultos']);
        $this->sizes = [
            'kidM' => Size::create(['id_size_group' => $kids->id, 'name' => 'M', 'sort_order' => 10]),
            'adultS' => Size::create(['id_size_group' => $adults->id, 'name' => 'S', 'sort_order' => 10]),
            'adultM' => Size::create(['id_size_group' => $adults->id, 'name' => 'M', 'sort_order' => 20, 'status' => 'inactive']),
            'kidS' => Size::create(['id_size_group' => $kids->id, 'name' => 'S', 'sort_order' => 20, 'status' => 'inactive']),
            'adultL' => Size::create(['id_size_group' => $adults->id, 'name' => 'L', 'sort_order' => 10]),
        ];
    }

    public static function orderings(): array
    {
        return [
            'default' => [null, ['adultS', 'adultL', 'adultM', 'kidM', 'kidS']],
            'name asc' => [['column' => 'name', 'direction' => 'asc'], ['adultL', 'adultM', 'adultS', 'kidM', 'kidS']],
            'name desc' => [['column' => 'name', 'direction' => 'desc'], ['adultS', 'adultM', 'adultL', 'kidS', 'kidM']],
            'order desc' => [['column' => 'sortOrder', 'direction' => 'desc'], ['adultM', 'adultS', 'adultL', 'kidS', 'kidM']],
            'status asc' => [['column' => 'status', 'direction' => 'asc'], ['adultS', 'adultL', 'adultM', 'kidM', 'kidS']],
            'id desc' => [['column' => 'id', 'direction' => 'desc'], ['adultL', 'adultM', 'adultS', 'kidS', 'kidM']],
            'group id desc' => [['column' => 'idSizeGroup', 'direction' => 'desc'], ['adultS', 'adultL', 'adultM', 'kidM', 'kidS']],
            'legacy format' => [['field' => 'name', 'type' => 'desc'], ['adultS', 'adultM', 'adultL', 'kidS', 'kidM']],
        ];
    }

    #[DataProvider('orderings')]
    public function test_get_and_post_keep_groups_fixed_when_the_selected_column_changes(?array $order, array $expected): void
    {
        $payload = $order === null ? [] : ['o' => $order];
        $expectedIds = array_map(fn ($key) => $this->sizes[$key]->id, $expected);
        $post = $this->postJson('/api/sizes/query', $payload)->assertOk();
        $get = $this->getJson('/api/sizes?'.http_build_query($payload))->assertOk();

        $this->assertSame($expectedIds, array_column($post->json('data.items'), 'id'));
        $this->assertSame($post->json('data'), $get->json('data'));
        $post->assertJsonPath('data.totalCount', 5);
    }

    public function test_ordering_is_applied_before_pagination_with_stable_ties(): void
    {
        $ids = [];
        for ($offset = 0; $offset < 5; $offset += 2) {
            $response = $this->postJson('/api/sizes/query', [
                'p' => ['r' => $offset, 's' => 2],
                'o' => ['column' => 'status', 'direction' => 'asc'],
            ])->assertOk()->assertJsonPath('data.totalCount', 5);
            array_push($ids, ...array_column($response->json('data.items'), 'id'));
        }

        $this->assertSame(array_map(fn ($key) => $this->sizes[$key]->id,
            ['adultS', 'adultL', 'adultM', 'kidM', 'kidS']), $ids);
    }

    public function test_filters_search_and_field_selection_still_work_without_ambiguous_columns(): void
    {
        $this->postJson('/api/sizes/query', [
            'w' => ['id' => $this->sizes['adultS']->id, 'status' => 'active'],
            'f' => ['id', 'idSizeGroup', 'name', 'sortOrder', 'status'],
        ])->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.name', 'S');

        $response = $this->postJson('/api/sizes/query', [
            'w' => [
                ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
                ['f' => 'search', 'ao' => 'contains', 'v' => 'Adultos', 'lo' => '&&'],
            ],
            'totalCount' => false,
        ])->assertOk()->assertJsonMissingPath('data.totalCount');

        $this->assertSame([$this->sizes['adultS']->id, $this->sizes['adultL']->id],
            array_column($response->json('data.items'), 'id'));
    }

    public function test_groups_with_identical_names_remain_separate(): void
    {
        $group = SizeGroup::create(['name' => 'Adultos']);
        $size = Size::create(['id_size_group' => $group->id, 'name' => 'A', 'sort_order' => 0]);
        $response = $this->postJson('/api/sizes/query', ['o' => ['column' => 'name', 'direction' => 'asc']])->assertOk();

        $this->assertSame(array_map(fn ($key) => $this->sizes[$key]->id, ['adultL', 'adultM', 'adultS']),
            array_slice(array_column($response->json('data.items'), 'id'), 0, 3));
        $response->assertJsonPath('data.items.3.id', $size->id);
    }

    public function test_catalogs_return_the_same_default_order_and_group_names(): void
    {
        $sizes = $this->getJson('/api/sizes')->assertOk()->json('data.items');
        $catalogs = $this->getJson('/api/catalogs')->assertOk()->json('data.sizes');

        $this->assertSame($sizes, $catalogs);
        $this->assertSame(['Adultos', 'Adultos', 'Adultos', 'Niños', 'Niños'], array_column($catalogs, 'sizeGroup'));
    }

    public function test_invalid_orderings_still_return_validation_errors(): void
    {
        $this->postJson('/api/sizes/query', ['o' => ['column' => 'unknown', 'direction' => 'asc']])
            ->assertUnprocessable()->assertJsonValidationErrors('o.column');
        $this->getJson('/api/sizes?'.http_build_query(['o' => ['column' => 'name', 'direction' => 'invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('o.direction');
    }

    public function test_login_catalogs_match_the_default_size_listing(): void
    {
        $this->seed(ProductionSeeder::class);
        $sizes = $this->getJson('/api/sizes')->assertOk()->json('data.items');
        $login = $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'admin'])->assertOk();

        $this->assertSame($sizes, $login->json('data.catalogs.sizes'));
    }
}
