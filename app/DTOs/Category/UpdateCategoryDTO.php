<?php

namespace App\DTOs\Category;

readonly class UpdateCategoryDTO
{
    public function __construct(
        public ?string $key,
        public ?string $name,
        public ?string $description,
        public ?string $status,
        /** Renombrar en cascada lo derivado de la clave (ver KeyRenameService). */
        public bool    $cascadeKeys = false,
    ) {}
}
