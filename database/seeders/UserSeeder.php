<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'id_user_type' => 1,
            'first_name'   => 'Suriel',
            'last_name'    => 'Dzul Dzul',
            'user_name'    => 'suriel.dzul',
            'email'        => 'dzulsuriel@gmail.com',
            'password'     => Hash::make('suriel2024'),
            'status'       => 'active',
        ]);
    }
}
