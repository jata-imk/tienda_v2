<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductColorImagesRequest;
use App\Http\Resources\Product\ProductImageResource;
use App\Models\Color;
use App\Models\Product;
use App\Services\ProductImageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Inventory
 *
 * @authenticated
 */
class ProductColorImageController extends Controller
{
    public function __construct(private ProductImageService $productImageService) {}

    /**
     * List product color images
     *
     * Devuelve las imagenes cargadas para un color especifico del producto.
     *
     * @urlParam product integer required Product ID. Example: 1
     * @urlParam color integer required Color ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Images retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function index(int $product, int $color): JsonResponse
    {
        $productModel = Product::find($product);

        if (!$productModel) {
            return ApiResponse::error('Product not found.', 404);
        }

        $colorModel = Color::find($color);

        if (!$colorModel) {
            return ApiResponse::error('Color not found.', 404);
        }

        $images = $this->productImageService->listImages($productModel, $colorModel);

        return ApiResponse::query('Images retrieved.', ProductImageResource::collection($images), $images->count());
    }

    /**
     * Upload product color images
     *
     * Multipart request con un campo `images[]` (uno o varios archivos). Cada
     * imagen se agrega a la galeria del color; no reemplaza las existentes.
     *
     * @urlParam product integer required Product ID. Example: 1
     * @urlParam color integer required Color ID. Example: 1
     *
     * @bodyParam images file[] required Archivos de imagen (jpeg, png, webp; max 4MB c/u).
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Images uploaded.","data":{"items":[],"totalCount":0,"summary":[0]}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function store(StoreProductColorImagesRequest $request, int $product, int $color): JsonResponse
    {
        $productModel = Product::find($product);

        if (!$productModel) {
            return ApiResponse::error('Product not found.', 404);
        }

        $colorModel = Color::find($color);

        if (!$colorModel) {
            return ApiResponse::error('Color not found.', 404);
        }

        $images = $this->productImageService->addImages($productModel, $colorModel, $request->file('images'));

        return ApiResponse::created('Images uploaded.', ProductImageResource::collection($images));
    }

    /**
     * Delete a product color image
     *
     * @urlParam product integer required Product ID. Example: 1
     * @urlParam color integer required Color ID. Example: 1
     * @urlParam image integer required Product image ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Image deleted.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function destroy(int $product, int $color, int $image): JsonResponse
    {
        $productModel = Product::find($product);

        if (!$productModel) {
            return ApiResponse::error('Product not found.', 404);
        }

        $colorModel = Color::find($color);

        if (!$colorModel) {
            return ApiResponse::error('Color not found.', 404);
        }

        $imageModel = $this->productImageService->findImage($productModel, $colorModel, $image);

        if (!$imageModel) {
            return ApiResponse::error('Image not found.', 404);
        }

        $this->productImageService->deleteImage($imageModel);

        return ApiResponse::ok('Image deleted.');
    }
}
