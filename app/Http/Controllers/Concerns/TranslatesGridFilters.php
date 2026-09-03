<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Extrae y normaliza los filtros de consulta ya sea desde query params (GET)
     * o desde el body JSON (POST).
     *
     * @return array<string, mixed>
     */
    protected function extractGridFilters(Request $request): array
    {
        $payload = $request->isMethod('GET')
            ? $request->query()
            : ($request->json()->all() ?: $request->all());

        $filters = [];

        // Pagination — p.page/p.r = row offset (0-indexed en DevExtreme); p.per_page/p.s = page size
        if (!empty($payload['p'])) {
            $p       = $payload['p'];
            $perPage = (int) ($p['per_page'] ?? $p['s'] ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;

            if (isset($p['r'])) {
                $offset = (int) $p['r'];
                $page   = max(1, intdiv($offset, $perPage) + 1);
            } elseif (!$request->isMethod('GET') && isset($p['page'])) {
                $offset = (int) $p['page'];
                $page   = max(1, intdiv($offset, $perPage) + 1);
            } else {
                $page   = max(1, (int) ($p['page'] ?? 1));
            }

            $filters['p'] = [
                'per_page' => $perPage,
                'page'     => $page,
            ];
        }

        // Field selection — convierte camelCase a snake_case
        if (isset($payload['f']) && is_array($payload['f']) && count($payload['f']) > 0) {
            $filters['f'] = array_values(array_map(fn($f) => Str::snake($f), $payload['f']));
        }

        // Ordering — column/direction (Formato B) o field/type (Formato A)
        if (!empty($payload['o'])) {
            $filters['o'] = [
                'column'    => Str::snake($payload['o']['column'] ?? $payload['o']['field'] ?? 'id'),
                'direction' => strtolower($payload['o']['direction'] ?? $payload['o']['type'] ?? 'asc'),
            ];
        }

        // Where — asociativo plano o condiciones DevExtreme
        if (!empty($payload['w'])) {
            $w = $payload['w'];
            $filters['w'] = array_is_list($w) ? $this->translateGridFilters($w) : $w;
        }

        // totalCount
        if (array_key_exists('totalCount', $payload)) {
            $filters['totalCount'] = filter_var($payload['totalCount'], FILTER_VALIDATE_BOOLEAN);
        }

        return $filters;
    }

    /**
     * Envoltorio estandar para respuestas de grid con items y totalCount.
     */
    protected function gridResponse(string $message, mixed $result, string $resourceClass): JsonResponse
    {
        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query($message, $resourceClass::collection($items), $total);
    }

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
