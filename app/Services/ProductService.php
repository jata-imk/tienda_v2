<?php

namespace App\Services;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductService
{
    use AppliesGridConditions;

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
                // Cada `&&` abre un bloque y arrastra los `||` que le siguen; un
                // bloque de dos o mas se envuelve en parentesis para que el OR no
                // se lleve por delante los filtros AND anteriores.
                foreach ($this->groupGridConditions($filters['w']) as $block) {
                    if (count($block) === 1) {
                        $this->applyProductCondition($query, $block[0], 'and');

                        continue;
                    }

                    $query->where(function ($group) use ($block) {
                        foreach ($block as $index => $cond) {
                            $this->applyProductCondition($group, $cond, $index === 0 ? 'and' : 'or');
                        }
                    });
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

    /**
     * Resuelve una condicion de producto. `$boolean` es el conector con la
     * condicion anterior dentro del bloque (`and` en la primera, `or` en las
     * demas). Las columnas que en realidad son relaciones (`categories`,
     * `id_size_group`) nunca llegan al builder plano.
     *
     * @param array<string, mixed> $cond
     */
    private function applyProductCondition($query, array $cond, string $boolean): void
    {
        $or       = $boolean === 'or';
        $column   = $cond['column'];
        $operator = $cond['operator'] ?? '=';
        $value    = $cond['value'] ?? null;

        // Campo virtual `search`: OR agrupado sobre columnas y relaciones.
        if ($column === 'search') {
            if ($this->searchTermIsEmpty($value)) {
                return;
            }

            $this->applySearchGroup($query, $or ? 'orWhere' : 'where', $operator, $value);

            return;
        }

        if (in_array($column, self::CATEGORY_FILTER_KEYS, true)) {
            $this->applyCategoryCondition($query, $operator, $value, $or);

            return;
        }

        // `idSizeGroup contains texto` busca en el nombre del grupo de tallas, no
        // en el FK entero. Con comparadores escalares sigue siendo la columna.
        if ($column === 'id_size_group' && $this->isLikeOperator($operator)) {
            $method = $this->relationMethod($operator === 'like', $or);

            $query->$method(
                'sizeGroup',
                fn($q) => $q->whereRaw('size_groups.name LIKE ? ESCAPE ?', [$value, '\\']),
            );

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }

    /**
     * Filtro por categoria: siempre contra la pivote. `in` / `not in` traen una
     * lista de ids; `contains` (`like`) busca en el nombre de la categoria.
     */
    private function applyCategoryCondition($query, string $operator, mixed $value, bool $or): void
    {
        if ($operator === 'in' || $operator === 'not in') {
            $ids = $this->normalizeInValues($value);

            // Seleccion vacia: el frontend no filtro por categoria.
            if ($ids === []) {
                return;
            }

            $method = $this->relationMethod($operator === 'in', $or);

            $query->$method('categories', fn($q) => $q->whereIn('categories.id', $ids));

            return;
        }

        if ($this->isLikeOperator($operator)) {
            $method = $this->relationMethod($operator === 'like', $or);

            $query->$method(
                'categories',
                fn($q) => $q->whereRaw('categories.name LIKE ? ESCAPE ?', [$value, '\\']),
            );

            return;
        }

        $query->{$this->relationMethod(true, $or)}(
            'categories',
            fn($q) => $q->where('categories.id', $operator, $value),
        );
    }

    /** `whereHas` / `whereDoesntHave` y sus variantes `or`. */
    private function relationMethod(bool $positive, bool $or): string
    {
        if ($positive) {
            return $or ? 'orWhereHas' : 'whereHas';
        }

        return $or ? 'orWhereDoesntHave' : 'whereDoesntHave';
    }

    private function isLikeOperator(string $operator): bool
    {
        return in_array($operator, ['like', 'not like'], true);
    }

    /**
     * Búsqueda general: un grupo `(col LIKE x OR ... OR relacion LIKE x)` que
     * se AND-ea con el resto de filtros. El paréntesis lo genera el closure, y
     * así `status = active` nunca se pierde por la precedencia de OR.
     */
    private function applySearchGroup($query, string $method, string $operator, mixed $value): void
    {
        // ESCAPE explícito para que `\%`, `\_`, `\\` (que agrega escapeLike) se
        // interpreten igual en MariaDB y en sqlite (los tests corren en sqlite,
        // que no asume `\` como carácter de escape por defecto).
        $sql  = strtoupper($operator) . ' ? ESCAPE ?';
        $bind = [$value, '\\'];

        $query->$method(function ($q) use ($sql, $bind) {
            $q->whereRaw("products.name $sql", $bind)
                ->orWhereRaw("products.key $sql", $bind)
                ->orWhereRaw("products.description $sql", $bind)
                ->orWhereRaw("products.code_bar $sql", $bind)
                ->orWhereHas('categories', fn($c) => $c->whereRaw("categories.name $sql", $bind))
                ->orWhereHas('sizeGroup', fn($s) => $s->whereRaw("size_groups.name $sql", $bind));
        });
    }

    /**
     * El valor ya viene con comodines LIKE (`%term%`); la búsqueda se ignora si
     * el término real es vacío o sólo espacios.
     */
    private function searchTermIsEmpty(mixed $value): bool
    {
        return trim(trim((string) $value, '%')) === '';
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
