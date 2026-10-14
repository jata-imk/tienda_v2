<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `purchase` es exclusivo de la recepcion de pedidos de compra: los
     * endpoints de movimientos manuales no lo aceptan. El indice compuesto
     * permite rastrear los movimientos de una linea de pedido.
     */
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('movement_type', ['entry', 'sale', 'adjustment', 'return', 'cancel', 'purchase'])->change();
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        // Sin esto, reducir el enum fallaria (MariaDB estricto) o truncaria las
        // filas `purchase`.
        DB::table('inventory_movements')
            ->where('movement_type', 'purchase')
            ->update(['movement_type' => 'entry']);

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->enum('movement_type', ['entry', 'sale', 'adjustment', 'return', 'cancel'])->change();
        });
    }
};
