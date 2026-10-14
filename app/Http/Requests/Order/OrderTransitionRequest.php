<?php

namespace App\Http\Requests\Order;

use App\DTOs\Order\OrderTransitionDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Confirmar o cancelar: solo admite un comentario para el historial. */
class OrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function toDTO(): OrderTransitionDTO
    {
        return new OrderTransitionDTO(notes: $this->input('notes'));
    }
}
