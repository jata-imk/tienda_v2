<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // TEXT solo admite 64 KB: un logo en base64 se trunca. LONGTEXT no.
        // sqlite (tests) no distingue tamanos de texto.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `company_info` MODIFY `logo` LONGTEXT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `company_info` MODIFY `logo` TEXT NULL');
    }
};
