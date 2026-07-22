<?php

namespace App\Models\Concerns;

/**
 * Deja `updated_at` en null durante el alta: solo se llena cuando el registro
 * se modifica. Eloquent iguala `updated_at` a `created_at` al insertar dentro
 * de `updateTimestamps()`, que corre despues del evento `creating`, por eso se
 * sobreescribe el metodo en lugar de usar un hook.
 */
trait NullsUpdatedAtOnCreate
{
    public function updateTimestamps(): static
    {
        parent::updateTimestamps();

        $column = $this->getUpdatedAtColumn();

        if (!$this->exists && $column !== null) {
            $this->setAttribute($column, null);
        }

        return $this;
    }
}
