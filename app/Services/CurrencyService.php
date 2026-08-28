<?php

namespace App\Services;

use App\DTOs\Currency\CreateCurrencyDTO;
use App\DTOs\Currency\UpdateCurrencyDTO;
use App\Models\CompanyInfo;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    public function index(): Collection
    {
        return Currency::all();
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
