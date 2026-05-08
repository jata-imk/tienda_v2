<?php

namespace App\DTOs\User;

readonly class UpdateUserDTO
{
    public function __construct(
        public ?int    $userTypeId,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $userName,
        public ?string $email,
        public ?string $password,
        public ?string $status,
    ) {}
}
