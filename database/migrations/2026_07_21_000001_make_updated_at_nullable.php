<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas con timestamps gestionados por Eloquent. `inventory_movements`
     * queda fuera: solo tiene `created_at`.
     */
    private array $tables = [
        'user_types', 'company_info', 'users', 'user_sessions', 'currencies',
        'categories', 'size_groups', 'sizes', 'colors', 'products',
        'product_variants',
    ];

    public function up(): void
    {
        // Sentencia especifica de MySQL/MariaDB; en sqlite (tests) las columnas
        // ya se crean como TIMESTAMP NULL por `timestamps()`.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'updated_at')) {
                continue;
            }

            // MariaDB puede haber agregado DEFAULT CURRENT_TIMESTAMP ON UPDATE
            // de forma implicita en la primera columna TIMESTAMP de la tabla.
            DB::statement("ALTER TABLE `{$table}` MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        // La definicion previa ya era TIMESTAMP NULL; no hay nada que revertir.
    }
};
