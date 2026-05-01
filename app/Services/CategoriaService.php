<?php

namespace App\Services;

use App\DTOs\Inventario\ActualizarCategoriaDTO;
use App\DTOs\Inventario\CrearCategoriaDTO;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;

class CategoriaService
{
    public function index(): Collection
    {
        return Categoria::all();
    }

    public function show(int $id): ?Categoria
    {
        return Categoria::find($id);
    }

    public function store(CrearCategoriaDTO $dto): Categoria
    {
        return Categoria::create([
            'name'        => $dto->name,
            'description' => $dto->description,
            'status'      => $dto->status,
        ]);
    }

    public function update(int $id, ActualizarCategoriaDTO $dto): ?Categoria
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return null;
        }

        $campos = array_filter([
            'name'        => $dto->name,
            'description' => $dto->description,
            'status'      => $dto->status,
        ], fn($v) => $v !== null);

        $categoria->update($campos);

        return $categoria->fresh();
    }

    public function destroy(int $id): bool
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return false;
        }

        $categoria->update(['status' => 'inactivo']);

        return true;
    }
}
