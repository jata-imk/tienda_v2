<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevelopmentSeeder no puede ejecutarse en producción.');
        }

        $this->call([
            ProductionSeeder::class,
            CategorySeeder::class,
            ColorSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
