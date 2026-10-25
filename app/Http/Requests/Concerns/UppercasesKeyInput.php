<?php

namespace App\Http\Requests\Concerns;

/**
 * Las claves cortas (`CAM`, `NIN`) se guardan en mayusculas: se normalizan
 * antes de validar para que `unique` compare contra el valor final.
 */
trait UppercasesKeyInput
{
    protected function prepareForValidation(): void
    {
        $key = $this->input('key');

        if (is_string($key)) {
            $this->merge(['key' => strtoupper(trim($key))]);
        }
    }
}
