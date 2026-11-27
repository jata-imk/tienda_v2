<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comparar con la collation de la columna, igual que el futuro índice.
        // No cambiar datos: estas tallas pueden tener variantes e inventario.
        $duplicates = DB::table('sizes')->select('id_size_group', 'name')
            ->groupBy('id_size_group', 'name')->havingRaw('COUNT(*) > 1')->get();

        if ($duplicates->isNotEmpty()) {
            $conflicts = $duplicates->map(function ($duplicate) {
                $ids = DB::table('sizes')->where('id_size_group', $duplicate->id_size_group)
                    ->where('name', $duplicate->name)->orderBy('id')->pluck('id')->implode(', ');

                return 'Grupo '.$duplicate->id_size_group.', nombre '
                    .json_encode($duplicate->name, JSON_UNESCAPED_UNICODE).': IDs '.$ids;
            });

            throw new RuntimeException(
                "No se puede crear el índice único de tallas. Resuelve los nombres duplicados por grupo y vuelve a ejecutar la migración:\n"
                .$conflicts->implode("\n")
            );
        }

        Schema::table('sizes', function (Blueprint $table) {
            $table->unique(['id_size_group', 'name'], 'sizes_group_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->dropUnique('sizes_group_name_unique');
        });
    }
};
