<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_info', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rfc')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('tax_regime')->nullable();
            $table->text('logo')->nullable();
            $table->string('street')->nullable();
            $table->string('external_number')->nullable();
            $table->string('cross_street_one')->nullable();
            $table->string('cross_street_two')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->boolean('stock_control')->default(true);
            $table->tinyInteger('quantity_integers')->default(9);
            $table->tinyInteger('quantity_decimals')->default(3);
            $table->json('grid_settings')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_info');
    }
};
