<?php

namespace App\Services;

use App\DTOs\Dashboard\DashboardFiltersDTO;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas para el dashboard. Todo se resuelve en SQL: nunca se
 * cargan colecciones completas ni se usa Product::getTotalStockAttribute()
 * (haria N+1).
 */
class DashboardService
{
    /**
     * PDO no tiene PARAM_FLOAT: un umbral float viaja como texto y SQLite
     * considera cualquier texto mayor que cualquier numero, asi que
     * `stock <= ?` daria siempre verdadero. El CAST fuerza la comparacion
     * numerica y funciona igual en MariaDB.
     */
    private const NUMERIC_PARAM = 'CAST(? AS DECIMAL(10,3))';

    public function summary(DashboardFiltersDTO $filters): array
    {
        return [
            'topProducts'         => $this->topProducts($filters),
            'lowestStock'         => $this->stockRanking($filters->limit, 'asc'),
            'highestStock'        => $this->stockRanking($filters->limit, 'desc'),
            'criticalStockBySize' => $this->criticalStockBySize($filters->lowStockThreshold),
            'summary'             => $this->totals($filters->lowStockThreshold),
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

    /**
     * Combinaciones producto-talla con existencia critica. A diferencia de
     * `lowestStock` (ranking agregado por producto) esta es la lista completa
     * de combinaciones bajo el umbral: no se corta con `limit`.
     *
     * Los colores de una misma talla se suman en una sola fila. Solo entran
     * productos con control de existencias, igual que `lowStockCount`; los
     * servicios no tienen variantes, asi que el join interno los descarta.
     */
    private function criticalStockBySize(float $lowStockThreshold): array
    {
        return Product::query()
            ->join('product_variants', function ($join) {
                $join->on('product_variants.id_product', '=', 'products.id')
                    ->where('product_variants.status', 'active');
            })
            ->join('sizes', 'sizes.id', '=', 'product_variants.id_size')
            ->where('products.status', 'active')
            ->where('products.stock_control', true)
            ->groupBy('products.id', 'products.key', 'products.name', 'sizes.id', 'sizes.name', 'sizes.sort_order')
            ->select([
                'products.key',
                'products.name as product_name',
                'sizes.name as size_name',
                DB::raw('SUM(product_variants.stock) as stock'),
            ])
            ->havingRaw('SUM(product_variants.stock) <= ' . self::NUMERIC_PARAM, [$lowStockThreshold])
            ->orderByRaw('SUM(product_variants.stock) asc')
            ->orderBy('products.name')
            ->orderBy('sizes.sort_order')
            ->get()
            ->map(fn($row) => [
                'product' => $row->product_name,
                'key'     => $row->key,
                'size'    => $row->size_name,
                'stock'   => (float) $row->stock,
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

        return [
            'totalProducts'   => (int) $products->total,
            'activeProducts'  => (int) $products->active,
            'totalVariants'   => (int) $variants->total,
            'totalStock'      => (float) $variants->stock,
            'inventoryValue'  => round((float) $variants->value, 2),
            'lowStockCount'   => $this->countProductsWithStockUpTo($lowStockThreshold),
            'outOfStockCount' => $this->countProductsWithStockUpTo(0),
        ];
    }

    /**
     * Productos activos con control de existencias cuya existencia total no
     * supera el limite dado. Es un conteo global: no depende de `limit` ni de
     * los rankings, por eso el frontend no debe inferirlo de `lowestStock`.
     */
    private function countProductsWithStockUpTo(float $limit): int
    {
        return DB::query()
            ->fromSub($this->stockByProduct(), 'stock_by_product')
            ->whereRaw('stock <= ' . self::NUMERIC_PARAM, [$limit])
            ->count();
    }

    /**
     * Subconsulta: existencia total (variantes activas) por producto activo
     * con control de existencias.
     */
    private function stockByProduct(): QueryBuilder
    {
        return Product::query()
            ->leftJoin('product_variants', function ($join) {
                $join->on('product_variants.id_product', '=', 'products.id')
                    ->where('product_variants.status', 'active');
            })
            ->where('products.status', 'active')
            ->where('products.stock_control', true)
            ->groupBy('products.id')
            ->select('products.id')
            ->selectRaw('COALESCE(SUM(product_variants.stock), 0) as stock')
            ->toBase();
    }
}
