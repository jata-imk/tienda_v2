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
     * Dashboard metrics (GET)
     *
     * Returns best-selling products, stock ranking (lowest/highest), critical
     * product-size combinations and inventory KPIs in a single payload. Stock
     * alerts omit zero-stock variants that have never had inventory movements.
     *
     * Every stock metric reflects the **current** inventory state: only
     * `topProducts` is affected by the date range.
     *
     * @queryParam limit integer Rows per ranking (1-50, default 5). Does not apply to criticalStockBySize. Example: 5
     * @queryParam dateFrom date Start of the sales range, inclusive. Only filters topProducts. Example: 2026-01-01
     * @queryParam dateTo date End of the sales range, inclusive (whole day). Only filters topProducts. Example: 2026-07-21
     * @queryParam lowStockThreshold number Stock level considered low (default 5). Filters criticalStockBySize and summary.lowStockCount. Example: 5
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Dashboard retrieved.","data":{"topProducts":[],"lowestStock":[],"highestStock":[],"criticalStockBySize":[{"product":"Filipina caballero","key":"FIL-001","size":"G","stock":2}],"summary":{"totalProducts":0,"activeProducts":0,"totalVariants":0,"totalStock":0,"inventoryValue":0,"inventorySaleValue":0,"lowStockCount":0,"outOfStockCount":0}}}
     */
    public function index(DashboardRequest $request): JsonResponse
    {
        return $this->response($request);
    }

    /**
     * Query dashboard metrics (POST)
     *
     * Accepts the dashboard filters as a JSON body. It returns the same data
     * as `GET /api/dashboard`, which remains available for compatibility.
     *
     * Every stock metric reflects the **current** inventory state: only
     * `topProducts` is affected by the date range. Stock alerts omit
     * zero-stock variants that have never had inventory movements.
     *
     * @bodyParam limit integer Rows per ranking (1-50, default 5). Does not apply to criticalStockBySize. Example: 5
     * @bodyParam dateFrom string Start of the sales range, inclusive (YYYY-MM-DD). Only filters topProducts. Example: 2026-01-01
     * @bodyParam dateTo string End of the sales range, inclusive (YYYY-MM-DD, whole day). Only filters topProducts. Example: 2026-07-21
     * @bodyParam lowStockThreshold number Stock level considered low (default 5). Filters criticalStockBySize and summary.lowStockCount. Example: 5
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Dashboard retrieved.","data":{"topProducts":[],"lowestStock":[],"highestStock":[],"criticalStockBySize":[{"product":"Filipina caballero","key":"FIL-001","size":"G","stock":2}],"summary":{"totalProducts":0,"activeProducts":0,"totalVariants":0,"totalStock":0,"inventoryValue":0,"inventorySaleValue":0,"lowStockCount":0,"outOfStockCount":0}}}
     */
    public function query(DashboardRequest $request): JsonResponse
    {
        return $this->response($request);
    }

    private function response(DashboardRequest $request): JsonResponse
    {
        return ApiResponse::ok('Dashboard retrieved.', $this->dashboardService->summary($request->toDTO()));
    }
}
