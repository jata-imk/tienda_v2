<?php

namespace App\Services;

use App\DTOs\Size\CreateSizeDTO;
use App\DTOs\Size\UpdateSizeDTO;
use App\Models\Size;
use Illuminate\Database\Eloquent\Collection;

class SizeService
{
    public function index(array $filters = []): array|Collection
    {
        $query = Size::with('sizeGroup');

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

        if (!empty($filters['f'])) {
            $query->select($filters['f']);
        }

        if (!empty($filters['o'])) {
            $query->orderBy($filters['o']['column'] ?? 'id', $filters['o']['direction'] ?? 'asc');
        } else {
            $query->orderBy('sort_order');
        }

        $totalCount = isset($filters['totalCount'])
            ? (bool) filter_var($filters['totalCount'], FILTER_VALIDATE_BOOLEAN)
            : true;

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

    public function show(int $id): ?Size
    {
        return Size::with('sizeGroup')->find($id);
    }

    public function store(CreateSizeDTO $dto): Size
    {
        $size = Size::create([
            'id_size_group' => $dto->sizeGroupId,
            'name'          => $dto->name,
            'sort_order'    => $dto->sortOrder,
            'status'        => $dto->status,
        ]);

        return $size->fresh('sizeGroup');
    }

    public function update(int $id, UpdateSizeDTO $dto): ?Size
    {
        $size = Size::find($id);

        if (!$size) {
            return null;
        }

        $fields = array_filter([
            'id_size_group' => $dto->sizeGroupId,
            'name'          => $dto->name,
            'sort_order'    => $dto->sortOrder,
            'status'        => $dto->status,
        ], fn($v) => $v !== null);

        $size->update($fields);

        return $size->fresh('sizeGroup');
    }

    public function destroy(int $id): bool
    {
        $size = Size::find($id);

        if (!$size) {
            return false;
        }

        $size->update(['status' => 'inactive']);

        return true;
    }
}
