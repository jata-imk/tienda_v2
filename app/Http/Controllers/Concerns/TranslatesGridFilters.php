<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

/**
 * Traduce las condiciones que manda el `filterRow` / `filterBuilder` de
 * DevExtreme (`{f, ao, v, lo}`) al formato que aplican los Services
 * (`{column, operator, value, logic}`).
 *
 * Los operadores de texto (`contains`, `startswith`, ...) se convierten a
 * `LIKE` con el comodin correspondiente; `between` y los operadores de conjunto
 * (`in` / `notin`) viajan como una sola condicion con la lista de valores.
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
            // `between` no se parte en dos condiciones: partirlo rompe la
            // agrupacion cuando la condicion llega con `lo: '||'`.
            'between'     => is_array($value) && count($value) === 2
                ? ['between', array_values($value)]
                : ['=', $value],
            // Operadores de conjunto: viajan como una lista de valores y los
            // Services los resuelven con `whereIn` / `whereNotIn`. `in` es el
            // nombre del contrato; `anyof` / `noneof` son los alias que manda el
            // filterBuilder de DevExtreme. El sentinel no es un operador SQL.
            'in', 'anyof'               => ['in', array_values((array) $value)],
            'notin', 'not in', 'noneof' => ['not in', array_values((array) $value)],
            // Un operador desconocido con valor escalar cae en `=` (comportamiento
            // historico), pero con un array NO: Laravel colapsaria la lista a su
            // primer elemento (`Builder::flattenValue`) y el filtro devolveria
            // resultados incompletos en silencio. Se trata como `in`.
            default       => is_array($value)
                ? ['in', array_values($value)]
                : ['=', $value],
        };
    }

    /** Evita que `%` y `_` dentro del texto buscado actuen como comodines. */
    private function escapeLike(mixed $value): string
    {
        return addcslashes((string) $value, '%_\\');
    }
}
