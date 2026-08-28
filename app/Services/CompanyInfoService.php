<?php

namespace App\Services;

use App\DTOs\CompanyInfo\CreateCompanyInfoDTO;
use App\DTOs\CompanyInfo\UpdateCompanyInfoDTO;
use App\Models\CompanyInfo;

class CompanyInfoService
{
    public function show(): ?CompanyInfo
    {
        return CompanyInfo::with('currency')->first();
    }

    /**
     * `company_info` es una tabla de un solo registro: si ya existe uno,
     * el alta se rechaza y el consumidor debe usar PUT/PATCH.
     */
    public function store(CreateCompanyInfoDTO $dto): ?CompanyInfo
    {
        if (CompanyInfo::exists()) {
            return null;
        }

        return CompanyInfo::create($dto->fields)->load('currency');
    }

    public function update(UpdateCompanyInfoDTO $dto): ?CompanyInfo
    {
        $companyInfo = CompanyInfo::first();

        if (!$companyInfo) {
            return null;
        }

        $companyInfo->update($dto->fields);

        return $companyInfo->fresh('currency');
    }
}
