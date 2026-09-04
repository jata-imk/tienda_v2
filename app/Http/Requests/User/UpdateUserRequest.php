<?php

namespace App\Http\Requests\User;

use App\DTOs\User\UpdateUserDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('user');

        return [
            'idUserType' => [
                'sometimes',
                'integer',
                Rule::exists('user_types', 'id')->where('status', 'active'),
            ],
            'firstName' => 'sometimes|string|max:100',
            'lastName' => 'sometimes|string|max:100',
            'userName' => "sometimes|string|max:100|unique:users,user_name,{$id}",
            'email' => "sometimes|email|unique:users,email,{$id}",
            'password' => 'nullable|string|min:8',
            'status' => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateUserDTO
    {
        return new UpdateUserDTO(
            userTypeId: $this->filled('idUserType') ? (int) $this->input('idUserType') : null,
            firstName: $this->input('firstName'),
            lastName: $this->input('lastName'),
            userName: $this->input('userName'),
            email: $this->input('email'),
            password: $this->filled('password') ? $this->input('password') : null,
            status: $this->input('status'),
        );
    }
}
