<?php

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RequireRole;
use App\Models\Size;
use App\Models\SizeGroup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SizeNameUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private SizeGroup $group;

    private SizeGroup $otherGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = SizeGroup::create(['name' => 'Adultos']);
        $this->otherGroup = SizeGroup::create(['name' => 'Niños']);
        $this->withoutMiddleware([JwtAuthenticate::class, RequireRole::class]);
    }

    public static function statuses(): array
    {
        return [['active'], ['inactive']];
    }

    #[DataProvider('statuses')]
    public function test_creation_rejects_a_name_reserved_by_an_active_or_inactive_size(string $status): void
    {
        Size::create(['id_size_group' => $this->group->id, 'name' => 'M', 'status' => $status]);

        $this->postJson('/api/sizes', ['idSizeGroup' => $this->group->id, 'name' => '  M  '])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('sizes', 1);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseMissing('audit_events', ['action' => 'sizes.created', 'outcome' => 'success']);
    }

    public function test_the_same_name_can_be_created_in_another_group(): void
    {
        Size::create(['id_size_group' => $this->group->id, 'name' => 'M']);

        $this->postJson('/api/sizes', ['idSizeGroup' => $this->otherGroup->id, 'name' => 'M'])
            ->assertCreated()->assertJsonPath('data.idSizeGroup', $this->otherGroup->id);

        $this->assertDatabaseCount('sizes', 2);
    }

    public function test_editing_a_size_can_keep_its_own_name_and_change_its_order(): void
    {
        $size = Size::create(['id_size_group' => $this->group->id, 'name' => 'M']);

        $this->putJson('/api/sizes/'.$size->id, ['name' => 'M', 'sortOrder' => 70])
            ->assertOk()->assertJsonPath('data.name', 'M')->assertJsonPath('data.sortOrder', 70);
        $this->putJson('/api/sizes/'.$size->id, ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    #[DataProvider('statuses')]
    public function test_renaming_rejects_a_name_reserved_in_the_same_group(string $status): void
    {
        Size::create(['id_size_group' => $this->group->id, 'name' => 'M', 'status' => $status]);
        $size = Size::create(['id_size_group' => $this->group->id, 'name' => 'L']);

        $this->putJson('/api/sizes/'.$size->id, ['name' => 'M', 'sortOrder' => 90])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame('L', $size->fresh()->name);
        $this->assertSame(0, $size->fresh()->sort_order);
    }

    public function test_changing_only_the_group_checks_the_existing_name_in_the_destination(): void
    {
        Size::create(['id_size_group' => $this->otherGroup->id, 'name' => 'M', 'status' => 'inactive']);
        $size = Size::create(['id_size_group' => $this->group->id, 'name' => 'M']);

        $this->putJson('/api/sizes/'.$size->id, ['idSizeGroup' => $this->otherGroup->id])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame($this->group->id, (int) $size->fresh()->id_size_group);
        $this->putJson('/api/sizes/'.$size->id, ['idSizeGroup' => $this->otherGroup->id, 'name' => 'L'])
            ->assertOk()->assertJsonPath('data.idSizeGroup', $this->otherGroup->id)->assertJsonPath('data.name', 'L');
    }

    public function test_changing_only_the_group_is_allowed_when_the_name_is_available(): void
    {
        $size = Size::create(['id_size_group' => $this->group->id, 'name' => 'M']);

        $this->putJson('/api/sizes/'.$size->id, ['idSizeGroup' => $this->otherGroup->id])
            ->assertOk()->assertJsonPath('data.name', 'M')->assertJsonPath('data.idSizeGroup', $this->otherGroup->id);
        $this->putJson('/api/sizes/99999', ['name' => 'M'])->assertNotFound();
    }

    public function test_a_name_taken_after_validation_returns_422_and_rolls_back_creation(): void
    {
        // Insertar después del exists() reproduce el conflicto del índice al guardar.
        Size::creating(function (Size $size) {
            DB::table('sizes')->insert(['id_size_group' => $size->id_size_group, 'name' => $size->name]);
        });

        $this->postJson('/api/sizes', ['idSizeGroup' => $this->group->id, 'name' => 'RACE'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('sizes', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_a_name_taken_after_validation_returns_422_and_rolls_back_update(): void
    {
        $size = Size::create(['id_size_group' => $this->group->id, 'name' => 'M']);
        Size::updating(function (Size $updating) {
            DB::table('sizes')->insert(['id_size_group' => $updating->id_size_group, 'name' => $updating->name]);
        });

        $this->putJson('/api/sizes/'.$size->id, ['name' => 'RACE'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame('M', $size->fresh()->name);
        $this->assertDatabaseCount('sizes', 1);
    }

    public function test_other_unique_constraints_are_not_reported_as_duplicate_size_names(): void
    {
        Size::creating(function () {
            DB::table('size_groups')->insert(['id' => $this->group->id, 'name' => 'Otro']);
        });

        $this->withoutExceptionHandling();
        $this->expectException(UniqueConstraintViolationException::class);
        $this->postJson('/api/sizes', ['idSizeGroup' => $this->group->id, 'name' => 'M']);
    }

    public function test_mariadb_collation_also_reserves_names_with_different_case(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('La collation de producción se comprueba en MariaDB.');
        }

        Size::create(['id_size_group' => $this->group->id, 'name' => 'M']);
        $this->postJson('/api/sizes', ['idSizeGroup' => $this->group->id, 'name' => 'm'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
