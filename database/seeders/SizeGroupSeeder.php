<?php

namespace Database\Seeders;

use App\Models\SizeGroup;
use Illuminate\Database\Seeder;

class SizeGroupSeeder extends Seeder
{
    public function run(): void
    {
        SizeGroup::firstOrCreate(['name' => 'Adultos'], [
            'description' => 'Tallas de adulto: 32 a 46 y letras',
            'status' => 'active',
        ]);

        SizeGroup::firstOrCreate(['name' => 'Niños'], [
            'description' => 'Tallas infantiles: 1 a 16',
            'status' => 'active',
        ]);
    }
}
