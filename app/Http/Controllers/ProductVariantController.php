<?php

namespace App\Http\Controllers;

use App\Http\Resources\Product\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Inventory
 *
 * @authenticated
 */
class ProductVariantController extends Controller
{
    /**
     * List product variants
     *
     * Devuelve las variantes (talla x color) de un producto, base para la matriz de captura.
     *
     * @urlParam product integer required Product ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Variants retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function index(int $product): JsonResponse
    {
        if (!Product::whereKey($product)->exists()) {
            return ApiResponse::error('Product not found.', 404);
        }

        $variants = ProductVariant::with(['size', 'color'])
            ->where('id_product', $product)
            ->get();

        return ApiResponse::query('Variants retrieved.', ProductVariantResource::collection($variants), $variants->count());
    }
}
