<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Catalogs
 *
 * @authenticated
 */
class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalogService) {}

    /**
     * List catalogs
     *
     * Returns all static catalogs (currencies, categories, colors, sizes,
     * size groups, user types, IVA types) in a single call. Same payload
     * bundled inside the login response — use this endpoint to refresh the
     * frontend's local cache without requiring a new login.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Catalogs retrieved.","data":{"currencies":[],"categories":[],"colors":[],"sizeGroups":[],"sizes":[],"userTypes":[],"ivaTypes":[]}}
     */
    public function index(): JsonResponse
    {
        return ApiResponse::ok('Catalogs retrieved.', $this->catalogService->all());
    }
}
