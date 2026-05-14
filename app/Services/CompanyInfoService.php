<?php

namespace App\Services;

use App\DTOs\CompanyInfo\UpdateCompanyInfoDTO;
use App\Models\CompanyInfo;

class CompanyInfoService
{
    public function update(UpdateCompanyInfoDTO $dto): ?CompanyInfo
    {
        $companyInfo = CompanyInfo::first();

        if (!$companyInfo) {
            return null;
        }

        $companyInfo->update($dto->fields);

        return $companyInfo->fresh();
    }
}
