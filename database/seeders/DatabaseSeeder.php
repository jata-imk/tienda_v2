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
            CurrencySeeder::class,
            CategorySeeder::class,
            SizeGroupSeeder::class,
            SizeSeeder::class,
            ColorSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
