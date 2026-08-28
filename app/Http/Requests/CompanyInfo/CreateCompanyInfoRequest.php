<?php

namespace App\Http\Requests\CompanyInfo;

use App\DTOs\CompanyInfo\CreateCompanyInfoDTO;
use App\Http\Requests\CompanyInfo\Concerns\NormalizesCompanyInfoInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCompanyInfoRequest extends FormRequest
{
    use NormalizesCompanyInfoInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'rfc'              => 'nullable|string|max:255',
            'legalName'        => 'nullable|string|max:255',
            'taxRegime'        => 'nullable|string|max:255',
            'idCurrency'       => ['nullable', 'integer', Rule::exists('currencies', 'id')->where('status', 'active')],
            'logo'             => self::LOGO_RULES,
            'street'           => 'nullable|string|max:255',
            'externalNumber'   => 'nullable|string|max:255',
            'crossStreetOne'   => 'nullable|string|max:255',
            'crossStreetTwo'   => 'nullable|string|max:255',
            'postalCode'       => 'nullable|string|max:255',
            'neighborhood'     => 'nullable|string|max:255',
            'city'             => 'nullable|string|max:255',
            'stockControl'     => 'boolean',
            'quantityIntegers' => 'integer|min:0|max:255',
            'quantityDecimals' => 'integer|min:0|max:255',
            'gridSettings'     => 'nullable|array',
            'status'           => 'in:active,inactive',
        ];
    }

    public function toDTO(): CreateCompanyInfoDTO
    {
        return new CreateCompanyInfoDTO($this->validatedSnake());
    }
}
