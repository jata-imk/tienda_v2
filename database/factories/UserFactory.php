<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'id_user_type' => UserType::first()?->id ?? 1,
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'user_name'    => fake()->unique()->userName(),
            'email'        => fake()->unique()->safeEmail(),
            'password'     => static::$password ??= Hash::make('password'),
            'status'       => 'active',
        ];
    }
}
