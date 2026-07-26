<?php

namespace App\Services;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductService
{
    private const VARIANT_RELATIONS = ['categories', 'sizeGroup', 'variants.size', 'variants.color', 'colorImages'];

    /**
     * Nombres con los que el frontend puede filtrar por categoria. Ninguno es
     * columna de `products`: todos se resuelven contra la pivote.
     */
    private const CATEGORY_FILTER_KEYS = ['categories', 'id_category', 'category'];

    public function __construct(private ImageService $imageService) {}

    public function index(array $filters = []): array|Collection
    {
        $query = Product::with(self::VARIANT_RELATIONS);

        // where filters — `categories` ya no es columna: se resuelve por la pivote
        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                foreach ($filters['w'] as $cond) {
                    $method = ($cond['logic'] ?? 'and') === 'or' ? 'orWhere' : 'where';

                    if (in_array($cond['column'], self::CATEGORY_FILTER_KEYS, true)) {
                        $method = $method === 'orWhere' ? 'orWhereHas' : 'whereHas';
                        $query->$method('categories', fn($q) => $q->where(
                            'categories.id',
                            $cond['operator'],
                            $cond['value'],
                        ));

                        continue;
                    }

                    $query->$method($cond['column'], $cond['operator'], $cond['value']);
                }
            } else {
                foreach ($filters['w'] as $column => $value) {
                    if (in_array($column, self::CATEGORY_FILTER_KEYS, true)) {
                        $query->whereHas('categories', fn($q) => $q->whereIn('categories.id', (array) $value));

                        continue;
                    }

                    $query->where($column, $value);
                }
            }
        }

        // field selection — `categories` se descarta: no existe como columna
        if (!empty($filters['f'])) {
            $fields = array_values(array_diff($filters['f'], self::CATEGORY_FILTER_KEYS));

            if ($fields !== []) {
                // `id` es obligatorio para poder cargar la relacion categories.
                $query->select(array_values(array_unique(array_merge(['id'], $fields))));
            }
        }

        // ordering
        if (!empty($filters['o'])) {
            $column    = $filters['o']['column'] ?? 'id';
            $direction = $filters['o']['direction'] ?? 'asc';
            $query->orderBy($column, $direction);
        }

        $totalCount = isset($filters['totalCount'])
            ? (bool) filter_var($filters['totalCount'], FILTER_VALIDATE_BOOLEAN)
            : true;

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
        return Product::with(self::VARIANT_RELATIONS)->find($id);
    }

    public function store(CreateProductDTO $dto): Product
    {
        return DB::transaction(function () use ($dto) {
            $product = Product::create([
                'id_size_group' => $dto->sizeGroupId,
                'key'           => $dto->key,
                'name'          => $dto->name,
                'description'   => $dto->description,
                'code_bar'      => $dto->codeBar,
                'price'         => $dto->price,
                'cost'          => $dto->cost,
                'stock_control' => $dto->stockControl,
                'discount'      => $dto->discount,
                'type_iva'      => $dto->typeIva,
                'rate_iva'      => $dto->rateIva,
                'quota_iva'     => $dto->quotaIva,
                'isr'           => $dto->isr,
                'imp_esp'       => $dto->impEsp,
                'status'        => $dto->status,
            ]);

            $product->categories()->sync($dto->categoryIds);

            foreach ($dto->variants as $variantInput) {
                $variant = ProductVariant::create([
                    'id_product' => $product->id,
                    'id_size'    => $variantInput->sizeId,
                    'id_color'   => $variantInput->colorId,
                    'sku'        => $variantInput->sku,
                    'code_bar'   => $variantInput->codeBar,
                    'stock'      => $variantInput->stock,
                    'status'     => $variantInput->status,
                ]);

                // Movimiento inicial por cada variante con existencia > 0.
                if ($dto->initialMovement !== null && $variantInput->stock > 0) {
                    InventoryMovement::create([
                        'id_product_variant' => $variant->id,
                        'movement_type'      => $dto->initialMovement->movementType,
                        'quantity'           => $variantInput->stock,
                        'previous_stock'     => 0,
                        'new_stock'          => $variantInput->stock,
                        'reference_type'     => $dto->initialMovement->referenceType,
                        'reference_id'       => $dto->initialMovement->referenceId,
                        'notes'              => $dto->initialMovement->notes,
                        'id_user'            => $dto->initialMovement->userId,
                    ]);
                }
            }

            return $product->fresh(self::VARIANT_RELATIONS);
        });
    }

    public function update(int $id, UpdateProductDTO $dto): ?Product
    {
        $product = Product::find($id);

        if (!$product) {
            return null;
        }

        $fields = array_filter([
            'id_size_group' => $dto->sizeGroupId,
            'key'           => $dto->key,
            'name'          => $dto->name,
            'description'   => $dto->description,
            'code_bar'      => $dto->codeBar,
            'price'         => $dto->price,
            'cost'          => $dto->cost,
            'stock_control' => $dto->stockControl,
            'discount'      => $dto->discount,
            'type_iva'      => $dto->typeIva,
            'rate_iva'      => $dto->rateIva,
            'quota_iva'     => $dto->quotaIva,
            'isr'           => $dto->isr,
            'imp_esp'       => $dto->impEsp,
            'status'        => $dto->status,
        ], fn($v) => $v !== null);

        DB::transaction(function () use ($product, $fields, $dto) {
            if ($fields !== []) {
                $product->update($fields);
            }

            if ($dto->categoryIds !== null) {
                $product->categories()->sync($dto->categoryIds);
            }
        });

        return $product->fresh(self::VARIANT_RELATIONS);
    }

    /**
     * Reemplaza la imagen del producto: sube la nueva y borra la anterior.
     */
    public function setImage(int $id, UploadedFile $file): ?Product
    {
        $product = Product::find($id);

        if (!$product) {
            return null;
        }

        $previous = [$product->image, $product->image_thumb];
        $stored   = $this->imageService->store($file, "products/{$product->id}");

        $product->update([
            'image'       => $stored['path'],
            'image_thumb' => $stored['thumb'],
        ]);

        $this->imageService->delete(...$previous);

        return $product->fresh(self::VARIANT_RELATIONS);
    }

    public function clearImage(int $id): ?Product
    {
        $product = Product::find($id);

        if (!$product) {
            return null;
        }

        $this->imageService->delete($product->image, $product->image_thumb);

        $product->update(['image' => null, 'image_thumb' => null]);

        return $product->fresh(self::VARIANT_RELATIONS);
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
