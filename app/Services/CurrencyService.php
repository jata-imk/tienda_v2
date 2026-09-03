<?php

namespace App\Services;

use App\DTOs\Currency\CreateCurrencyDTO;
use App\DTOs\Currency\UpdateCurrencyDTO;
use App\Models\CompanyInfo;
use App\Models\Currency;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    use AppliesGridConditions;

    public function index(array $filters = []): array|Collection
    {
        $query = Currency::query();

        if (!empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applyCurrencyCondition']);
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

    /**
     * Resuelve una condicion de moneda soportando el campo virtual `search`.
     *
     * @param mixed $query
     * @param array<string, mixed> $cond
     */
    public function applyCurrencyCondition($query, array $cond, string $boolean): void
    {
        $or     = $boolean === 'or';
        $column = $cond['column'];

        if ($column === 'search') {
            $value = trim(trim((string) ($cond['value'] ?? ''), '%'));
            if ($value === '') {
                return;
            }

            $likeVal = '%' . addcslashes($value, '%_\\') . '%';
            $method  = $or ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($likeVal) {
                $q->where('name', 'like', $likeVal)
                  ->orWhere('code', 'like', $likeVal)
                  ->orWhere('symbol', 'like', $likeVal);
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }

    public function show(int $id): ?Currency
    {
        return Currency::find($id);
    }

    public function store(CreateCurrencyDTO $dto): Currency
    {
        return Currency::create([
            'name'          => $dto->name,
            'code'          => $dto->code,
            'symbol'        => $dto->symbol,
            'exchange_rate' => $dto->exchangeRate,
            'status'        => $dto->status,
        ]);
    }

    public function update(int $id, UpdateCurrencyDTO $dto): ?Currency
    {
        $currency = Currency::find($id);

        if (!$currency) {
            return null;
        }

        $fields = array_filter([
            'name'          => $dto->name,
            'code'          => $dto->code,
            'symbol'        => $dto->symbol,
            'exchange_rate' => $dto->exchangeRate,
            'status'        => $dto->status,
        ], fn($v) => $v !== null);

        $currency->update($fields);

        return $currency->fresh();
    }

    /**
     * Soft-delete: la FK de `company_info` es RESTRICT pero nunca dispara porque
     * no se borra la fila, asi que la moneda base se protege aqui.
     *
     * @return 'not_found'|'in_use'|'deactivated'
     */
    public function destroy(int $id): string
    {
        $currency = Currency::find($id);

        if (!$currency) {
            return 'not_found';
        }

        if (CompanyInfo::where('id_currency', $id)->exists()) {
            return 'in_use';
        }

        $currency->update(['status' => 'inactive']);

        return 'deactivated';
    }
}
