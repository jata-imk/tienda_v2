<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserTypeSeeder::class,
            CurrencySeeder::class,
            CompanySeeder::class,
            SizeGroupSeeder::class,
            SizeSeeder::class,
            UserSeeder::class,
        ]);
    }
}
