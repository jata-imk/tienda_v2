<?php

namespace App\DTOs\Usuario;

readonly class CrearUsuarioDTO
{
    public function __construct(
        public string $name,
        public string $firstName,
        public string $lastName,
        public string $username,
        public string $email,
        public string $password,
        public int    $userTypeId,
        public string $status = 'activo',
    ) {}
}
