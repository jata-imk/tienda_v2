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
            'name'              => 'sometimes|string|max:255',
            'rfc'               => 'sometimes|nullable|string|max:255',
            'legal_name'        => 'sometimes|nullable|string|max:255',
            'tax_regime'        => 'sometimes|nullable|string|max:255',
            'logo'              => array_merge(['sometimes'], self::LOGO_RULES),
            'street'            => 'sometimes|nullable|string|max:255',
            'external_number'   => 'sometimes|nullable|string|max:255',
            'cross_street_one'  => 'sometimes|nullable|string|max:255',
            'cross_street_two'  => 'sometimes|nullable|string|max:255',
            'postal_code'       => 'sometimes|nullable|string|max:255',
            'neighborhood'      => 'sometimes|nullable|string|max:255',
            'city'              => 'sometimes|nullable|string|max:255',
            'stock_control'     => 'sometimes|boolean',
            'quantity_integers' => 'sometimes|integer|min:0|max:255',
            'quantity_decimals' => 'sometimes|integer|min:0|max:255',
            'grid_settings'     => 'sometimes|nullable|array',
            'status'            => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateCompanyInfoDTO
    {
        return new UpdateCompanyInfoDTO($this->validated());
    }
}
