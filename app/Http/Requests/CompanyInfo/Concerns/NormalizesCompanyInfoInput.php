<?php

namespace App\Http\Requests\CompanyInfo\Concerns;

use Illuminate\Support\Str;

/**
 * `company-info` habla camelCase en la entrada (como el resto de la API); las
 * columnas de la tabla son snake_case. La conversion se hace despues de validar.
 */
trait NormalizesCompanyInfoInput
{
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

    /**
     * Datos validados con las llaves reindexadas a snake_case, listas para el
     * modelo (`legalName` → `legal_name`, `crossStreetOne` → `cross_street_one`).
     *
     * @return array<string, mixed>
     */
    protected function validatedSnake(): array
    {
        $fields = [];

        foreach ($this->validated() as $key => $value) {
            $fields[Str::snake($key)] = $value;
        }

        return $fields;
    }
}
