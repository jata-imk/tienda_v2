<?php

namespace App\Http\Requests\Usuario;

use App\DTOs\Usuario\CrearUsuarioDTO;
use Illuminate\Foundation\Http\FormRequest;

class CrearUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:100',
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'username'     => 'required|string|max:100|unique:usuarios,username',
            'email'        => 'required|email|unique:usuarios,email',
            'password'     => 'required|string|min:8',
            'user_type_id' => 'required|integer|exists:tipos_usuario,id',
            'status'       => 'sometimes|in:activo,inactivo',
        ];
    }

    public function toDTO(): CrearUsuarioDTO
    {
        return new CrearUsuarioDTO(
            name:       $this->input('name'),
            firstName:  $this->input('first_name'),
            lastName:   $this->input('last_name'),
            username:   $this->input('username'),
            email:      $this->input('email'),
            password:   $this->input('password'),
            userTypeId: (int) $this->input('user_type_id'),
            status:     $this->input('status', 'activo'),
        );
    }
}
