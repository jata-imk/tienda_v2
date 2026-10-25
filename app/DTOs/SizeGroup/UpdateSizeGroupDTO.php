<?php

namespace App\DTOs\SizeGroup;

readonly class UpdateSizeGroupDTO
{
    public function __construct(
        public ?string $key,
        public ?string $name,
        public ?string $description,
        public ?string $status,
        /** `key` vino explicitamente en null: se borra (null en `$key` = no tocar). */
        public bool    $clearKey = false,
        /** Renombrar en cascada lo derivado de la clave (ver KeyRenameService). */
        public bool    $cascadeKeys = false,
    ) {}
}
