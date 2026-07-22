<?php

namespace App\Http\Requests\CompanyInfo\Concerns;

/**
 * Los campos de `company_info` se validan en snake_case, pero el resto de la
 * API habla camelCase. Se aceptan ambas formas normalizando antes de validar.
 */
trait NormalizesCompanyInfoInput
{
    private const CAMEL_TO_SNAKE = [
        'legalName'        => 'legal_name',
        'taxRegime'        => 'tax_regime',
        'externalNumber'   => 'external_number',
        'crossStreetOne'   => 'cross_street_one',
        'crossStreetTwo'   => 'cross_street_two',
        'postalCode'       => 'postal_code',
        'stockControl'     => 'stock_control',
        'quantityIntegers' => 'quantity_integers',
        'quantityDecimals' => 'quantity_decimals',
        'gridSettings'     => 'grid_settings',
    ];

    /**
     * Logo en base64, con o sin prefijo data-URI. ~2.8 MB de texto ≈ 2 MB de
     * imagen; la columna es LONGTEXT.
     */
    private const LOGO_RULES = [
        'nullable',
        'string',
        'max:2800000',
        'regex:/^(data:image\/(png|jpe?g|webp);base64,)?[A-Za-z0-9+\/=\s]+$/',
    ];

    protected function prepareForValidation(): void
    {
        $replacements = [];

        foreach (self::CAMEL_TO_SNAKE as $camel => $snake) {
            if ($this->has($camel) && !$this->has($snake)) {
                $replacements[$snake] = $this->input($camel);
            }
        }

        if ($replacements !== []) {
            $this->merge($replacements);
        }
    }
}
