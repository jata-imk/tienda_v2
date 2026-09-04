<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\SizeGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $administrator = User::whereHas(
            'userType',
            fn ($query) => $query->where('code', UserRole::Administrator->value),
        )->orderBy('id')->firstOrFail();

        $adultGroup = SizeGroup::where('name', 'Adultos')->firstOrFail();
        $categories = Category::whereIn('name', ['Camisas lino', 'Caballero'])->pluck('id', 'name');
        $colors = Color::whereIn('name', ['Blanco', 'Azul marino'])->pluck('id', 'name');
        $sizes = Size::where('id_size_group', $adultGroup->id)
            ->whereIn('name', ['34', '36', '38'])
            ->pluck('id', 'name');

        $product = Product::firstOrCreate(['key' => 'CAM-001'], [
            'id_size_group' => $adultGroup->id,
            'name' => 'Camisa lino caballero',
            'description' => 'Camisa 100% lino manga larga',
            'code_bar' => null,
            'price' => 800.00,
            'cost' => 600.00,
            'stock_control' => true,
            'discount' => 0.00,
            'type_iva' => 1,
            'rate_iva' => 16.00,
            'quota_iva' => null,
            'isr' => 0.00,
            'imp_esp' => 0.00,
            'status' => 'active',
        ]);

        $product->categories()->syncWithoutDetaching($categories->values()->all());

        $variants = [
            ['size' => '34', 'color' => 'Blanco', 'sku' => 'CAM-001-34-BLA', 'stock' => 3],
            ['size' => '36', 'color' => 'Blanco', 'sku' => 'CAM-001-36-BLA', 'stock' => 1],
            ['size' => '38', 'color' => 'Blanco', 'sku' => 'CAM-001-38-BLA', 'stock' => 2],
            ['size' => '34', 'color' => 'Azul marino', 'sku' => 'CAM-001-34-AZM', 'stock' => 1],
            ['size' => '36', 'color' => 'Azul marino', 'sku' => 'CAM-001-36-AZM', 'stock' => 0],
            ['size' => '38', 'color' => 'Azul marino', 'sku' => 'CAM-001-38-AZM', 'stock' => 4],
        ];

        foreach ($variants as $data) {
            $variant = ProductVariant::firstOrCreate([
                'id_product' => $product->id,
                'id_size' => $sizes->get($data['size']),
                'id_color' => $colors->get($data['color']),
            ], [
                'sku' => $data['sku'],
                'code_bar' => null,
                'stock' => $data['stock'],
                'status' => 'active',
            ]);

            if ($variant->wasRecentlyCreated && $data['stock'] > 0) {
                InventoryMovement::create([
                    'id_product_variant' => $variant->id,
                    'movement_type' => 'entry',
                    'quantity' => $data['stock'],
                    'previous_stock' => 0,
                    'new_stock' => $data['stock'],
                    'reference_type' => 'initial_load',
                    'reference_id' => null,
                    'notes' => 'Carga inicial de inventario',
                    'id_user' => $administrator->id,
                ]);
            }
        }

        $service = Product::firstOrCreate(['key' => 'SERV-001'], [
            'id_size_group' => null,
            'name' => 'Ajuste de prenda',
            'description' => 'Servicio de ajuste, sin inventario',
            'code_bar' => null,
            'price' => 150.00,
            'cost' => 0.00,
            'stock_control' => false,
            'discount' => 0.00,
            'type_iva' => 1,
            'rate_iva' => 16.00,
            'quota_iva' => null,
            'isr' => 0.00,
            'imp_esp' => 0.00,
            'status' => 'active',
        ]);

        $service->categories()->syncWithoutDetaching([$categories->get('Caballero')]);
    }
}
