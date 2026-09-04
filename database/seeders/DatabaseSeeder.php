<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(app()->environment('production')
            ? ProductionSeeder::class
            : DevelopmentSeeder::class);
    }
}
