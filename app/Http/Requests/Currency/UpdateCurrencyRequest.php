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
            'name'   => 'sometimes|string|max:100',
            'code'   => "sometimes|string|max:10|unique:currencies,code,{$id}",
            'symbol' => 'sometimes|string|max:10',
            'status' => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateCurrencyDTO
    {
        return new UpdateCurrencyDTO(
            name:   $this->input('name'),
            code:   $this->filled('code') ? strtoupper($this->input('code')) : null,
            symbol: $this->input('symbol'),
            status: $this->input('status'),
        );
    }
}
