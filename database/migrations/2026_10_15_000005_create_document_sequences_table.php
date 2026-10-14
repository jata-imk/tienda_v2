<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ultimo folio emitido por serie. Sacar el siguiente folio con
     * `MAX(folio) ... FOR UPDATE` sobre `orders` provocaba deadlocks en MariaDB
     * con altas concurrentes (gap locks compatibles entre si + INSERT); bloquear
     * una fila que ya existe serializa las altas sin ese problema. La fila se
     * crea aqui y no al vuelo: un INSERT IGNORE concurrente reintroduciria el
     * deadlock (dos locks S que luego piden X).
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('key', 10)->primary();
            $table->unsignedInteger('last_folio')->default(0);
        });

        DB::table('document_sequences')->insert([
            'key'        => 'PED',
            'last_folio' => (int) DB::table('orders')->where('key', 'PED')->max('folio'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
