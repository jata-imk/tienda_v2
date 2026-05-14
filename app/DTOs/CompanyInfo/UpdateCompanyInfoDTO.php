<?php

namespace App\DTOs\CompanyInfo;

readonly class UpdateCompanyInfoDTO
{
    public function __construct(
        public array $fields,
    ) {}
}
