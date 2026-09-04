<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // Moneda base: su tipo de cambio siempre es 1.
        Currency::firstOrCreate(['code' => 'MXN'], [
            'name' => 'Pesos Mexicanos',
            'symbol' => '$',
            'exchange_rate' => 1,
            'status' => 'active',
        ]);

        Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'Dólar Estadounidense',
            'symbol' => '$',
            'exchange_rate' => 17.25,
            'status' => 'active',
        ]);
    }
}
