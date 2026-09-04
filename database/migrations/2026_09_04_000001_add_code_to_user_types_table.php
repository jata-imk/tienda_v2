<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_types', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
        });

        DB::table('user_types')
            ->whereRaw('LOWER(name) IN (?, ?)', ['administrador', 'administrator'])
            ->update(['code' => 'administrator']);

        DB::table('user_types')
            ->whereRaw('LOWER(name) IN (?, ?)', ['vendedor', 'seller'])
            ->update(['code' => 'seller']);

        DB::table('user_types')
            ->whereRaw('LOWER(name) IN (?, ?, ?)', ['almacen', 'almacén', 'warehouse'])
            ->update(['code' => 'warehouse']);

        if (DB::table('user_types')->whereNull('code')->exists()) {
            throw new RuntimeException('Existen tipos de usuario sin un código de rol reconocido.');
        }

        DB::table('user_types')->where('code', 'administrator')->update(['name' => 'Administrador']);
        DB::table('user_types')->where('code', 'seller')->update(['name' => 'Vendedor']);
        DB::table('user_types')->where('code', 'warehouse')->update(['name' => 'Almacén']);

        Schema::table('user_types', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('user_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
