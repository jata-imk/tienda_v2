<?php

namespace App\Services;

use App\DTOs\Category\CreateCategoryDTO;
use App\DTOs\Category\UpdateCategoryDTO;
use App\Models\Category;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    use AppliesGridConditions;

    public function index(array $filters = []): array|Collection
    {
        $query = Category::query();

        // where filters
        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                // Cada `&&` abre un bloque y arrastra los `||` que le siguen, para
                // que un grupo OR no anule los filtros AND anteriores.
                foreach ($this->groupGridConditions($filters['w']) as $block) {
                    if (count($block) === 1) {
                        $this->applyCategoryCondition($query, $block[0], 'and');

                        continue;
                    }

                    $query->where(function ($group) use ($block) {
                        foreach ($block as $index => $cond) {
                            $this->applyCategoryCondition($group, $cond, $index === 0 ? 'and' : 'or');
                        }
                    });
                }
            } else {
                foreach ($filters['w'] as $column => $value) {
                    $query->where($column, $value);
                }
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
     * Resuelve una condicion. `$boolean` es el conector con la condicion anterior
     * dentro del bloque (`and` en la primera, `or` en las demas).
     *
     * @param array<string, mixed> $cond
     */
    private function applyCategoryCondition($query, array $cond, string $boolean): void
    {
        // Campo virtual `search`: OR agrupado sobre name/description.
        if ($cond['column'] === 'search') {
            if (trim(trim((string) $cond['value'], '%')) === '') {
                return;
            }

            $sql  = strtoupper($cond['operator']) . ' ? ESCAPE ?';
            $bind = [$cond['value'], '\\'];

            $query->{$boolean === 'or' ? 'orWhere' : 'where'}(function ($q) use ($sql, $bind) {
                $q->whereRaw("name $sql", $bind)
                    ->orWhereRaw("description $sql", $bind);
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }

    public function show(int $id): ?Category
    {
        return Category::find($id);
    }

    public function store(CreateCategoryDTO $dto): Category
    {
        return Category::create([
            'name'        => $dto->name,
            'description' => $dto->description,
            'status'      => $dto->status,
        ]);
    }

    public function update(int $id, UpdateCategoryDTO $dto): ?Category
    {
        $category = Category::find($id);

        if (!$category) {
            return null;
        }

        $fields = array_filter([
            'name'        => $dto->name,
            'description' => $dto->description,
            'status'      => $dto->status,
        ], fn($v) => $v !== null);

        $category->update($fields);

        return $category->fresh();
    }

    public function destroy(int $id): bool
    {
        $category = Category::find($id);

        if (!$category) {
            return false;
        }

        $category->update(['status' => 'inactive']);

        return true;
    }
}
