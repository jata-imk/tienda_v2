<?php

namespace App\Services;

use App\DTOs\Config\ActualizarTipoMonedaDTO;
use App\DTOs\Config\CrearTipoMonedaDTO;
use App\Models\TipoMoneda;
use Illuminate\Database\Eloquent\Collection;

class TipoMonedaService
{
    public function index(): Collection
    {
        return TipoMoneda::all();
    }

    public function show(int $id): ?TipoMoneda
    {
        return TipoMoneda::find($id);
    }

    public function store(CrearTipoMonedaDTO $dto): TipoMoneda
    {
        return TipoMoneda::create([
            'name'   => $dto->name,
            'code'   => $dto->code,
            'symbol' => $dto->symbol,
            'status' => $dto->status,
        ]);
    }

    public function update(int $id, ActualizarTipoMonedaDTO $dto): ?TipoMoneda
    {
        $moneda = TipoMoneda::find($id);

        if (!$moneda) {
            return null;
        }

        $campos = array_filter([
            'name'   => $dto->name,
            'code'   => $dto->code,
            'symbol' => $dto->symbol,
            'status' => $dto->status,
        ], fn($v) => $v !== null);

        $moneda->update($campos);

        return $moneda->fresh();
    }

    public function destroy(int $id): bool
    {
        $moneda = TipoMoneda::find($id);

        if (!$moneda) {
            return false;
        }

        $moneda->update(['status' => 'inactivo']);

        return true;
    }
}
