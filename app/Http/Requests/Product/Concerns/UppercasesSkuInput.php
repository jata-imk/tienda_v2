<?php

namespace App\Http\Requests\Product\Concerns;

/**
 * Los SKU capturados a mano se guardan en mayusculas, igual que los generados
 * (`cam001-s2-c17` -> `CAM001-S2-C17`). Se normalizan antes de validar: si no,
 * `distinct` dejaria pasar `abc` y `ABC` en el mismo envio y el unique de la
 * tabla reventaria como 500.
 */
trait UppercasesSkuInput
{
    protected function uppercaseSkus(): void
    {
        $sku = $this->input('sku');

        if (is_string($sku)) {
            $this->merge(['sku' => strtoupper(trim($sku))]);
        }

        $variants = $this->input('variants');

        if (!is_array($variants)) {
            return;
        }

        $this->merge([
            'variants' => array_map(function ($variant) {
                if (is_array($variant) && is_string($variant['sku'] ?? null)) {
                    $variant['sku'] = strtoupper(trim($variant['sku']));
                }

                return $variant;
            }, $variants),
        ]);
    }
}
