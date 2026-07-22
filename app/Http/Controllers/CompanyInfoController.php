<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyInfo\CreateCompanyInfoRequest;
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
     * Get company info
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Company info retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Company info not found.","data":null}
     */
    public function show(): JsonResponse
    {
        $companyInfo = $this->companyInfoService->show();

        if (!$companyInfo) {
            return ApiResponse::error('Company info not found.', 404);
        }

        return ApiResponse::ok('Company info retrieved.', new CompanyInfoResource($companyInfo));
    }

    /**
     * Create company info
     *
     * Single-row table: fails with 409 if a record already exists.
     * `logo` accepts a base64 string (with or without data-URI prefix).
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Company info created.","data":{}}
     * @response 409 {"ok":false,"code":409,"status":"Conflict","message":"Company info already exists.","data":null}
     */
    public function store(CreateCompanyInfoRequest $request): JsonResponse
    {
        $companyInfo = $this->companyInfoService->store($request->toDTO());

        if (!$companyInfo) {
            return ApiResponse::error('Company info already exists.', 409);
        }

        return ApiResponse::created('Company info created.', new CompanyInfoResource($companyInfo));
    }

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
