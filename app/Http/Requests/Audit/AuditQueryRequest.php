<?php

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida la consulta de auditoría con el mismo contrato de grid que el resto de los
 * endpoints (`TranslatesGridFilters`): `f`, `p`, `o`, `w` vacíos o null equivalen a
 * omitirlos, las llaves desconocidas se ignoran, `direction`/`lo` no distinguen
 * mayúsculas y los operadores se interpretan igual que `mapGridOperator()`.
 *
 * Solo se rechaza (422) lo que en otro grid sería un error de SQL, una fuga de
 * columnas o una consulta sin límite: campos fuera de la lista blanca, tamaño de
 * página fuera de 1–100, fechas inválidas y valores de filtro mal formados.
 */
class AuditQueryRequest extends FormRequest
{
    public const EVENT_FIELDS = ['id', 'requestId', 'module', 'action', 'outcome', 'actorId', 'actorName', 'roleCode', 'roleName', 'entityType', 'entityId', 'source', 'occurredAt'];

    public const CHANGE_FIELDS = ['id', 'auditEventId', 'entityType', 'entityId', 'operation'];

    /** Operadores que `mapGridOperator()` resuelve con una lista de valores. */
    private const SET_OPERATORS = ['in', 'anyof', 'notin', 'not in', 'noneof'];

    /** Operadores de texto (LIKE): no aplican a columnas de fecha. */
    private const TEXT_OPERATORS = ['contains', 'notcontains', 'startswith', 'endswith'];

    public function authorize(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return $this->routeIs('audit-events.changes') ? self::CHANGE_FIELDS : self::EVENT_FIELDS;
    }

    public function rules(): array
    {
        $direction = function ($attribute, $value, $fail) {
            if (! is_string($value) || ! in_array(strtolower($value), ['asc', 'desc'], true)) {
                $fail("La dirección de ordenamiento debe ser 'asc' o 'desc'.");
            }
        };

        return [
            'p' => 'sometimes|nullable|array',
            'p.page' => 'sometimes|nullable|integer|min:'.($this->isMethod('GET') ? '1' : '0'),
            'p.r' => 'sometimes|nullable|integer|min:0',
            'p.per_page' => 'sometimes|nullable|integer|min:1|max:100',
            'p.s' => 'sometimes|nullable|integer|min:1|max:100',
            'f' => 'sometimes|nullable|array|max:20',
            'f.*' => ['string', Rule::in($this->fields())],
            'o' => 'sometimes|nullable|array',
            'o.column' => ['sometimes', 'nullable', 'string', Rule::in($this->fields())],
            'o.field' => ['sometimes', 'nullable', 'string', Rule::in($this->fields())],
            'o.direction' => ['sometimes', 'nullable', $direction],
            'o.type' => ['sometimes', 'nullable', $direction],
            'w' => 'sometimes|nullable|array|max:50',
            'dateFrom' => 'sometimes|nullable|date',
            'dateTo' => $this->filled('dateFrom')
                ? 'sometimes|nullable|date|after_or_equal:dateFrom'
                : 'sometimes|nullable|date',
            'entityType' => 'sometimes|nullable|string|max:64',
            'entityId' => ['sometimes', 'nullable', function ($attribute, $value, $fail) {
                if ((! is_string($value) && ! is_int($value)) || mb_strlen((string) $value) > 64) {
                    $fail('entityId debe ser un identificador de hasta 64 caracteres.');
                }
            }],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $where = $this->input('w', []);
            if (! is_array($where)) {
                return;
            }
            if (! array_is_list($where)) {
                foreach ($where as $field => $value) {
                    if (! in_array($field, $this->fields(), true) || ! is_scalar($value) || mb_strlen((string) $value) > 512
                        || ($field === 'occurredAt' && validator(['date' => $value], ['date' => 'date'])->fails())) {
                        $validator->errors()->add('w', 'Campo o valor de filtro no permitido.');
                    }
                }

                return;
            }
            foreach ($where as $index => $condition) {
                // Llaves extra en la condición se ignoran, igual que en los demás grids.
                $ao = is_array($condition) ? ($condition['ao'] ?? '==') : null;
                $lo = is_array($condition) ? ($condition['lo'] ?? '&&') : null;
                if (! is_array($condition)
                    || ! in_array($condition['f'] ?? null, $this->fields(), true)
                    || ! is_string($ao)
                    || ($lo !== null && ! is_scalar($lo))
                    || (($condition['f'] ?? '') === 'occurredAt' && in_array(strtolower($ao), self::TEXT_OPERATORS, true))) {
                    $validator->errors()->add("w.$index", 'Condición de filtro no permitida.');

                    continue;
                }
                $ao = strtolower($ao);
                $value = $condition['v'] ?? null;
                // Igual que mapGridOperator(): un operador desconocido con array actúa como `in`.
                $known = in_array($ao, ['==', '=', '!=', '<>', '>', '>=', '<', '<=', 'between', ...self::TEXT_OPERATORS, ...self::SET_OPERATORS], true);
                $set = in_array($ao, self::SET_OPERATORS, true) || (! $known && is_array($value));
                $values = ($set || $ao === 'between') && is_array($value) ? $value : [$value];
                if (($set && (! is_array($value) || count($value) < 1 || count($value) > 100))
                    || ($ao === 'between' && (! is_array($value) || count($value) !== 2))) {
                    $validator->errors()->add("w.$index.v", 'Valor de filtro no válido.');
                }
                foreach ($values as $item) {
                    if (! is_scalar($item) || mb_strlen((string) $item) > 512
                        || (($condition['f'] ?? '') === 'occurredAt' && validator(['date' => $item], ['date' => 'date'])->fails())) {
                        $validator->errors()->add("w.$index.v", 'Valor de filtro no válido.');
                    }
                }
            }
        }];
    }
}
