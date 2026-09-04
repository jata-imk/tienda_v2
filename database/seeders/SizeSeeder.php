<?php

namespace Database\Seeders;

use App\Models\Size;
use App\Models\SizeGroup;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizesByGroup = [
            'Adultos' => ['32', '34', '36', '38', '40', '42', '44', '46', 'XS', 'S', 'M', 'L', 'XL', 'XXL'],
            'Niños' => ['1', '2', '4', '6', '8', '10', '12', '14', '16'],
        ];

        foreach ($sizesByGroup as $groupName => $sizes) {
            $group = SizeGroup::where('name', $groupName)->firstOrFail();

            foreach ($sizes as $index => $name) {
                Size::firstOrCreate([
                    'id_size_group' => $group->id,
                    'name' => $name,
                ], [
                    'sort_order' => ($index + 1) * 10,
                    'status' => 'active',
                ]);
            }
        }
    }
}
