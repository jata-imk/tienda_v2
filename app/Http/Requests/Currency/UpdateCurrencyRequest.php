<?php

namespace App\Http\Requests\Currency;

use App\DTOs\Currency\UpdateCurrencyDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('currency');

        return [
            'name'         => 'sometimes|string|max:100',
            'code'         => "sometimes|string|max:10|unique:currencies,code,{$id}",
            'symbol'       => 'sometimes|string|max:10',
            // Rango real de decimal(18,6): por debajo del minimo MariaDB redondearia
            // a 0 y por encima del maximo desbordaria (500 en vez de 422).
            'exchangeRate' => ['sometimes', 'numeric', 'between:0.000001,999999999999'],
            'status'       => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateCurrencyDTO
    {
        return new UpdateCurrencyDTO(
            name:         $this->input('name'),
            code:         $this->filled('code') ? strtoupper($this->input('code')) : null,
            symbol:       $this->input('symbol'),
            exchangeRate: $this->filled('exchangeRate') ? round((float) $this->input('exchangeRate'), 6) : null,
            status:       $this->input('status'),
        );
    }
}
