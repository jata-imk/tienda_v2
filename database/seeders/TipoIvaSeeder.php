<?php

namespace Database\Seeders;

use App\Models\TipoIva;
use Illuminate\Database\Seeder;

class TipoIvaSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['name' => 'general',          'description' => 'General (base: 16%)'],
            ['name' => 'tasa_producto',    'description' => 'Tasa por producto'],
            ['name' => 'cuota_producto',   'description' => 'Cuota por producto'],
            ['name' => 'no_aplica',        'description' => 'No aplica'],
        ];

        foreach ($tipos as $tipo) {
            TipoIva::create($tipo);
        }
    }
}
