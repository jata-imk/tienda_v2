<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::exists()) {
            return;
        }

        $administrator = UserType::where('code', UserRole::Administrator->value)->firstOrFail();

        User::create([
            'id_user_type' => $administrator->id,
            'first_name' => 'Administrador',
            'last_name' => 'Sistema',
            'user_name' => 'admin',
            'email' => 'admin@tienda.local',
            'password' => Hash::make('admin'),
            'status' => 'active',
        ]);
    }
}
