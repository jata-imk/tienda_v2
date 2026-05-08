<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Inventory
 *
 * @authenticated
 */
class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    /**
     * List products
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * @queryParam p[page] integer Page number. Example: 1
     * @queryParam p[per_page] integer Items per page. Example: 15
     * @queryParam f[] string[] Fields to return. Example: ["id","name"]
     * @queryParam o[column] string Order by column. Example: name
     * @queryParam o[direction] string Order direction (asc/desc). Example: asc
     * @queryParam w[status] string Filter by field value. Example: active
     * @queryParam totalCount boolean Return total count. Example: true
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Products retrieved.","data":[]}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['p', 'f', 'o', 'w', 'totalCount']);
        $result  = $this->productService->index($filters);

        return ApiResponse::ok('Products retrieved.', is_array($result) && isset($result['items'])
            ? ['items' => ProductResource::collection($result['items']), 'total' => $result['total'] ?? null, 'page' => $result['page'] ?? null, 'pages' => $result['pages'] ?? null]
            : ProductResource::collection($result)
        );
    }

    /**
     * Get product
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Product retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->productService->show($id);

        if (!$product) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::ok('Product retrieved.', new ProductResource($product));
    }

    /**
     * Create product
     *
     * @bodyParam idCategory integer required Category ID. Example: 1
     * @bodyParam key string required Internal unique key. Example: 000001
     * @bodyParam typeIva integer required IVA type (1=general/16%, 2=rate, 3=quota, 4=N/A). Example: 1
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Product created.","data":{}}
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        return ApiResponse::created('Product created.', new ProductResource($this->productService->store($request->toDTO())));
    }

    /**
     * Update product
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Product updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productService->update($id, $request->toDTO());

        if (!$product) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::ok('Product updated.', new ProductResource($product));
    }

    /**
     * Deactivate product
     *
     * Sets status to `inactive`. Does not delete the record.
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Product deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->productService->destroy($id)) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::ok('Product deactivated.');
    }
}
