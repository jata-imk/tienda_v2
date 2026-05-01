<?php

namespace App\Services;

use App\DTOs\Inventario\ActualizarInventarioDTO;
use App\DTOs\Inventario\CrearInventarioDTO;
use App\Models\Inventario;
use Illuminate\Database\Eloquent\Collection;

class InventarioService
{
    public function index(): Collection
    {
        return Inventario::with(['categoria', 'tipoIva'])->get();
    }

    public function show(int $id): ?Inventario
    {
        return Inventario::with(['categoria', 'tipoIva'])->find($id);
    }

    public function store(CrearInventarioDTO $dto): Inventario
    {
        $producto = Inventario::create([
            'category_id'  => $dto->categoryId,
            'status'       => $dto->status,
            'clave'        => $dto->clave,
            'name'         => $dto->name,
            'description'  => $dto->description,
            'codebar'      => $dto->codebar,
            'price'        => $dto->price,
            'cost'         => $dto->cost,
            'stock_control' => $dto->stockControl,
            'stock'        => $dto->stock,
            'discount'     => $dto->discount,
            'type_iva_id'  => $dto->typeIvaId,
            'rate_iva'     => $dto->rateIva,
            'quota_iva'    => $dto->quotaIva,
            'isr'          => $dto->isr,
            'imp_esp'      => $dto->impEsp,
        ]);

        return $producto->fresh(['categoria', 'tipoIva']);
    }

    public function update(int $id, ActualizarInventarioDTO $dto): ?Inventario
    {
        $producto = Inventario::find($id);

        if (!$producto) {
            return null;
        }

        $campos = array_filter([
            'category_id'  => $dto->categoryId,
            'status'       => $dto->status,
            'clave'        => $dto->clave,
            'name'         => $dto->name,
            'description'  => $dto->description,
            'codebar'      => $dto->codebar,
            'price'        => $dto->price,
            'cost'         => $dto->cost,
            'stock_control' => $dto->stockControl,
            'stock'        => $dto->stock,
            'discount'     => $dto->discount,
            'type_iva_id'  => $dto->typeIvaId,
            'rate_iva'     => $dto->rateIva,
            'quota_iva'    => $dto->quotaIva,
            'isr'          => $dto->isr,
            'imp_esp'      => $dto->impEsp,
        ], fn($v) => $v !== null);

        $producto->update($campos);

        return $producto->fresh(['categoria', 'tipoIva']);
    }

    public function destroy(int $id): bool
    {
        $producto = Inventario::find($id);

        if (!$producto) {
            return false;
        }

        $producto->update(['status' => 'baja']);

        return true;
    }
}
