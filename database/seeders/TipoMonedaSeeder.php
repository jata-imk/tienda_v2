<?php

namespace Database\Seeders;

use App\Models\TipoMoneda;
use Illuminate\Database\Seeder;

class TipoMonedaSeeder extends Seeder
{
    public function run(): void
    {
        TipoMoneda::create([
            'name'   => 'Pesos Mexicanos',
            'code'   => 'MXN',
            'symbol' => '$',
            'status' => 'activo',
        ]);
    }
}
