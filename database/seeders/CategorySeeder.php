<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::firstOrCreate(['name' => 'Camisas lino'], [
            'key' => 'CAM',
            'description' => 'Camisas de todos tipos de lino',
            'status' => 'active',
        ]);

        Category::firstOrCreate(['name' => 'Caballero'], [
            'key' => 'CAB',
            'description' => 'Prendas para caballero',
            'status' => 'active',
        ]);
    }
}
