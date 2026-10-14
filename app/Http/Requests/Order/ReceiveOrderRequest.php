<?php

namespace App\Http\Requests\Order;

use App\DTOs\Order\ReceiveLineDTO;
use App\DTOs\Order\ReceiveOrderDTO;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes'            => 'nullable|string|max:500',
            'lines'            => 'required|array|min:1|max:200',
            'lines.*.detailId' => 'required|integer|distinct',
            'lines.*.quantity' => 'required|numeric|gt:0|max:9999999.999',
        ];
    }

    public function toDTO(): ReceiveOrderDTO
    {
        return new ReceiveOrderDTO(
            notes: $this->input('notes'),
            lines: array_map(
                fn (array $line) => new ReceiveLineDTO(
                    detailId: (int) $line['detailId'],
                    quantity: (float) $line['quantity'],
                ),
                $this->validated('lines'),
            ),
        );
    }
}
