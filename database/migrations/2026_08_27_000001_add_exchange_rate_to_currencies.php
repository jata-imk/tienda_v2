<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de cambio manual: cuantas unidades de la moneda base equivalen a 1
     * unidad de esta moneda. La moneda base vale 1.
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('symbol');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
