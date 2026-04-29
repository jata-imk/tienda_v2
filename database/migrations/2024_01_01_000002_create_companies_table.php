<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->string('company_name');
            $table->string('rfc')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('regimen_fiscal')->nullable();
            $table->text('img')->nullable();
            $table->string('street')->nullable();
            $table->string('num_ext')->nullable();
            $table->string('cross_one')->nullable();
            $table->string('cross_two')->nullable();
            $table->string('cp')->nullable();
            $table->string('colony')->nullable();
            $table->string('city')->nullable();
            $table->boolean('stock_control')->default(true);
            $table->tinyInteger('integers_q')->default(9);
            $table->tinyInteger('decimals_q')->default(3);
            $table->timestamp('date_creation')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
