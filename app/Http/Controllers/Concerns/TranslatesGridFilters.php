<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

/**
 * Traduce las condiciones que manda el `filterRow` / `filterBuilder` de
 * DevExtreme (`{f, ao, v, lo}`) al formato que aplican los Services
 * (`{column, operator, value, logic}`).
 *
 * Los operadores de texto (`contains`, `startswith`, ...) se convierten a
 * `LIKE` con el comodin correspondiente; `between` se parte en dos
 * condiciones (`>=` y `<=`) para no requerir `whereBetween` en los Services.
 */
trait TranslatesGridFilters
{
    /**
     * @param  array<int, array<string, mixed>> $conditions
     * @return array<int, array<string, mixed>>
     */
    protected function translateGridFilters(array $conditions): array
    {
        $translated = [];

        foreach ($conditions as $condition) {
            $column = Str::snake($condition['f'] ?? '');
            $logic  = strtolower($condition['lo'] ?? '&&') === '||' ? 'or' : 'and';
            $ao     = $condition['ao'] ?? '==';
            $value  = $condition['v'] ?? null;

            if ($ao === 'between' && is_array($value) && count($value) === 2) {
                $translated[] = ['column' => $column, 'operator' => '>=', 'value' => $value[0], 'logic' => $logic];
                $translated[] = ['column' => $column, 'operator' => '<=', 'value' => $value[1], 'logic' => 'and'];

                continue;
            }

            [$operator, $value] = $this->mapGridOperator($ao, $value);

            $translated[] = [
                'column'   => $column,
                'operator' => $operator,
                'value'    => $value,
                'logic'    => $logic,
            ];
        }

        return $translated;
    }

    /**
     * @return array{0: string, 1: mixed}
     */
    private function mapGridOperator(string $ao, mixed $value): array
    {
        return match ($ao) {
            '!=', '<>'    => ['!=', $value],
            '>'           => ['>', $value],
            '>='          => ['>=', $value],
            '<'           => ['<', $value],
            '<='          => ['<=', $value],
            'contains'    => ['like', '%' . $this->escapeLike($value) . '%'],
            'notcontains' => ['not like', '%' . $this->escapeLike($value) . '%'],
            'startswith'  => ['like', $this->escapeLike($value) . '%'],
            'endswith'    => ['like', '%' . $this->escapeLike($value)],
            // `anyof` viaja como una lista de ids; el Service lo resuelve con
            // `whereIn` (no es un operador SQL, por eso el sentinel `in`).
            'anyof'       => ['in', array_values((array) $value)],
            default       => ['=', $value],
        };
    }

    /** Evita que `%` y `_` dentro del texto buscado actuen como comodines. */
    private function escapeLike(mixed $value): string
    {
        return addcslashes((string) $value, '%_\\');
    }
}
