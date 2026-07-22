<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\DashboardRequest;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Dashboard
 *
 * @authenticated
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /**
     * Dashboard metrics
     *
     * Returns best-selling products, stock ranking (lowest/highest) and
     * inventory KPIs in a single payload.
     *
     * @queryParam limit integer Rows per ranking (1-50, default 5). Example: 5
     * @queryParam dateFrom date Start of the sales range. Example: 2026-01-01
     * @queryParam dateTo date End of the sales range. Example: 2026-07-21
     * @queryParam lowStockThreshold number Stock level considered low (default 5). Example: 5
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Dashboard retrieved.","data":{"topProducts":[],"lowestStock":[],"highestStock":[],"summary":{"totalProducts":0,"activeProducts":0,"totalVariants":0,"totalStock":0,"inventoryValue":0,"lowStockCount":0}}}
     */
    public function index(DashboardRequest $request): JsonResponse
    {
        return ApiResponse::ok('Dashboard retrieved.', $this->dashboardService->summary($request->toDTO()));
    }
}
