<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\CreateProductVariantsRequest;
use App\Http\Requests\Product\UpdateProductVariantRequest;
use App\Http\Resources\Product\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Inventory
 *
 * @authenticated
 */
class ProductVariantController extends Controller
{
    public function __construct(private ProductVariantService $productVariantService) {}

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

        $variants = $this->productVariantService->listByProduct($product);

        return ApiResponse::query('Variants retrieved.', ProductVariantResource::collection($variants), $variants->count());
    }

    /**
     * Add variants to a product
     *
     * Agrega una o varias variantes (talla x color) a un producto existente, por
     * ejemplo un color nuevo. Si se envia `initialMovement` se genera un movimiento
     * de inventario por cada variante con existencia inicial mayor a cero.
     *
     * @urlParam product integer required Product ID. Example: 1
     *
     * @bodyParam variants object[] required Variantes a agregar.
     * @bodyParam variants[].idSize integer required Size ID (debe pertenecer al grupo de tallas del producto). Example: 2
     * @bodyParam variants[].idColor integer required Color ID. Example: 3
     * @bodyParam variants[].sku string required SKU unico de la variante. Example: CAM-001-34-BEI
     * @bodyParam variants[].codeBar string Codigo de barras de la variante. Example: 7500000000011
     * @bodyParam variants[].stock number required Existencia inicial. Example: 3
     * @bodyParam variants[].status string active o inactive. Example: active
     * @bodyParam initialMovement object Movimiento inicial opcional.
     * @bodyParam initialMovement.movementType string required_with:initialMovement entry, sale, adjustment, return o cancel. Example: entry
     * @bodyParam initialMovement.idUser integer required_with:initialMovement Usuario que genera el movimiento. Example: 1
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Variants created.","data":{"items":[],"totalStock":0}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function store(CreateProductVariantsRequest $request, int $product): JsonResponse
    {
        $productModel = Product::find($product);

        if (!$productModel) {
            return ApiResponse::error('Product not found.', 404);
        }

        $variants = $this->productVariantService->store($productModel, $request->toDTO());

        return ApiResponse::created('Variants created.', [
            'items'      => ProductVariantResource::collection($variants),
            'totalStock' => $this->productVariantService->totalStock($productModel),
        ]);
    }

    /**
     * Update a product variant
     *
     * Solo se editan `sku`, `codeBar` y `status`. La talla y el color son fijos
     * (identifican la variante) y el `stock` se mueve por `POST /api/inventory/movements`.
     *
     * @urlParam product integer required Product ID. Example: 1
     * @urlParam variant integer required Product variant ID. Example: 1
     *
     * @bodyParam sku string SKU unico de la variante. Example: CAM-001-34-BEI
     * @bodyParam codeBar string Codigo de barras de la variante. Example: 7500000000011
     * @bodyParam status string active o inactive. Example: active
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Variant updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Variant not found for this product.","data":null}
     */
    public function update(UpdateProductVariantRequest $request, int $product, int $variant): JsonResponse
    {
        $resolved = $this->resolveProductVariant($product, $variant);

        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        [, $variantModel] = $resolved;

        $updated = $this->productVariantService->update($variantModel, $request->toDTO());

        return ApiResponse::ok('Variant updated.', new ProductVariantResource($updated));
    }

    /**
     * Deactivate a product variant
     *
     * Pone `status` en `inactive`. No borra la fila ni sus movimientos; la variante
     * deja de sumar en el `totalStock` del producto.
     *
     * @urlParam product integer required Product ID. Example: 1
     * @urlParam variant integer required Product variant ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Variant deactivated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Variant not found for this product.","data":null}
     */
    public function destroy(int $product, int $variant): JsonResponse
    {
        $resolved = $this->resolveProductVariant($product, $variant);

        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        [, $variantModel] = $resolved;

        $deactivated = $this->productVariantService->deactivate($variantModel);

        return ApiResponse::ok('Variant deactivated.', new ProductVariantResource($deactivated));
    }

    /**
     * Resuelve producto y variante validando que la variante pertenezca al
     * producto. Devuelve [Product, ProductVariant] o un JsonResponse 404.
     *
     * @return array{0: Product, 1: ProductVariant}|JsonResponse
     */
    private function resolveProductVariant(int $product, int $variant): array|JsonResponse
    {
        $productModel = Product::find($product);

        if (!$productModel) {
            return ApiResponse::error('Product not found.', 404);
        }

        $variantModel = ProductVariant::where('id_product', $productModel->id)
            ->whereKey($variant)
            ->first();

        if (!$variantModel) {
            return ApiResponse::error('Variant not found for this product.', 404);
        }

        return [$productModel, $variantModel];
    }
}
