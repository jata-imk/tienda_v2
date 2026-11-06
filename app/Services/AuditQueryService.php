<?php

namespace App\Services;

use App\Http\Requests\Audit\AuditQueryRequest;
use App\Services\Concerns\AppliesGridConditions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditQueryService
{
    use AppliesGridConditions;

    public function index(array $filters, array $extra = [], ?int $eventId = null): array
    {
        $query = DB::table($eventId === null ? 'audit_events' : 'audit_changes');
        if ($eventId !== null) {
            $query->where('audit_event_id', $eventId);
        }
        $where = $filters['w'] ?? [];
        if (array_is_list($where)) {
            foreach ($where as &$condition) {
                $condition['value'] = $this->normalizeValue($condition['column'], $condition['value'] ?? null);
            }
            unset($condition);
            $this->applyConditions($query, $where);
        } else {
            foreach ($where as $field => $value) {
                $query->where(Str::snake($field), $this->normalizeValue(Str::snake($field), $value));
            }
        }
        if ($eventId === null) {
            foreach (['dateFrom' => '>=', 'dateTo' => '<='] as $field => $operator) {
                if (isset($extra[$field])) {
                    $date = CarbonImmutable::parse($extra[$field], config('app.timezone'));
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $extra[$field])) {
                        $date = $field === 'dateTo' ? $date->endOfDay() : $date->startOfDay();
                    }
                    $query->where('occurred_at', $operator, $date->utc()->format('Y-m-d H:i:s.u'));
                }
            }
            // La pareja se evalúa sobre la misma entidad, principal o afectada.
            if (isset($extra['entityType']) || isset($extra['entityId'])) {
                $query->where(function ($scope) use ($extra) {
                    $scope->where(function ($main) use ($extra) {
                        foreach (['entityType' => 'entity_type', 'entityId' => 'entity_id'] as $input => $column) {
                            if (isset($extra[$input])) {
                                $main->where("audit_events.$column", (string) $extra[$input]);
                            }
                        }
                    })->orWhereExists(function ($change) use ($extra) {
                        $change->selectRaw('1')->from('audit_changes')->whereColumn('audit_changes.audit_event_id', 'audit_events.id');
                        foreach (['entityType' => 'entity_type', 'entityId' => 'entity_id'] as $input => $column) {
                            if (isset($extra[$input])) {
                                $change->where("audit_changes.$column", (string) $extra[$input]);
                            }
                        }
                    });
                });
            }
        }
        if (isset($filters['f'])) {
            $query->select(array_unique(['id', ...$filters['f']]));
        } elseif ($eventId === null) {
            $query->select(array_map(Str::snake(...), AuditQueryRequest::EVENT_FIELDS));
        }
        $order = $filters['o'] ?? ['column' => $eventId === null ? 'occurred_at' : 'id', 'direction' => 'desc'];
        $query->orderBy($order['column'], $order['direction']);
        if ($order['column'] !== 'id') {
            $query->orderByDesc('id');
        }
        $page = $filters['p']['page'] ?? 1;
        $size = $filters['p']['per_page'] ?? 15;
        $totalCount = $filters['totalCount'] ?? true;
        $result = $totalCount ? $query->paginate($size, ['*'], 'page', $page) : $query->simplePaginate($size, ['*'], 'page', $page);
        $data = ['items' => array_map($this->resource(...), $result->items()), 'page' => $result->currentPage(), 'perPage' => $size, 'hasMore' => $result->hasMorePages()];
        if ($totalCount) {
            $data['totalCount'] = $result->total();
            $data['summary'] = [$result->total()];
        }

        return $data;
    }

    public function show(int $id): ?array
    {
        $event = DB::table('audit_events')->find($id);

        return $event ? $this->resource($event) : null;
    }

    private function normalizeValue(string $column, mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeValue($column, $item), $value);
        }

        return match ($column) {
            'entity_id' => (string) $value,
            'occurred_at' => CarbonImmutable::parse($value, config('app.timezone'))->utc()->format('Y-m-d H:i:s.u'),
            default => $value,
        };
    }

    private function resource(object $row): array
    {
        $result = [];
        foreach ((array) $row as $key => $value) {
            if (in_array($key, ['before_values', 'after_values', 'metadata'], true)) {
                $value = $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            } elseif ($key === 'occurred_at') {
                $value = CarbonImmutable::parse($value, 'UTC')->toISOString();
            }
            $result[Str::camel($key)] = $value;
        }

        return $result;
    }
}
