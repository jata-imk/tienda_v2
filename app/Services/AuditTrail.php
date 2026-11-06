<?php

namespace App\Services;

use App\Exceptions\AuditUnavailableException;
use App\Models\User;
use App\Support\AuditActions;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditTrail
{
    private int $depth = 0;

    private array $changes = [];

    private array $actor = [];

    private array $metadata = [];

    private ?string $entityType = null;

    private ?string $entityId = null;

    private ?Request $request = null;

    private ?string $action = 'system.changed';

    private string $module = 'security';

    private ?string $requestId = null;

    private bool $recorded = false;

    public function initialize(Request $request): void
    {
        $definition = AuditActions::forRequest($request);
        $this->request = $request;
        $this->requestId = (string) Str::uuid();
        $this->module = $definition['module'];
        $this->action = $definition['action'];
        $this->entityType = $definition['entity'];
        $this->entityId = null;
        $this->actor = $this->metadata = $this->changes = [];
        $this->recorded = false;
        $request->attributes->set('auditRequestId', $this->requestId);
        foreach (['image' => 'product_image', 'variant' => 'product_variant', 'order' => 'order', 'product' => 'product', 'user' => 'user', 'category' => 'category', 'color' => 'color', 'size' => 'size', 'size_group' => 'size_group', 'currency' => 'currency'] as $parameter => $entity) {
            $id = $request->route()?->parameter($parameter);
            if ($entity === $this->entityType && is_scalar($id)) {
                $this->entityId = substr((string) $id, 0, 64);
            }
        }
        if ($this->action === 'auth.login') {
            $name = $request->input('userName', '');
            $this->metadata['attemptedUserName'] = is_string($name) ? mb_substr($name, 0, 255) : null;
        }
    }

    public function identify(User $user): void
    {
        $user->loadMissing('userType');
        $this->actor = ['actor_id' => $user->id, 'actor_name' => $user->user_name, 'role_code' => $user->userType?->code, 'role_name' => $user->userType?->name];
    }

    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::uuid();
    }

    public function subject(string $entity, int|string $id): void
    {
        $this->entityType = $entity;
        $this->entityId = (string) $id;
    }

    public function metadata(array $values): void
    {
        $this->metadata = array_replace($this->metadata, $this->safe($values));
    }

    private function enabled(): bool
    {
        return $this->action !== null && in_array($this->module, config('audit.modules', []), true);
    }

    /** No lee la BD antes del callback: conserva el primer lock de claves/folios. */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        if ($this->depth > 0) {
            $checkpoint = [$this->changes, $this->entityType, $this->entityId, $this->metadata];
            try {
                return DB::transaction($callback, $attempts);
            } catch (Throwable $exception) {
                [$this->changes, $this->entityType, $this->entityId, $this->metadata] = $checkpoint;
                throw $exception;
            }
        }
        if (! $this->enabled()) {
            return DB::transaction($callback, $attempts);
        }

        $subject = [$this->entityType, $this->entityId];
        try {
            return DB::transaction(function () use ($callback, $subject) {
                $this->changes = [];
                $this->recorded = false;
                [$this->entityType, $this->entityId] = $subject;
                $this->depth++;
                try {
                    $result = $callback();
                    if ($this->changes === [] && ($result === null || $result === false || in_array($result, ['not_found', 'in_use'], true))) {
                        return $result;
                    }
                    $this->persist($this->changes === [] && ! $this->isActionOnly() ? 'no_change' : 'success');

                    return $result;
                } finally {
                    $this->depth--;
                }
            }, $attempts);
        } catch (Throwable $exception) {
            $this->recorded = false;
            [$this->entityType, $this->entityId] = $subject;
            throw $exception;
        } finally {
            $this->changes = [];
        }
    }

    /** Entrada para comandos/jobs; el actor de sistema es null por defecto. */
    public function run(string $module, string $action, Closure $callback, int $attempts = 1): mixed
    {
        if ($this->depth > 0) {
            return $this->transaction($callback, $attempts);
        }
        $previous = [$this->module, $this->action, $this->entityType, $this->entityId, $this->metadata, $this->recorded];
        [$this->module, $this->action, $this->entityType, $this->entityId, $this->metadata, $this->recorded] = [$module, $action, null, null, [], false];
        try {
            return $this->transaction($callback, $attempts);
        } catch (Throwable $exception) {
            $this->finish(500, $exception);
            throw $exception;
        } finally {
            [$this->module, $this->action, $this->entityType, $this->entityId, $this->metadata, $this->recorded] = $previous;
        }
    }

    public function modelChange(Model $model, string $operation): void
    {
        if ($this->depth === 0 || ! isset(config('audit.models')[$model::class])) {
            return;
        }
        [$entity] = config('audit.models')[$model::class];
        $before = $operation === 'created' ? null : $this->snapshot($model, $model->getRawOriginal());
        $after = $operation === 'deleted' ? null : $this->snapshot($model, $model->getAttributes());
        if ($model instanceof User && $operation === 'updated' && $model->wasChanged('password')) {
            $before['passwordChanged'] = false;
            $after['passwordChanged'] = true;
        }
        $this->record($entity, $model->getKey(), $operation, $before, $after);
    }

    public function snapshot(Model $model, array $attributes): array
    {
        [, $fields] = config('audit.models')[$model::class];
        $copy = clone $model;
        $copy->setRawAttributes($attributes, true);
        $values = [];
        foreach (explode(',', $fields) as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }
            $value = $copy->getAttribute($field);
            if ($field === 'logo' && $value !== null) {
                $value = ['sha256' => hash('sha256', $value), 'encodedBytes' => strlen($value)];
            }
            $values[Str::camel($field)] = $this->safe($value);
        }

        return $values;
    }

    private function safe(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/password(?!Changed)|token|authorization|secret/i', $key)) {
                    continue;
                }
                $result[$key] = $this->safe($item);
            }

            return $result;
        }

        return is_scalar($value) || $value === null ? $value : '[unsupported]';
    }

    public function record(string $entity, int|string $id, string $operation, ?array $before, ?array $after): void
    {
        if ($this->depth === 0) {
            return;
        }
        $before = $this->safe($before);
        $after = $this->safe($after);
        $key = $entity.':'.$id;
        if (isset($this->changes[$key])) {
            $previous = $this->changes[$key];
            if ($previous['operation'] === 'created' && $operation === 'deleted') {
                unset($this->changes[$key]);

                return;
            }
            $before = $previous['before_values'] === null ? null : $previous['before_values'] + ($before ?? []);
            if ($previous['after_values'] !== null && $after !== null) {
                $after = array_replace($previous['after_values'], $after);
            }
            $operation = $previous['operation'] === 'created' ? 'created' : $operation;
        }
        if ($before === $after) {
            unset($this->changes[$key]);

            return;
        }
        $this->changes[$key] = ['entity_type' => $entity, 'entity_id' => (string) $id, 'operation' => $operation, 'before_values' => $before, 'after_values' => $after];
        if ($this->entityType === null || ($this->entityType === $entity && $this->entityId === null)) {
            $this->subject($entity, $id);
        }
    }

    /** Captura por fila manteniendo el UPDATE masivo original. */
    public function bulkUpdate($query, array $attributes): int
    {
        if ($this->depth === 0) {
            return $query->update($attributes);
        }
        $rows = (clone $query)->orderBy('id')->lockForUpdate()->get();
        $count = $query->update($attributes);
        foreach ($rows as $row) {
            $before = $this->snapshot($row, $row->getAttributes());
            $row->forceFill($attributes);
            $this->record(config('audit.models')[$row::class][0], $row->id, 'updated', $before, $this->snapshot($row, $row->getAttributes()));
        }

        return $count;
    }

    public function bulkDelete($query): int
    {
        if ($this->depth === 0) {
            return $query->delete();
        }
        $rows = (clone $query)->orderBy('id')->lockForUpdate()->get();
        $count = $query->delete();
        foreach ($rows as $row) {
            $this->record(config('audit.models')[$row::class][0], $row->id, 'deleted', $this->snapshot($row, $row->getAttributes()), null);
        }

        return $count;
    }

    private function isActionOnly(): bool
    {
        return str_starts_with($this->action ?? '', 'auth.') || $this->module === 'documents';
    }

    /** Se ejecuta dentro de la misma transacción que los datos de negocio. */
    protected function persist(string $outcome): void
    {
        try {
            $id = DB::table('audit_events')->insertGetId([
                'request_id' => $this->requestId(), 'module' => $this->module, 'action' => $this->action,
                'outcome' => $outcome, 'actor_id' => $this->actor['actor_id'] ?? null,
                'actor_name' => $this->actor['actor_name'] ?? null, 'role_code' => $this->actor['role_code'] ?? null,
                'role_name' => $this->actor['role_name'] ?? null, 'entity_type' => $this->entityType, 'entity_id' => $this->entityId,
                'source' => $this->request ? 'api' : 'system', 'route' => $this->request?->route()?->uri(),
                'method' => $this->request?->method(), 'ip' => $this->request?->ip(),
                'user_agent' => $this->request ? mb_substr($this->request->userAgent() ?? '', 0, 512) : null,
                'metadata' => $this->json($this->metadata), 'occurred_at' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
            $rows = [];
            foreach ($this->changes as $change) {
                $before = $change['before_values'];
                $after = $change['after_values'];
                if ($before !== null && $after !== null) {
                    $keys = array_keys(array_filter($after, fn ($value, $key) => ! array_key_exists($key, $before) || $value !== $before[$key], ARRAY_FILTER_USE_BOTH));
                    $before = array_intersect_key($before, array_flip($keys));
                    $after = array_intersect_key($after, array_flip($keys));
                    if ($after === []) {
                        continue;
                    }
                }
                $rows[] = array_replace($change, ['audit_event_id' => $id, 'before_values' => $this->json($before), 'after_values' => $this->json($after)]);
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('audit_changes')->insert($chunk);
            }
            $this->recorded = true;
        } catch (Throwable $exception) {
            $this->fallback('audit.write_failed', $exception);
            throw new AuditUnavailableException('No se pudo guardar la auditoría. La operación fue cancelada.', previous: $exception);
        }
    }

    private function json(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function finish(int $status, ?Throwable $exception = null): void
    {
        if ($status === 401 || $status === 403) {
            $this->action = str_starts_with($this->action ?? '', 'auth.') ? $this->action : ($status === 403 ? 'security.access_denied' : 'security.unauthenticated');
            $this->module = 'security';
        }
        if ($this->recorded || ! $this->enabled()) {
            return;
        }
        $this->changes = [];
        $this->metadata['httpStatus'] = $status;
        if ($exception) {
            $this->metadata['exceptionType'] = $exception::class;
        }
        try {
            DB::transaction(fn () => $this->persist($status >= 500 ? 'failed' : ($status >= 400 ? 'rejected' : ($this->isActionOnly() ? 'success' : 'no_change'))));
        } catch (AuditUnavailableException $failure) {
            if ($status < 400) {
                throw $failure;
            }
        }
    }

    public function fallback(string $message, Throwable $exception): void
    {
        try {
            Log::channel('api_requests')->critical($message, ['requestId' => $this->requestId(), 'exceptionType' => $exception::class]);
        } catch (Throwable) {
            error_log($message.' requestId='.$this->requestId());
        }
    }

    public function logRequest(int $status, float $durationMs): void
    {
        try {
            Log::channel('api_requests')->info('api.request', [
                'requestId' => $this->requestId(), 'method' => $this->request?->method(),
                'route' => $this->request?->route()?->uri(), 'actorId' => $this->actor['actor_id'] ?? null,
                'status' => $status, 'durationMs' => round($durationMs, 2),
            ]);
        } catch (Throwable $exception) {
            $this->fallback('api.request_log_failed', $exception);
        }
    }
}
