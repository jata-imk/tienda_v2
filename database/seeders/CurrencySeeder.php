<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::create([
            'name'   => 'Pesos Mexicanos',
            'code'   => 'MXN',
            'symbol' => '$',
            'status' => 'active',
        ]);

        Currency::create([
            'name'   => 'Dólar Estadounidense',
            'code'   => 'USD',
            'symbol' => '$',
            'status' => 'active',
        ]);
    }
}
