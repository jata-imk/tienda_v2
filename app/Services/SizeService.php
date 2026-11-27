<?php

namespace App\Services;

use App\DTOs\Size\CreateSizeDTO;
use App\DTOs\Size\UpdateSizeDTO;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\Concerns\AppliesGridConditions;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SizeService
{
    use AppliesGridConditions;

    private const DUPLICATE_NAME_MESSAGE = 'Ya existe una talla con este nombre en el grupo.';

    public function index(array $filters = []): array|Collection
    {
        $query = Size::with('sizeGroup')->orderedByGroup();

        if (! empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applySizeCondition']);
            } else {
                foreach ($filters['w'] as $column => $value) {
                    $query->where($column, $value);
                }
            }
        }

        if (! empty($filters['f'])) {
            $query->select($filters['f']);
        }

        if (! empty($filters['o'])) {
            $this->applyGridOrder($query, $filters, 'sizes');
        }

        $query->orderBy('sizes.sort_order')->orderBy('sizes.id');

        $totalCount = isset($filters['totalCount'])
            ? (bool) filter_var($filters['totalCount'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if (! empty($filters['p'])) {
            $page = (int) ($filters['p']['page'] ?? 1);
            $perPage = (int) ($filters['p']['per_page'] ?? 15);
            $items = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => $items->items(),
                'total' => $totalCount ? $items->total() : null,
                'page' => $items->currentPage(),
                'pages' => $items->lastPage(),
            ];
        }

        $items = $query->get();

        if ($totalCount) {
            return ['items' => $items, 'total' => $items->count()];
        }

        return $items;
    }

    public function show(int $id): ?Size
    {
        return Size::with('sizeGroup')->find($id);
    }

    public function store(CreateSizeDTO $dto): Size
    {
        return Audit::transaction(function () use ($dto) {
            $this->assertNameAvailable($dto->sizeGroupId, $dto->name);

            $size = new Size([
                'id_size_group' => $dto->sizeGroupId,
                'name' => $dto->name,
                'sort_order' => $dto->sortOrder,
                'status' => $dto->status,
            ]);
            $this->saveSize($size);

            $initialization = app(VariantInitializationService::class);
            $withoutColors = [];
            Product::where('id_size_group', $size->id_size_group)->orderBy('id')->chunkById(100, function ($products) use ($size, $initialization, &$withoutColors) {
                foreach ($products as $candidate) {
                    $product = Product::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if ((int) $product->id_size_group !== (int) $size->id_size_group) {
                        continue;
                    }
                    // Lectura actual tras bloquear el producto, incluso si el lote ya
                    // abrió una vista anterior de InnoDB mientras esperaba el lock.
                    $colorIds = ProductVariant::where('id_product', $product->id)->orderBy('id')
                        ->lockForUpdate()->get(['id', 'id_color'])->pluck('id_color')->unique();
                    if ($colorIds->isEmpty()) {
                        $withoutColors[] = $product->id;

                        continue;
                    }
                    foreach (Color::whereIn('id', $colorIds)->orderBy('id')->get() as $color) {
                        $initialization->createMissing($product, $size, $color, $initialization->actorId());
                    }
                }
            });
            if ($withoutColors !== []) {
                app(AuditTrail::class)->metadata(['productsWithoutColors' => $withoutColors]);
            }

            return $size->fresh('sizeGroup');
        });
    }

    public function update(int $id, UpdateSizeDTO $dto): ?Size
    {
        return Audit::transaction(function () use ($id, $dto) {
            $size = Size::lockForUpdate()->find($id);

            if (! $size) {
                return null;
            }

            $this->assertNameAvailable(
                $dto->sizeGroupId ?? (int) $size->id_size_group,
                $dto->name ?? $size->name,
                $size->id,
            );

            if ($dto->sizeGroupId !== null && (int) $size->id_size_group !== $dto->sizeGroupId) {
                $productIds = ProductVariant::where('id_size', $size->id)->distinct()->pluck('id_product');
                $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
                if ($products->contains(fn ($product) => (int) $product->id_size_group !== $dto->sizeGroupId)) {
                    throw ValidationException::withMessages([
                        'idSizeGroup' => 'No se puede cambiar el grupo: la talla está asociada a productos de otro grupo.',
                    ]);
                }
            }

            $fields = array_filter([
                'id_size_group' => $dto->sizeGroupId,
                'name' => $dto->name,
                'sort_order' => $dto->sortOrder,
                'status' => $dto->status,
            ], fn ($v) => $v !== null);

            $size->fill($fields);
            $this->saveSize($size);

            return $size->fresh('sizeGroup');
        });
    }

    public function destroy(int $id): bool
    {
        return Audit::transaction(function () use ($id) {
            $size = Size::lockForUpdate()->find($id);

            if (! $size) {
                return false;
            }

            $size->update(['status' => 'inactive']);

            return true;
        });
    }

    private function assertNameAvailable(int $groupId, string $name, ?int $ignoreId = null): void
    {
        $duplicate = Size::where('id_size_group', $groupId)->where('name', $name)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME_MESSAGE]);
        }
    }

    /** El índice resuelve la carrera entre comprobar disponibilidad y guardar. */
    private function saveSize(Size $size): void
    {
        try {
            $size->save();
        } catch (UniqueConstraintViolationException $exception) {
            // MariaDB reporta el índice; SQLite reporta las columnas.
            if ($exception->index !== 'sizes_group_name_unique'
                && $exception->columns !== ['id_size_group', 'name']) {
                throw $exception;
            }

            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME_MESSAGE]);
        }
    }

    /**
     * Resuelve una condicion de talla soportando relaciones y campo virtual `search`.
     *
     * @param  mixed  $query
     * @param  array<string, mixed>  $cond
     */
    public function applySizeCondition($query, array $cond, string $boolean): void
    {
        $or = $boolean === 'or';
        $column = $cond['column'];

        if ($column === 'search') {
            $value = trim(trim((string) ($cond['value'] ?? ''), '%'));
            if ($value === '') {
                return;
            }

            $likeVal = '%'.addcslashes($value, '%_\\').'%';
            $method = $or ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($likeVal) {
                $q->where('name', 'like', $likeVal)
                    ->orWhereHas('sizeGroup', fn ($sq) => $sq->where('name', 'like', $likeVal));
            });

            return;
        }

        if ($column === 'size_group') {
            $operator = $cond['operator'] ?? '=';
            $value = $cond['value'] ?? null;
            $method = $or ? 'orWhereHas' : 'whereHas';

            $query->$method('sizeGroup', function ($sq) use ($operator, $value) {
                $sq->where('name', $operator, $value);
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }
}
