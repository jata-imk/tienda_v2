<?php

namespace App\Services;

use App\DTOs\Currency\CreateCurrencyDTO;
use App\DTOs\Currency\UpdateCurrencyDTO;
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
            'name'   => $dto->name,
            'code'   => $dto->code,
            'symbol' => $dto->symbol,
            'status' => $dto->status,
        ]);
    }

    public function update(int $id, UpdateCurrencyDTO $dto): ?Currency
    {
        $currency = Currency::find($id);

        if (!$currency) {
            return null;
        }

        $fields = array_filter([
            'name'   => $dto->name,
            'code'   => $dto->code,
            'symbol' => $dto->symbol,
            'status' => $dto->status,
        ], fn($v) => $v !== null);

        $currency->update($fields);

        return $currency->fresh();
    }

    public function destroy(int $id): bool
    {
        $currency = Currency::find($id);

        if (!$currency) {
            return false;
        }

        $currency->update(['status' => 'inactive']);

        return true;
    }
}
