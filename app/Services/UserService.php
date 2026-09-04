<?php

namespace App\Services;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Concerns\AppliesGridConditions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    use AppliesGridConditions;

    public function index(array $filters = []): array|Collection
    {
        $query = User::with('userType');

        if (! empty($filters['w'])) {
            if (array_is_list($filters['w'])) {
                $this->applyConditions($query, $filters['w'], [$this, 'applyUserCondition']);
            } else {
                foreach ($filters['w'] as $column => $value) {
                    $query->where($column, $value);
                }
            }
        }

        if (! empty($filters['f'])) {
            $query->select($filters['f']);
        }

        if (! empty($filters['o'])) {
            $query->orderBy($filters['o']['column'] ?? 'id', $filters['o']['direction'] ?? 'asc');
        }

        $totalCount = isset($filters['totalCount'])
            ? (bool) filter_var($filters['totalCount'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if (! empty($filters['p'])) {
            $page = (int) ($filters['p']['page'] ?? 1);
            $perPage = (int) ($filters['p']['per_page'] ?? 15);
            $items = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => $items->items(),
                'total' => $totalCount ? $items->total() : null,
                'page' => $items->currentPage(),
                'pages' => $items->lastPage(),
            ];
        }

        $items = $query->get();

        if ($totalCount) {
            return ['items' => $items, 'total' => $items->count()];
        }

        return $items;
    }

    public function show(int $id): ?User
    {
        return User::with('userType')->find($id);
    }

    public function store(CreateUserDTO $dto): User
    {
        $user = User::create([
            'id_user_type' => $dto->userTypeId,
            'first_name' => $dto->firstName,
            'last_name' => $dto->lastName,
            'user_name' => $dto->userName,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'status' => $dto->status,
        ]);

        return $user->fresh('userType');
    }

    public function update(int $id, UpdateUserDTO $dto): ?User
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = User::find($id);

            if (! $user) {
                return null;
            }

            $previousRoleId = $user->id_user_type;
            $fields = array_filter([
                'id_user_type' => $dto->userTypeId,
                'first_name' => $dto->firstName,
                'last_name' => $dto->lastName,
                'user_name' => $dto->userName,
                'email' => $dto->email,
                'status' => $dto->status,
                'password' => $dto->password !== null ? Hash::make($dto->password) : null,
            ], fn ($v) => $v !== null);

            $user->update($fields);

            if ($user->status !== 'active' || $previousRoleId !== $user->id_user_type || $dto->password !== null) {
                $this->revokeSessions($user);
            }

            return $user->fresh('userType');
        });
    }

    public function destroy(int $id): bool
    {
        $user = User::find($id);

        if (! $user) {
            return false;
        }

        DB::transaction(function () use ($user) {
            $user->update(['status' => 'inactive']);
            $this->revokeSessions($user);
        });

        return true;
    }

    private function revokeSessions(User $user): void
    {
        UserSession::where('id_user', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Resuelve una condicion de usuario soportando relaciones y campo virtual `search`.
     *
     * @param  mixed  $query
     * @param  array<string, mixed>  $cond
     */
    public function applyUserCondition($query, array $cond, string $boolean): void
    {
        $or = $boolean === 'or';
        $column = $cond['column'];

        if ($column === 'search') {
            $value = trim(trim((string) ($cond['value'] ?? ''), '%'));
            if ($value === '') {
                return;
            }

            $likeVal = '%'.addcslashes($value, '%_\\').'%';
            $method = $or ? 'orWhere' : 'where';

            $query->$method(function ($q) use ($likeVal) {
                $q->where('first_name', 'like', $likeVal)
                    ->orWhere('last_name', 'like', $likeVal)
                    ->orWhere('user_name', 'like', $likeVal)
                    ->orWhere('email', 'like', $likeVal)
                    ->orWhereHas('userType', fn ($uq) => $uq->where('name', 'like', $likeVal));
            });

            return;
        }

        if ($column === 'user_type') {
            $operator = $cond['operator'] ?? '=';
            $value = $cond['value'] ?? null;
            $method = $or ? 'orWhereHas' : 'whereHas';

            $query->$method('userType', function ($uq) use ($operator, $value) {
                $uq->where('name', $operator, $value);
            });

            return;
        }

        $this->applyGridCondition($query, $cond, $boolean);
    }
}
