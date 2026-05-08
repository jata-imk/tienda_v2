<?php

namespace App\Http\Requests\User;

use App\DTOs\User\CreateUserDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idUserType' => 'required|integer|exists:user_types,id',
            'firstName'  => 'required|string|max:100',
            'lastName'   => 'required|string|max:100',
            'userName'   => 'required|string|max:100|unique:users,user_name',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8',
            'status'     => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateUserDTO
    {
        return new CreateUserDTO(
            userTypeId: (int) $this->input('idUserType'),
            firstName:  $this->input('firstName'),
            lastName:   $this->input('lastName'),
            userName:   $this->input('userName'),
            email:      $this->input('email'),
            password:   $this->input('password'),
            status:     $this->input('status', 'active'),
        );
    }
}
