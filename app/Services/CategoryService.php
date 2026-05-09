<?php

namespace App\Services;

use App\DTOs\Category\CreateCategoryDTO;
use App\DTOs\Category\UpdateCategoryDTO;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function index(array $filters = []): array|Collection
    {
        $query = Category::query();

        // where filters
        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                foreach ($filters['w'] as $cond) {
                    $method = ($cond['logic'] ?? 'and') === 'or' ? 'orWhere' : 'where';
                    $query->$method($cond['column'], $cond['operator'], $cond['value']);
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
