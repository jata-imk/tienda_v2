<?php

namespace App\Services;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function index(array $filters = []): array|Collection
    {
        $query = Product::with('category');

        // where filters
        if (!empty($filters['w'])) {
            foreach ($filters['w'] as $column => $value) {
                $query->where($column, $value);
            }
        }

        // field selection
        if (!empty($filters['f'])) {
            $query->select($filters['f']);
        }

        // ordering
        if (!empty($filters['o'])) {
            $column    = $filters['o']['column'] ?? 'id';
            $direction = $filters['o']['direction'] ?? 'asc';
            $query->orderBy($column, $direction);
        }

        $totalCount = !empty($filters['totalCount']) && $filters['totalCount'];

        // pagination
        if (!empty($filters['p'])) {
            $page    = (int) ($filters['p']['page'] ?? 1);
            $perPage = (int) ($filters['p']['per_page'] ?? 15);
            $items   = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => $items->items(),
                'total' => $totalCount ? $items->total() : null,
                'page'  => $items->currentPage(),
                'pages' => $items->lastPage(),
            ];
        }

        $items = $query->get();

        if ($totalCount) {
            return ['items' => $items, 'total' => $items->count()];
        }

        return $items;
    }

    public function show(int $id): ?Product
    {
        return Product::with('category')->find($id);
    }

    public function store(CreateProductDTO $dto): Product
    {
        $product = Product::create([
            'id_category'   => $dto->categoryId,
            'key'           => $dto->key,
            'name'          => $dto->name,
            'description'   => $dto->description,
            'code_bar'      => $dto->codeBar,
            'size'          => $dto->size,
            'price'         => $dto->price,
            'cost'          => $dto->cost,
            'stock_control' => $dto->stockControl,
            'stock'         => $dto->stock,
            'discount'      => $dto->discount,
            'type_iva'      => $dto->typeIva,
            'rate_iva'      => $dto->rateIva,
            'quota_iva'     => $dto->quotaIva,
            'isr'           => $dto->isr,
            'imp_esp'       => $dto->impEsp,
            'status'        => $dto->status,
        ]);

        return $product->fresh('category');
    }

    public function update(int $id, UpdateProductDTO $dto): ?Product
    {
        $product = Product::find($id);

        if (!$product) {
            return null;
        }

        $fields = array_filter([
            'id_category'   => $dto->categoryId,
            'key'           => $dto->key,
            'name'          => $dto->name,
            'description'   => $dto->description,
            'code_bar'      => $dto->codeBar,
            'size'          => $dto->size,
            'price'         => $dto->price,
            'cost'          => $dto->cost,
            'stock_control' => $dto->stockControl,
            'stock'         => $dto->stock,
            'discount'      => $dto->discount,
            'type_iva'      => $dto->typeIva,
            'rate_iva'      => $dto->rateIva,
            'quota_iva'     => $dto->quotaIva,
            'isr'           => $dto->isr,
            'imp_esp'       => $dto->impEsp,
            'status'        => $dto->status,
        ], fn($v) => $v !== null);

        $product->update($fields);

        return $product->fresh('category');
    }

    public function destroy(int $id): bool
    {
        $product = Product::find($id);

        if (!$product) {
            return false;
        }

        $product->update(['status' => 'inactive']);

        return true;
    }
}
