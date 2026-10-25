<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clave corta de categoria. Datos del seeder: `CAM` (Camisas lino) y `CAB`
 * (Caballero).
 */
class CategoryKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_store_generates_the_key_from_the_first_three_letters(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/categories', ['name' => 'Guayabera'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'GUA');
    }

    public function test_generated_key_ignores_accents_digits_and_symbols(): void
    {
        $this->assertSame('LIN', Category::create(['name' => '100% Lino'])->key);
        $this->assertSame('NIN', Category::create(['name' => 'Niños'])->key);
    }

    public function test_collisions_fall_back_to_word_initials_and_then_a_number(): void
    {
        $this->assertSame('MAN', Category::create(['name' => 'Manga corta'])->key);
        $this->assertSame('MLA', Category::create(['name' => 'Manga larga'])->key);
        $this->assertSame('MAN2', Category::create(['name' => 'Mantel'])->key);
        $this->assertSame('CAM2', Category::create(['name' => 'Camisetas'])->key);
    }

    public function test_word_initials_are_cut_to_three_letters_for_long_names(): void
    {
        Category::create(['name' => 'Ropa infantil']);

        $this->assertSame('RDP', Category::create(['name' => 'Ropa de playa niño'])->key);

        // 12 palabras: antes las iniciales median 11 y no cabian en la columna.
        $this->assertSame('RDB', Category::create(['name' => 'Ropa de bano para nino y nina de playa mar sol arena'])->key);

        // Base e iniciales tomadas: sufijo numerico sobre la base.
        $this->assertSame('ROP2', Category::create(['name' => 'Ropa de bikini'])->key);
    }

    public function test_store_accepts_a_manual_key_in_uppercase(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/categories', ['name' => 'Vestidos', 'key' => 'vest'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'VEST');
    }

    public function test_store_rejects_a_taken_or_non_alphanumeric_key(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/categories', ['name' => 'Otra camisa', 'key' => 'cam'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        $this->withoutMiddleware()
            ->postJson('/api/categories', ['name' => 'Otra', 'key' => 'A-B'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_update_changes_the_key_and_allows_keeping_its_own(): void
    {
        $this->withoutMiddleware()
            ->putJson('/api/categories/2', ['key' => 'CAB', 'name' => 'Caballero'])
            ->assertOk()
            ->assertJsonPath('data.key', 'CAB');

        $this->withoutMiddleware()
            ->putJson('/api/categories/2', ['key' => 'cba'])
            ->assertOk()
            ->assertJsonPath('data.key', 'CBA');
    }

    public function test_size_group_exposes_its_key(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/size-groups/2')
            ->assertOk()
            ->assertJsonPath('data.key', 'NIN');

        $this->withoutMiddleware()
            ->postJson('/api/size-groups', ['name' => 'Dama', 'key' => 'dam'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'DAM');
    }

    public function test_size_group_key_can_be_cleared_and_is_kept_when_omitted(): void
    {
        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['name' => 'Niños'])
            ->assertOk()
            ->assertJsonPath('data.key', 'NIN');

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['key' => null])
            ->assertOk()
            ->assertJsonPath('data.key', null);

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['key' => 'nin'])
            ->assertOk()
            ->assertJsonPath('data.key', 'NIN');
    }
}
