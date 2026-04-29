<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Contraseña de ejemplo: suriel2024
        Usuario::create([
            'user_type_id' => 1,
            'name'         => 'Suriel',
            'first_name'   => 'Dzul',
            'last_name'    => 'Dzul',
            'username'     => 'suriel.dzul',
            'email'        => 'dzulsuriel@gmail.com',
            'password'     => Hash::make('suriel2024'),
            'status'       => 'activo',
        ]);
    }
}
