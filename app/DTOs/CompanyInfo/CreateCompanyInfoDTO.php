<?php

namespace App\DTOs\CompanyInfo;

readonly class CreateCompanyInfoDTO
{
    public function __construct(
        public array $fields,
    ) {}
}
