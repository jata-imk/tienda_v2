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
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:10|unique:currencies,code',
            'symbol'       => 'required|string|max:10',
            // Rango real de decimal(18,6): por debajo del minimo MariaDB redondearia
            // a 0 y por encima del maximo desbordaria (500 en vez de 422).
            'exchangeRate' => ['sometimes', 'numeric', 'between:0.000001,999999999999'],
            'status'       => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateCurrencyDTO
    {
        return new CreateCurrencyDTO(
            name:         $this->input('name'),
            code:         strtoupper($this->input('code')),
            symbol:       $this->input('symbol'),
            exchangeRate: round((float) $this->input('exchangeRate', 1), 6),
            status:       $this->input('status', 'active'),
        );
    }
}
