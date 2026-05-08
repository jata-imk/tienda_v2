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
            'id_user_type' => 'required|integer|exists:user_types,id',
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'user_name'    => 'required|string|max:100|unique:users,user_name',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'status'       => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateUserDTO
    {
        return new CreateUserDTO(
            userTypeId: (int) $this->input('id_user_type'),
            firstName:  $this->input('first_name'),
            lastName:   $this->input('last_name'),
            userName:   $this->input('user_name'),
            email:      $this->input('email'),
            password:   $this->input('password'),
            status:     $this->input('status', 'active'),
        );
    }
}
