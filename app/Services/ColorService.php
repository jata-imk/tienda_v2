<?php

namespace App\Services;

use App\DTOs\Color\CreateColorDTO;
use App\DTOs\Color\UpdateColorDTO;
use App\Models\Color;
use Illuminate\Database\Eloquent\Collection;

class ColorService
{
    public function index(array $filters = []): array|Collection
    {
        $query = Color::query();

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

    public function show(int $id): ?Color
    {
        return Color::find($id);
    }

    public function store(CreateColorDTO $dto): Color
    {
        return Color::create([
            'name'      => $dto->name,
            'hex_color' => $dto->hexColor,
            'status'    => $dto->status,
        ]);
    }

    public function update(int $id, UpdateColorDTO $dto): ?Color
    {
        $color = Color::find($id);

        if (!$color) {
            return null;
        }

        $fields = array_filter([
            'name'      => $dto->name,
            'hex_color' => $dto->hexColor,
            'status'    => $dto->status,
        ], fn($v) => $v !== null);

        $color->update($fields);

        return $color->fresh();
    }

    public function destroy(int $id): bool
    {
        $color = Color::find($id);

        if (!$color) {
            return false;
        }

        $color->update(['status' => 'inactive']);

        return true;
    }
}
