<?php

namespace App\Services;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function index(): Collection
    {
        return User::with('userType')->get();
    }

    public function show(int $id): ?User
    {
        return User::with('userType')->find($id);
    }

    public function store(CreateUserDTO $dto): User
    {
        $user = User::create([
            'id_user_type' => $dto->userTypeId,
            'first_name'   => $dto->firstName,
            'last_name'    => $dto->lastName,
            'user_name'    => $dto->userName,
            'email'        => $dto->email,
            'password'     => Hash::make($dto->password),
            'status'       => $dto->status,
        ]);

        return $user->fresh('userType');
    }

    public function update(int $id, UpdateUserDTO $dto): ?User
    {
        $user = User::find($id);

        if (!$user) {
            return null;
        }

        $fields = array_filter([
            'id_user_type' => $dto->userTypeId,
            'first_name'   => $dto->firstName,
            'last_name'    => $dto->lastName,
            'user_name'    => $dto->userName,
            'email'        => $dto->email,
            'status'       => $dto->status,
            'password'     => $dto->password !== null ? Hash::make($dto->password) : null,
        ], fn($v) => $v !== null);

        $user->update($fields);

        return $user->fresh('userType');
    }

    public function destroy(int $id): bool
    {
        $user = User::find($id);

        if (!$user) {
            return false;
        }

        $user->update(['status' => 'inactive']);

        return true;
    }
}
