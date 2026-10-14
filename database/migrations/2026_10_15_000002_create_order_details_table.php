<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('id_product_variant')->constrained('product_variants');
            $table->decimal('quantity_ordered', 10, 3);
            $table->decimal('quantity_received', 10, 3)->default(0);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Una variante aparece una sola vez por pedido. El unique tambien
            // sirve de indice para las consultas por `id_order`.
            $table->unique(['id_order', 'id_product_variant']);
            $table->index('id_product_variant');
        });

        // OrderService ya valida el rango; el CHECK lo blinda en la base.
        // SQLite (tests) no soporta `ALTER TABLE ... ADD CONSTRAINT`.
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement(
                'ALTER TABLE `order_details` ADD CONSTRAINT `chk_order_details_quantity_received` '
                .'CHECK (`quantity_received` >= 0 AND `quantity_received` <= `quantity_ordered`)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
