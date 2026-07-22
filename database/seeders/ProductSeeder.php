<?php

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Producto con control de existencias: variantes talla x color.
        // Tallas (grupo Adultos): 34=id 2, 36=id 3, 38=id 4. Colores: Blanco=1, Azul marino=2.
        $product = Product::create([
            'id_size_group' => 1,
            'key'           => 'CAM-001',
            'name'          => 'Camisa lino caballero',
            'description'   => 'Camisa 100% lino manga larga',
            'code_bar'      => null,
            'price'         => 800.00,
            'cost'          => 600.00,
            'stock_control' => true,
            'discount'      => 0.00,
            'type_iva'      => 1,
            'rate_iva'      => 16.00,
            'quota_iva'     => null,
            'isr'           => 0.00,
            'imp_esp'       => 0.00,
            'status'        => 'active',
        ]);

        $product->categories()->sync([1, 2]);

        $variants = [
            ['id_size' => 2, 'id_color' => 1, 'sku' => 'CAM-001-34-BLA', 'stock' => 3],
            ['id_size' => 3, 'id_color' => 1, 'sku' => 'CAM-001-36-BLA', 'stock' => 1],
            ['id_size' => 4, 'id_color' => 1, 'sku' => 'CAM-001-38-BLA', 'stock' => 2],
            ['id_size' => 2, 'id_color' => 2, 'sku' => 'CAM-001-34-AZM', 'stock' => 1],
            ['id_size' => 3, 'id_color' => 2, 'sku' => 'CAM-001-36-AZM', 'stock' => 0],
            ['id_size' => 4, 'id_color' => 2, 'sku' => 'CAM-001-38-AZM', 'stock' => 4],
        ];

        foreach ($variants as $data) {
            $variant = ProductVariant::create([
                'id_product' => $product->id,
                'id_size'    => $data['id_size'],
                'id_color'   => $data['id_color'],
                'sku'        => $data['sku'],
                'code_bar'   => null,
                'stock'      => $data['stock'],
                'status'     => 'active',
            ]);

            if ($data['stock'] > 0) {
                InventoryMovement::create([
                    'id_product_variant' => $variant->id,
                    'movement_type'      => 'entry',
                    'quantity'           => $data['stock'],
                    'previous_stock'     => 0,
                    'new_stock'          => $data['stock'],
                    'reference_type'     => 'initial_load',
                    'reference_id'       => null,
                    'notes'              => 'Carga inicial de inventario',
                    'id_user'            => 1,
                ]);
            }
        }

        // Producto sin control de existencias: no requiere grupo de tallas ni variantes.
        $service = Product::create([
            'id_size_group' => null,
            'key'           => 'SERV-001',
            'name'          => 'Ajuste de prenda',
            'description'   => 'Servicio de ajuste, sin inventario',
            'code_bar'      => null,
            'price'         => 150.00,
            'cost'          => 0.00,
            'stock_control' => false,
            'discount'      => 0.00,
            'type_iva'      => 1,
            'rate_iva'      => 16.00,
            'quota_iva'     => null,
            'isr'           => 0.00,
            'imp_esp'       => 0.00,
            'status'        => 'active',
        ]);

        $service->categories()->sync([2]);
    }
}
