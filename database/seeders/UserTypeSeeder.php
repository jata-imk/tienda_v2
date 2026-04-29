<?php

namespace Database\Seeders;

use App\Models\TipoUsuario;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        TipoUsuario::create([
            'type_user' => 'administrador',
            'status'    => 'activo',
        ]);
    }
}
