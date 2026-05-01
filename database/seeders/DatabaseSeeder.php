<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserTypeSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            TipoIvaSeeder::class,
            ImpuestosConfigSeeder::class,
            TipoMonedaSeeder::class,
        ]);
    }
}
