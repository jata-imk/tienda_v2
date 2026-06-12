<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'Blanco',      'hex_color' => '#FFFFFF'],
            ['name' => 'Azul marino', 'hex_color' => '#1F2A44'],
            ['name' => 'Beige',       'hex_color' => '#D8C3A5'],
        ];

        foreach ($colors as $color) {
            Color::create([
                'name'      => $color['name'],
                'hex_color' => $color['hex_color'],
                'status'    => 'active',
            ]);
        }
    }
}
