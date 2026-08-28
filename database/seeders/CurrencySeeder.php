<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // Moneda base: su tipo de cambio siempre es 1.
        Currency::create([
            'name'          => 'Pesos Mexicanos',
            'code'          => 'MXN',
            'symbol'        => '$',
            'exchange_rate' => 1,
            'status'        => 'active',
        ]);

        Currency::create([
            'name'          => 'Dólar Estadounidense',
            'code'          => 'USD',
            'symbol'        => '$',
            'exchange_rate' => 17.25,
            'status'        => 'active',
        ]);
    }
}
