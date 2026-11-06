<?php

namespace App\Services;

use App\DTOs\CompanyInfo\CreateCompanyInfoDTO;
use App\DTOs\CompanyInfo\UpdateCompanyInfoDTO;
use App\Models\CompanyInfo;
use App\Support\Audit;

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
        return Audit::transaction(function () use ($dto) {
            if (CompanyInfo::exists()) {
                return null;
            }

            return CompanyInfo::create($dto->fields)->load('currency');
        });
    }

    public function update(UpdateCompanyInfoDTO $dto): ?CompanyInfo
    {
        return Audit::transaction(function () use ($dto) {
            $companyInfo = CompanyInfo::lockForUpdate()->first();

            if (! $companyInfo) {
                return null;
            }

            $companyInfo->update($dto->fields);

            return $companyInfo->fresh('currency');
        });
    }
}
