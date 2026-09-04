<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            UserRole::Administrator->value => 'Administrador',
            UserRole::Seller->value => 'Vendedor',
            UserRole::Warehouse->value => 'Almacén',
        ];

        foreach ($roles as $code => $name) {
            UserType::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'status' => 'active'],
            );
        }
    }
}
