<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedidos de compra a proveedor. El folio es consecutivo por `key` y lo
     * genera OrderService; el unique es la red de seguridad ante concurrencia.
     *
     * `exchange_rate` es null mientras el pedido esta en draft (se usa el de
     * `currencies`) y se congela al confirmar.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('key', 10)->default('PED');
            $table->unsignedInteger('folio');
            $table->enum('status', ['draft', 'confirmed', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->foreignId('id_currency')->constrained('currencies');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('total_items', 12, 3)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('supplier_name')->nullable();
            $table->text('supplier_details')->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->date('date_order');
            $table->date('date_expected')->nullable();
            $table->dateTime('date_received')->nullable();
            $table->foreignId('id_user')->constrained('users');
            $table->timestamps();

            $table->unique(['key', 'folio']);
            $table->index('status');
            $table->index('date_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
