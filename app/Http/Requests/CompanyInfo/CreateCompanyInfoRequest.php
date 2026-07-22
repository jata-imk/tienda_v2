<?php

namespace App\Http\Requests\CompanyInfo;

use App\DTOs\CompanyInfo\CreateCompanyInfoDTO;
use App\Http\Requests\CompanyInfo\Concerns\NormalizesCompanyInfoInput;
use Illuminate\Foundation\Http\FormRequest;

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
            'name'              => 'required|string|max:255',
            'rfc'               => 'nullable|string|max:255',
            'legal_name'        => 'nullable|string|max:255',
            'tax_regime'        => 'nullable|string|max:255',
            'logo'              => self::LOGO_RULES,
            'street'            => 'nullable|string|max:255',
            'external_number'   => 'nullable|string|max:255',
            'cross_street_one'  => 'nullable|string|max:255',
            'cross_street_two'  => 'nullable|string|max:255',
            'postal_code'       => 'nullable|string|max:255',
            'neighborhood'      => 'nullable|string|max:255',
            'city'              => 'nullable|string|max:255',
            'stock_control'     => 'boolean',
            'quantity_integers' => 'integer|min:0|max:255',
            'quantity_decimals' => 'integer|min:0|max:255',
            'grid_settings'     => 'nullable|array',
            'status'            => 'in:active,inactive',
        ];
    }

    public function toDTO(): CreateCompanyInfoDTO
    {
        return new CreateCompanyInfoDTO($this->validated());
    }
}
