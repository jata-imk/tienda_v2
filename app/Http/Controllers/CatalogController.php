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
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Catalogs retrieved.","data":{"currencies":[],"categories":[],"colors":[],"sizeGroups":[],"sizes":[],"userTypes":[{"id":1,"name":"Administrador","code":"administrator","status":"active"}],"ivaTypes":[{"id":1,"name":"General"},{"id":2,"name":"Por producto"},{"id":3,"name":"Cuota fija"},{"id":4,"name":"No aplica"}]}}
     */
    public function index(): JsonResponse
    {
        return ApiResponse::ok('Catalogs retrieved.', $this->catalogService->all());
    }
}
