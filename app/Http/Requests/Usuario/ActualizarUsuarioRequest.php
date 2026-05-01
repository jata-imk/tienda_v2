<?php

namespace App\Http\Requests\Usuario;

use App\DTOs\Usuario\ActualizarUsuarioDTO;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('usuario');

        return [
            'name'         => 'sometimes|string|max:100',
            'first_name'   => 'sometimes|string|max:100',
            'last_name'    => 'sometimes|string|max:100',
            'username'     => "sometimes|string|max:100|unique:usuarios,username,{$id}",
            'email'        => "sometimes|email|unique:usuarios,email,{$id}",
            'password'     => 'nullable|string|min:8',
            'user_type_id' => 'sometimes|integer|exists:tipos_usuario,id',
            'status'       => 'sometimes|in:activo,inactivo',
        ];
    }

    public function toDTO(): ActualizarUsuarioDTO
    {
        return new ActualizarUsuarioDTO(
            name:       $this->input('name'),
            firstName:  $this->input('first_name'),
            lastName:   $this->input('last_name'),
            username:   $this->input('username'),
            email:      $this->input('email'),
            password:   $this->filled('password') ? $this->input('password') : null,
            userTypeId: $this->filled('user_type_id') ? (int) $this->input('user_type_id') : null,
            status:     $this->input('status'),
        );
    }
}
