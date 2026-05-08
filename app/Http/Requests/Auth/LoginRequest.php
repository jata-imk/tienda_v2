<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\LoginDTO;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userName' => 'required|string',
            'password' => 'required|string',
        ];
    }

    public function toDTO(): LoginDTO
    {
        return new LoginDTO(
            userName: $this->input('userName'),
            password: $this->input('password'),
        );
    }
}
