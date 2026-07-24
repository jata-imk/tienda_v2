<?php

namespace App\Http\Requests\CompanyInfo;

use App\DTOs\CompanyInfo\UpdateCompanyInfoDTO;
use App\Http\Requests\CompanyInfo\Concerns\NormalizesCompanyInfoInput;
use Illuminate\Foundation\Http\FormRequest;

class PatchCompanyInfoRequest extends FormRequest
{
    use NormalizesCompanyInfoInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|max:255',
            'rfc'              => 'sometimes|nullable|string|max:255',
            'legalName'        => 'sometimes|nullable|string|max:255',
            'taxRegime'        => 'sometimes|nullable|string|max:255',
            'logo'             => array_merge(['sometimes'], self::LOGO_RULES),
            'street'           => 'sometimes|nullable|string|max:255',
            'externalNumber'   => 'sometimes|nullable|string|max:255',
            'crossStreetOne'   => 'sometimes|nullable|string|max:255',
            'crossStreetTwo'   => 'sometimes|nullable|string|max:255',
            'postalCode'       => 'sometimes|nullable|string|max:255',
            'neighborhood'     => 'sometimes|nullable|string|max:255',
            'city'             => 'sometimes|nullable|string|max:255',
            'stockControl'     => 'sometimes|boolean',
            'quantityIntegers' => 'sometimes|integer|min:0|max:255',
            'quantityDecimals' => 'sometimes|integer|min:0|max:255',
            'gridSettings'     => 'sometimes|nullable|array',
            'status'           => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateCompanyInfoDTO
    {
        return new UpdateCompanyInfoDTO($this->validatedSnake());
    }
}
