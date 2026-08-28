<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserTypeSeeder::class,
            // CurrencySeeder va antes: CompanySeeder referencia la moneda base.
            CurrencySeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            SizeGroupSeeder::class,
            SizeSeeder::class,
            ColorSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
