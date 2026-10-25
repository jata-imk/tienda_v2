<?php

namespace App\Http\Requests\Concerns;

/**
 * Banderas del renombrado en cascada, comunes al `PUT` de producto, categoria
 * y grupo de tallas:
 *
 * - `cascadeKeys`: al cambiar `key`, renombra tambien lo que se deriva de ella
 *   (ver KeyRenameService). Default false.
 * - `dryRun`: no guarda nada; responde la vista previa del renombrado.
 */
trait ValidatesKeyCascade
{
    /**
     * @return array<string, string>
     */
    protected function keyCascadeRules(): array
    {
        return [
            'cascadeKeys' => 'sometimes|boolean',
            'dryRun'      => 'sometimes|boolean',
        ];
    }

    public function cascadeKeys(): bool
    {
        return $this->boolean('cascadeKeys');
    }

    public function isDryRun(): bool
    {
        return $this->boolean('dryRun');
    }
}
