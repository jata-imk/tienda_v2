<?php

namespace Tests\Feature;

use App\Models\SizeGroup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SizeNameMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // DDL sin transacción y sin revertir migraciones anteriores del proyecto.
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_27_000001_add_unique_name_per_group_to_sizes.php');
    }

    public function test_migration_reports_conflicting_ids_without_modifying_data_or_schema(): void
    {
        $this->migration()->down();
        $group = SizeGroup::create(['name' => 'Adultos']);
        $first = DB::table('sizes')->insertGetId(['id_size_group' => $group->id, 'name' => 'M']);
        $second = DB::table('sizes')->insertGetId(['id_size_group' => $group->id, 'name' => 'M', 'status' => 'inactive']);
        $before = DB::table('sizes')->orderBy('id')->get()->toJson();

        try {
            $this->migration()->up();
            $this->fail('La migración debe detenerse ante duplicados.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Grupo '.$group->id.', nombre "M": IDs '.$first.', '.$second, $exception->getMessage());
        }

        $this->assertSame($before, DB::table('sizes')->orderBy('id')->get()->toJson());
        $this->assertFalse(collect(Schema::getIndexes('sizes'))->contains('name', 'sizes_group_name_unique'));

        // La corrección es explícita; luego se puede reintentar la misma migración.
        DB::table('sizes')->where('id', $second)->update(['name' => 'L']);
        $this->migration()->up();
        $this->assertTrue(collect(Schema::getIndexes('sizes'))->contains('name', 'sizes_group_name_unique'));
    }

    public function test_database_constraint_allows_other_groups_but_reserves_inactive_names(): void
    {
        $first = SizeGroup::create(['name' => 'Adultos']);
        $second = SizeGroup::create(['name' => 'Niños']);
        DB::table('sizes')->insert([
            ['id_size_group' => $first->id, 'name' => 'M', 'status' => 'inactive'],
            ['id_size_group' => $second->id, 'name' => 'M', 'status' => 'active'],
        ]);
        $this->assertDatabaseCount('sizes', 2);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('sizes')->insert(['id_size_group' => $first->id, 'name' => 'M']);
    }

    public function test_mariadb_preflight_uses_the_same_case_insensitive_comparison_as_the_index(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('La collation de producción se comprueba en MariaDB.');
        }

        $this->migration()->down();
        $group = SizeGroup::create(['name' => 'Adultos']);
        DB::table('sizes')->insert([
            ['id_size_group' => $group->id, 'name' => 'M'],
            ['id_size_group' => $group->id, 'name' => 'm'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IDs 1, 2');
        try {
            $this->migration()->up();
        } finally {
            DB::table('sizes')->where('id', 2)->delete();
            $this->migration()->up();
        }
    }
}
