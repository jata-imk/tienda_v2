<?php

namespace App\Http\Requests\User;

use App\DTOs\User\UpdateUserDTO;
use Illuminate\Foundation\Http\FormRequest;

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
            'id_user_type' => 'sometimes|integer|exists:user_types,id',
            'first_name'   => 'sometimes|string|max:100',
            'last_name'    => 'sometimes|string|max:100',
            'user_name'    => "sometimes|string|max:100|unique:users,user_name,{$id}",
            'email'        => "sometimes|email|unique:users,email,{$id}",
            'password'     => 'nullable|string|min:8',
            'status'       => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateUserDTO
    {
        return new UpdateUserDTO(
            userTypeId: $this->filled('id_user_type') ? (int) $this->input('id_user_type') : null,
            firstName:  $this->input('first_name'),
            lastName:   $this->input('last_name'),
            userName:   $this->input('user_name'),
            email:      $this->input('email'),
            password:   $this->filled('password') ? $this->input('password') : null,
            status:     $this->input('status'),
        );
    }
}
