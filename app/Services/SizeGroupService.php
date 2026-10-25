<?php

namespace App\Services;

use App\DTOs\SizeGroup\CreateSizeGroupDTO;
use App\DTOs\Product\KeyRenamePlan;
use App\DTOs\SizeGroup\UpdateSizeGroupDTO;
use App\Models\SizeGroup;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;

class SizeGroupService
{
    use AppliesGridConditions;

    public function __construct(private KeyRenameService $keyRenameService) {}

    public function index(array $filters = []): array|Collection
    {
        $query = SizeGroup::query();

        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applySizeGroupCondition']);
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
            $this->applyGridOrder($query, $filters, 'size_groups');
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

    public function show(int $id): ?SizeGroup
    {
        return SizeGroup::find($id);
    }

    public function store(CreateSizeGroupDTO $dto): SizeGroup
    {
        return SizeGroup::create([
            'key'         => $dto->key,
            'name'        => $dto->name,
            'description' => $dto->description,
            'status'      => $dto->status,
        ]);
    }

    public function update(int $id, UpdateSizeGroupDTO $dto): ?SizeGroup
    {
        $sizeGroup = SizeGroup::find($id);

        if (!$sizeGroup) {
            return null;
        }

        $fields = array_filter([
            'key'         => $dto->key,
            'name'        => $dto->name,
            'description' => $dto->description,
            'status'      => $dto->status,
        ], fn($v) => $v !== null);

        if ($dto->clearKey) {
            $fields['key'] = null;
        }

        $this->keyRenameService->saveWithCascade(
            $sizeGroup,
            $dto->cascadeKeys,
            fn(SizeGroup $locked) => $this->keyRenamePlan($locked, $dto),
            fn(SizeGroup $locked) => $locked->update($fields),
        );

        return $sizeGroup->fresh();
    }

    /**
     * Vista previa (`dryRun`) del renombrado en cascada; null si no existe.
     */
    public function keyRenamePreview(int $id, UpdateSizeGroupDTO $dto): ?KeyRenamePlan
    {
        $sizeGroup = SizeGroup::find($id);

        return $sizeGroup ? $this->keyRenamePlan($sizeGroup, $dto) : null;
    }

    /**
     * `key: null` quita el segmento: `GUA-NIN-003` -> `GUA-003`.
     */
    private function keyRenamePlan(SizeGroup $sizeGroup, UpdateSizeGroupDTO $dto): KeyRenamePlan
    {
        return $dto->clearKey || $dto->key !== null
            ? $this->keyRenameService->forSizeGroup($sizeGroup, $dto->clearKey ? null : $dto->key)
            : new KeyRenamePlan();
    }

    public function destroy(int $id): bool
    {
        $sizeGroup = SizeGroup::find($id);

        if (!$sizeGroup) {
            return false;
        }

        $sizeGroup->update(['status' => 'inactive']);

        return true;
    }

    /**
     * Resuelve una condicion de grupo de tallas soportando el campo virtual `search`.
     *
     * @param mixed $query
     * @param array<string, mixed> $cond
     */
    public function applySizeGroupCondition($query, array $cond, string $boolean): void
    {
        $or = $boolean === 'or';

        if ($cond['column'] === 'search') {
            $value = trim(trim((string) ($cond['value'] ?? ''), '%'));
            if ($value === '') {
                return;
            }

            $likeVal = '%' . addcslashes($value, '%_\\') . '%';
            $method  = $or ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($likeVal) {
                $q->where('name', 'like', $likeVal)
                  ->orWhere('description', 'like', $likeVal);
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }
}
