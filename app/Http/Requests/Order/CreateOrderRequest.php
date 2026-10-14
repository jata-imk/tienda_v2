<?php

namespace App\Http\Requests\Order;

use App\DTOs\Order\CreateOrderDTO;
use App\Http\Requests\Order\Concerns\NormalizesOrderInput;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    use NormalizesOrderInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->headerRules(partial: false) + $this->lineRules('required');
    }

    public function toDTO(): CreateOrderDTO
    {
        return new CreateOrderDTO(
            currencyId:      $this->filled('idCurrency') ? (int) $this->input('idCurrency') : null,
            exchangeRate:    $this->filled('exchangeRate') ? (float) $this->input('exchangeRate') : null,
            dateOrder:       $this->input('dateOrder'),
            dateExpected:    $this->input('dateExpected'),
            supplierName:    $this->input('supplierName'),
            supplierDetails: $this->input('supplierDetails'),
            shippingAddress: $this->input('shippingAddress'),
            notes:           $this->input('notes'),
            lines:           $this->linesToDTO(),
        );
    }
}
