<?php

namespace App\Http\Requests\Currency;

use App\DTOs\Currency\CreateCurrencyDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:100',
            'code'   => 'required|string|max:10|unique:currencies,code',
            'symbol' => 'required|string|max:10',
            'status' => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateCurrencyDTO
    {
        return new CreateCurrencyDTO(
            name:   $this->input('name'),
            code:   strtoupper($this->input('code')),
            symbol: $this->input('symbol'),
            status: $this->input('status', 'active'),
        );
    }
}
