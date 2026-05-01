<?php

namespace App\Services;

use App\DTOs\Usuario\ActualizarUsuarioDTO;
use App\DTOs\Usuario\CrearUsuarioDTO;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    public function index(): Collection
    {
        return Usuario::with('tipoUsuario')->get();
    }

    public function show(int $id): ?Usuario
    {
        return Usuario::with('tipoUsuario')->find($id);
    }

    public function store(CrearUsuarioDTO $dto): Usuario
    {
        $usuario = Usuario::create([
            'user_type_id' => $dto->userTypeId,
            'name'         => $dto->name,
            'first_name'   => $dto->firstName,
            'last_name'    => $dto->lastName,
            'username'     => $dto->username,
            'email'        => $dto->email,
            'password'     => Hash::make($dto->password),
            'status'       => $dto->status,
        ]);

        return $usuario->fresh('tipoUsuario');
    }

    public function update(int $id, ActualizarUsuarioDTO $dto): ?Usuario
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return null;
        }

        $campos = array_filter([
            'user_type_id' => $dto->userTypeId,
            'name'         => $dto->name,
            'first_name'   => $dto->firstName,
            'last_name'    => $dto->lastName,
            'username'     => $dto->username,
            'email'        => $dto->email,
            'status'       => $dto->status,
            'password'     => $dto->password !== null ? Hash::make($dto->password) : null,
        ], fn($v) => $v !== null);

        $usuario->update($campos);

        return $usuario->fresh('tipoUsuario');
    }

    public function destroy(int $id): bool
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return false;
        }

        $usuario->update(['status' => 'inactivo']);

        return true;
    }
}
