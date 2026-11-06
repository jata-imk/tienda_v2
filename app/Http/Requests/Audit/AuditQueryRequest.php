<?php

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AuditQueryRequest extends FormRequest
{
    public const EVENT_FIELDS = ['id', 'requestId', 'module', 'action', 'outcome', 'actorId', 'actorName', 'roleCode', 'roleName', 'entityType', 'entityId', 'source', 'occurredAt'];

    public const CHANGE_FIELDS = ['id', 'auditEventId', 'entityType', 'entityId', 'operation'];

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
        return [
            'p' => 'sometimes|array:page,per_page,r,s',
            'p.page' => 'sometimes|integer|min:'.($this->isMethod('GET') ? '1' : '0'),
            'p.r' => 'sometimes|integer|min:0',
            'p.per_page' => 'sometimes|integer|min:1|max:100',
            'p.s' => 'sometimes|integer|min:1|max:100',
            'f' => 'sometimes|array|min:1|max:20',
            'f.*' => ['string', Rule::in($this->fields())],
            'o' => 'sometimes|array:column,direction,field,type',
            'o.column' => ['sometimes', 'string', Rule::in($this->fields())],
            'o.field' => ['sometimes', 'string', Rule::in($this->fields())],
            'o.direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'o.type' => ['sometimes', Rule::in(['asc', 'desc'])],
            'w' => 'sometimes|array|max:50',
            'totalCount' => ['sometimes', function ($attribute, $value, $fail) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                    $fail('totalCount debe ser booleano.');
                }
            }],
            'dateFrom' => 'sometimes|date',
            'dateTo' => 'sometimes|date|after_or_equal:dateFrom',
            'entityType' => 'sometimes|string|max:64',
            'entityId' => ['sometimes', function ($attribute, $value, $fail) {
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
                if (! is_array($condition) || array_diff(array_keys($condition), ['f', 'ao', 'v', 'lo']) !== []
                    || ! in_array($condition['f'] ?? null, $this->fields(), true)
                    || ! in_array($condition['ao'] ?? '==', ['==', '!=', '<>', '>', '>=', '<', '<=', 'contains', 'notcontains', 'startswith', 'endswith', 'in', 'notin', 'between'], true)
                    || (($condition['f'] ?? '') === 'occurredAt' && in_array($condition['ao'] ?? '==', ['contains', 'notcontains', 'startswith', 'endswith'], true))
                    || ! in_array($condition['lo'] ?? '&&', ['&&', '||'], true)) {
                    $validator->errors()->add("w.$index", 'Condición de filtro no permitida.');

                    continue;
                }
                $value = $condition['v'] ?? null;
                $set = in_array($condition['ao'] ?? '==', ['in', 'notin', 'between'], true);
                $values = $set && is_array($value) ? $value : [$value];
                if (($set && (! is_array($value) || count($value) < 1 || count($value) > 100))
                    || (($condition['ao'] ?? '') === 'between' && count($values) !== 2)) {
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
