<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\Size;
use App\Models\SizeGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridConditionPrecedenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_color_or_condition_does_not_leak_inactive_records(): void
    {
        $active = Color::create(['name' => 'ColorActivo', 'hex_color' => '#111111', 'status' => 'active']);
        $inactive = Color::create(['name' => 'ColorInactivo', 'hex_color' => '#222222', 'status' => 'inactive']);

        // status == active AND (name == ColorActivo OR name == ColorInactivo)
        $response = $this->withoutMiddleware()->postJson('/api/colors/query', [
            'w' => [
                ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
                ['f' => 'name', 'ao' => '==', 'v' => 'ColorActivo', 'lo' => '&&'],
                ['f' => 'name', 'ao' => '==', 'v' => 'ColorInactivo', 'lo' => '||'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $names = collect($response->json('data.items'))->pluck('name')->all();

        $this->assertContains('ColorActivo', $names);
        $this->assertNotContains('ColorInactivo', $names);
    }

    public function test_size_or_condition_does_not_leak_inactive_records(): void
    {
        $group = SizeGroup::first();
        $active = Size::create(['id_size_group' => $group->id, 'name' => 'TallaActiva', 'status' => 'active', 'sort_order' => 1]);
        $inactive = Size::create(['id_size_group' => $group->id, 'name' => 'TallaInactiva', 'status' => 'inactive', 'sort_order' => 2]);

        $response = $this->withoutMiddleware()->postJson('/api/sizes/query', [
            'w' => [
                ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
                ['f' => 'name', 'ao' => '==', 'v' => 'TallaActiva', 'lo' => '&&'],
                ['f' => 'name', 'ao' => '==', 'v' => 'TallaInactiva', 'lo' => '||'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $names = collect($response->json('data.items'))->pluck('name')->all();

        $this->assertContains('TallaActiva', $names);
        $this->assertNotContains('TallaInactiva', $names);
    }

    public function test_size_group_or_condition_does_not_leak_inactive_records(): void
    {
        $active = SizeGroup::create(['name' => 'GrupoActivo', 'description' => 'desc', 'status' => 'active']);
        $inactive = SizeGroup::create(['name' => 'GrupoInactivo', 'description' => 'desc', 'status' => 'inactive']);

        $response = $this->withoutMiddleware()->postJson('/api/size-groups/query', [
            'w' => [
                ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
                ['f' => 'name', 'ao' => '==', 'v' => 'GrupoActivo', 'lo' => '&&'],
                ['f' => 'name', 'ao' => '==', 'v' => 'GrupoInactivo', 'lo' => '||'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $names = collect($response->json('data.items'))->pluck('name')->all();

        $this->assertContains('GrupoActivo', $names);
        $this->assertNotContains('GrupoInactivo', $names);
    }

    public function test_color_search_virtual_field(): void
    {
        Color::create(['name' => 'VerdeEsmeralda', 'hex_color' => '#50C878', 'status' => 'active']);

        $response = $this->withoutMiddleware()->postJson('/api/colors/query', [
            'w' => [
                ['f' => 'search', 'ao' => 'contains', 'v' => 'Esmeralda', 'lo' => '&&'],
            ],
        ]);

        $response->assertOk();
        $names = collect($response->json('data.items'))->pluck('name')->all();
        $this->assertContains('VerdeEsmeralda', $names);
    }
}
