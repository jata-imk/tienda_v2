<?php

namespace App\Services\Concerns;

/**
 * Aplica al query builder las condiciones ya normalizadas por
 * `TranslatesGridFilters` (`{column, operator, value, logic}`).
 *
 * El contrato de `POST /{recurso}/query` exige que una condicion con `lo: '&&'`
 * seguida de una o mas con `lo: '||'` forme **un solo grupo OR** que se AND-ea
 * con el resto. Aplicar los `||` planos haria que un `status = active` previo se
 * perdiera por la precedencia de SQL, asi que las condiciones se reparten en
 * bloques y cada bloque de dos o mas se envuelve en un closure.
 */
trait AppliesGridConditions
{
    /**
     * Reparte la lista plana en bloques: cada bloque arranca con una condicion
     * `and` y arrastra las `or` que vengan detras.
     *
     * @param  array<int, array<string, mixed>> $conditions
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function groupGridConditions(array $conditions): array
    {
        $blocks = [];

        foreach ($conditions as $condition) {
            // Un `||` inicial no tiene con quien agruparse: abre bloque propio.
            if ($this->conditionIsOr($condition) && $blocks !== []) {
                $blocks[count($blocks) - 1][] = $condition;

                continue;
            }

            $blocks[] = [$condition];
        }

        return $blocks;
    }

    /**
     * Aplica una condicion suelta. `$boolean` es el conector con la condicion
     * anterior *dentro* del bloque (`and` en la primera, `or` en las demas); si
     * se omite se toma el `logic` de la propia condicion.
     *
     * @param array<string, mixed> $condition
     */
    protected function applyGridCondition($query, array $condition, ?string $boolean = null): void
    {
        $or       = $this->resolveBoolean($condition, $boolean) === 'or';
        $operator = $condition['operator'] ?? '=';
        $value    = $condition['value'] ?? null;

        if ($operator === 'in' || $operator === 'not in') {
            $values = $this->normalizeInValues($value);

            // Lista vacia = el usuario no selecciono nada: la condicion se ignora.
            if ($values === []) {
                return;
            }

            $method = $operator === 'in'
                ? ($or ? 'orWhereIn' : 'whereIn')
                : ($or ? 'orWhereNotIn' : 'whereNotIn');

            $query->$method($condition['column'], $values);

            return;
        }

        if ($operator === 'between' && is_array($value) && count($value) === 2) {
            $query->{$or ? 'orWhereBetween' : 'whereBetween'}($condition['column'], array_values($value));

            return;
        }

        $query->{$or ? 'orWhere' : 'where'}($condition['column'], $operator, $value);
    }

    /** Descarta nulos y cadenas vacias de la lista de un `in` / `not in`. */
    protected function normalizeInValues(mixed $value): array
    {
        return array_values(array_filter(
            (array) $value,
            fn($v) => $v !== null && $v !== '',
        ));
    }

    /** @param array<string, mixed> $condition */
    protected function conditionIsOr(array $condition): bool
    {
        return ($condition['logic'] ?? 'and') === 'or';
    }

    /** @param array<string, mixed> $condition */
    private function resolveBoolean(array $condition, ?string $boolean): string
    {
        if ($boolean !== null) {
            return $boolean;
        }

        return $this->conditionIsOr($condition) ? 'or' : 'and';
    }
}
