<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `company_info` se crea antes que `currencies`, por eso la FK a la moneda
     * base vive aqui y no en la migracion original.
     */
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->foreignId('id_currency')->nullable()->after('tax_regime')->constrained('currencies');
        });

        $mxnId = DB::table('currencies')->where('code', 'MXN')->value('id');

        if ($mxnId) {
            DB::table('company_info')->whereNull('id_currency')->update(['id_currency' => $mxnId]);
        }
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropForeign(['id_currency']);
            $table->dropColumn('id_currency');
        });
    }
};
