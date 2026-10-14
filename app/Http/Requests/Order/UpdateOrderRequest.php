<?php

namespace App\Http\Requests\Order;

use App\DTOs\Order\UpdateOrderDTO;
use App\Http\Requests\Order\Concerns\NormalizesOrderInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Edicion parcial de un pedido en draft: los campos omitidos no se tocan. Si
 * se envia `details`, reemplaza el conjunto completo de lineas.
 */
class UpdateOrderRequest extends FormRequest
{
    use NormalizesOrderInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->headerRules(partial: true) + $this->lineRules('sometimes');
    }

    public function toDTO(): UpdateOrderDTO
    {
        $attributes = [];

        foreach (Arr::except($this->validated(), ['details']) as $key => $value) {
            $attributes[Str::snake($key)] = $value;
        }

        if (array_key_exists('id_currency', $attributes) && $attributes['id_currency'] !== null) {
            $attributes['id_currency'] = (int) $attributes['id_currency'];
        }

        return new UpdateOrderDTO(
            attributes: $attributes,
            lines:      $this->has('details') ? $this->linesToDTO() : null,
        );
    }
}
