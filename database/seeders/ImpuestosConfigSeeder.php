<?php

namespace Database\Seeders;

use App\Models\ImpuestosConfig;
use Illuminate\Database\Seeder;

class ImpuestosConfigSeeder extends Seeder
{
    public function run(): void
    {
        ImpuestosConfig::create([
            'iva'     => 16.00,
            'isr'     => 10.00,
            'imp_esp' => 0.00,
        ]);
    }
}
