<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'id_category'   => 1,
            'key'           => '000001',
            'name'          => 'Guayabera blanca',
            'description'   => 'Guayabera caballero 100% lino',
            'code_bar'      => '16546546',
            'size'          => 'S',
            'price'         => 800.00,
            'cost'          => 600.00,
            'stock_control' => true,
            'stock'         => 20.000,
            'discount'      => 0.00,
            'type_iva'      => 1,
            'rate_iva'      => 16.00,
            'quota_iva'     => null,
            'isr'           => 0.00,
            'imp_esp'       => 0.00,
            'status'        => 'active',
        ]);

        Product::create([
            'id_category'   => 1,
            'key'           => '000002',
            'name'          => 'Filipina',
            'description'   => 'Filipina caballero manga larga',
            'code_bar'      => '65464677',
            'size'          => 'M',
            'price'         => 600.00,
            'cost'          => 500.00,
            'stock_control' => false,
            'stock'         => 10.000,
            'discount'      => 0.00,
            'type_iva'      => 3,
            'rate_iva'      => null,
            'quota_iva'     => 50.00,
            'isr'           => 0.00,
            'imp_esp'       => 0.00,
            'status'        => 'active',
        ]);
    }
}
