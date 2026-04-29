<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    public function index(): array
    {
        $usuarios = Usuario::with('tipoUsuario')->get()->map(fn($u) => $this->format($u));

        return ['result' => 'ok', 'message' => 'Usuarios obtenidos.', 'data' => $usuarios];
    }

    public function show(int $id): array
    {
        $usuario = Usuario::with('tipoUsuario')->find($id);

        if (!$usuario) {
            return ['result' => 'error', 'message' => 'Usuario no encontrado.', 'data' => null];
        }

        return ['result' => 'ok', 'message' => 'Usuario obtenido.', 'data' => $this->format($usuario)];
    }

    public function store(array $data): array
    {
        $usuario = Usuario::create([
            'user_type_id' => $data['user_type_id'],
            'name'         => $data['name'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'username'     => $data['username'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'status'       => $data['status'] ?? 'activo',
        ]);

        return ['result' => 'ok', 'message' => 'Usuario creado.', 'data' => $this->format($usuario->fresh('tipoUsuario'))];
    }

    public function update(int $id, array $data): array
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return ['result' => 'error', 'message' => 'Usuario no encontrado.', 'data' => null];
        }

        $campos = array_filter([
            'user_type_id' => $data['user_type_id'] ?? null,
            'name'         => $data['name'] ?? null,
            'first_name'   => $data['first_name'] ?? null,
            'last_name'    => $data['last_name'] ?? null,
            'username'     => $data['username'] ?? null,
            'email'        => $data['email'] ?? null,
            'status'       => $data['status'] ?? null,
            'password'     => isset($data['password']) ? Hash::make($data['password']) : null,
        ], fn($v) => $v !== null);

        $usuario->update($campos);

        return ['result' => 'ok', 'message' => 'Usuario actualizado.', 'data' => $this->format($usuario->fresh('tipoUsuario'))];
    }

    public function destroy(int $id): array
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return ['result' => 'error', 'message' => 'Usuario no encontrado.', 'data' => null];
        }

        $usuario->update(['status' => 'inactivo']);

        return ['result' => 'ok', 'message' => 'Usuario desactivado.', 'data' => null];
    }

    private function format(Usuario $u): array
    {
        return [
            'id'           => $u->id,
            'nombre'       => $u->name,
            'primerApellido'  => $u->first_name,
            'segundoApellido' => $u->last_name,
            'usuario'      => $u->username,
            'email'        => $u->email,
            'tipoUsuario'  => $u->tipoUsuario?->type_user,
            'status'       => $u->status,
            'dateCreation' => $u->date_creation?->toDateTimeString(),
        ];
    }
}
