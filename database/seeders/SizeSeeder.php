<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        // Grupo Adultos (id 1): numericas y por letra.
        $adults = ['32', '34', '36', '38', '40', '42', '44', '46', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];
        foreach ($adults as $i => $name) {
            Size::create([
                'id_size_group' => 1,
                'name'          => $name,
                'sort_order'    => ($i + 1) * 10,
                'status'        => 'active',
            ]);
        }

        // Grupo Niños (id 2).
        $kids = ['1', '2', '4', '6', '8', '10', '12', '14', '16'];
        foreach ($kids as $i => $name) {
            Size::create([
                'id_size_group' => 2,
                'name'          => $name,
                'sort_order'    => ($i + 1) * 10,
                'status'        => 'active',
            ]);
        }
    }
}
