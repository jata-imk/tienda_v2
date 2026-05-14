<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyInfo\PatchCompanyInfoRequest;
use App\Http\Requests\CompanyInfo\UpdateCompanyInfoRequest;
use App\Http\Resources\CompanyInfo\CompanyInfoResource;
use App\Services\CompanyInfoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Configuration
 *
 * @authenticated
 */
class CompanyInfoController extends Controller
{
    public function __construct(private CompanyInfoService $companyInfoService) {}

    /**
     * Update company info
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Company info updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Company info not found.","data":null}
     */
    public function update(UpdateCompanyInfoRequest $request): JsonResponse
    {
        $companyInfo = $this->companyInfoService->update($request->toDTO());

        if (!$companyInfo) {
            return ApiResponse::error('Company info not found.', 404);
        }

        return ApiResponse::ok('Company info updated.', new CompanyInfoResource($companyInfo));
    }

    /**
     * Partially update company info
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Company info updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Company info not found.","data":null}
     */
    public function patch(PatchCompanyInfoRequest $request): JsonResponse
    {
        $companyInfo = $this->companyInfoService->update($request->toDTO());

        if (!$companyInfo) {
            return ApiResponse::error('Company info not found.', 404);
        }

        return ApiResponse::ok('Company info updated.', new CompanyInfoResource($companyInfo));
    }
}
