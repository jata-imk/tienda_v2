<?php

namespace App\Services;

use App\DTOs\Dashboard\DashboardFiltersDTO;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas para el dashboard. Todo se resuelve en SQL: nunca se
 * cargan colecciones completas ni se usa Product::getTotalStockAttribute()
 * (haria N+1).
 */
class DashboardService
{
    public function summary(DashboardFiltersDTO $filters): array
    {
        return [
            'topProducts'  => $this->topProducts($filters),
            'lowestStock'  => $this->stockRanking($filters->limit, 'asc'),
            'highestStock' => $this->stockRanking($filters->limit, 'desc'),
            'summary'      => $this->totals($filters->lowStockThreshold),
        ];
    }

    /**
     * Productos mas vendidos. La unica fuente de ventas hoy son los
     * movimientos de inventario con `movement_type = 'sale'`.
     */
    private function topProducts(DashboardFiltersDTO $filters): array
    {
        $query = InventoryMovement::query()
            ->join('product_variants', 'product_variants.id', '=', 'inventory_movements.id_product_variant')
            ->join('products', 'products.id', '=', 'product_variants.id_product')
            ->where('inventory_movements.movement_type', 'sale')
            ->groupBy('products.id', 'products.key', 'products.name')
            ->select([
                'products.id',
                'products.key',
                'products.name',
                DB::raw('SUM(inventory_movements.quantity) as quantity_sold'),
            ])
            ->orderByDesc('quantity_sold')
            ->limit($filters->limit);

        if ($filters->dateFrom !== null) {
            $query->where('inventory_movements.created_at', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->where('inventory_movements.created_at', '<=', $filters->dateTo);
        }

        return $query->get()
            ->map(fn($row) => [
                'id'           => (int) $row->id,
                'key'          => $row->key,
                'name'         => $row->name,
                'quantitySold' => (float) $row->quantity_sold,
            ])
            ->all();
    }

    /**
     * Ranking de existencias por producto (suma de variantes activas).
     */
    private function stockRanking(int $limit, string $direction): array
    {
        return Product::query()
            ->leftJoin('product_variants', function ($join) {
                $join->on('product_variants.id_product', '=', 'products.id')
                    ->where('product_variants.status', 'active');
            })
            ->where('products.status', 'active')
            ->groupBy('products.id', 'products.key', 'products.name')
            ->select([
                'products.id',
                'products.key',
                'products.name',
                DB::raw('COALESCE(SUM(product_variants.stock), 0) as stock'),
            ])
            ->orderBy('stock', $direction)
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'id'    => (int) $row->id,
                'key'   => $row->key,
                'name'  => $row->name,
                'stock' => (float) $row->stock,
            ])
            ->all();
    }

    private function totals(float $lowStockThreshold): array
    {
        $products = Product::query()
            ->selectRaw("COUNT(*) as total, SUM(status = 'active') as active")
            ->first();

        $variants = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.id_product')
            ->where('product_variants.status', 'active')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(product_variants.stock), 0) as stock')
            ->selectRaw('COALESCE(SUM(product_variants.stock * products.cost), 0) as value')
            ->first();

        $lowStockCount = DB::query()
            ->fromSub(
                Product::query()
                    ->leftJoin('product_variants', function ($join) {
                        $join->on('product_variants.id_product', '=', 'products.id')
                            ->where('product_variants.status', 'active');
                    })
                    ->where('products.status', 'active')
                    ->where('products.stock_control', true)
                    ->groupBy('products.id')
                    ->select('products.id')
                    ->selectRaw('COALESCE(SUM(product_variants.stock), 0) as stock'),
                'stock_by_product',
            )
            ->where('stock', '<=', $lowStockThreshold)
            ->count();

        return [
            'totalProducts'  => (int) $products->total,
            'activeProducts' => (int) $products->active,
            'totalVariants'  => (int) $variants->total,
            'totalStock'     => (float) $variants->stock,
            'inventoryValue' => round((float) $variants->value, 2),
            'lowStockCount'  => $lowStockCount,
        ];
    }
}
