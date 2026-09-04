<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::firstOrCreate(['name' => 'Camisas lino'], [
            'description' => 'Camisas de todos tipos de lino',
            'status' => 'active',
        ]);

        Category::firstOrCreate(['name' => 'Caballero'], [
            'description' => 'Prendas para caballero',
            'status' => 'active',
        ]);
    }
}
