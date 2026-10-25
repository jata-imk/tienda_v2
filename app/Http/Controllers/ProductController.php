<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithKeyRenamePreview;
use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Requests\Product\NextProductKeyRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UploadProductImageRequest;
use App\Http\Resources\Product\ProductResource;
use App\Services\ProductKeyService;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Inventory
 *
 * @authenticated
 */
class ProductController extends Controller
{
    use RespondsWithKeyRenamePreview;
    use TranslatesGridFilters;

    public function __construct(
        private ProductService $productService,
        private ProductKeyService $productKeyService,
    ) {}

    /**
     * List products
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * Cada producto incluye sus variantes en `variants`, ordenadas por color
     * alfabetico y, dentro de cada color, por talla (`sizes.sort_order`). `o` ordena
     * los productos, no las variantes: el orden de estas no es configurable aqui.
     * Para ordenarlas a gusto, `POST /api/products/{id}/variants/query` con `o`.
     *
     * @queryParam p[page] integer Page number. Example: 1
     * @queryParam p[per_page] integer Items per page. Example: 15
     * @queryParam f[] string[] Fields to return. Example: ["id","name"]
     * @queryParam o[column] string Order by column. Example: name
     * @queryParam o[direction] string Order direction (asc/desc). Example: asc
     * @queryParam w[status] string Filter by field value. Example: active
     * @queryParam totalCount boolean Return total count. Example: true
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Products retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Products retrieved.', $this->productService->index($filters), ProductResource::class);
    }

    /**
     * Query products (POST)
     *
     * Supports two payload formats. Formato B (standard):
     * `p.page` = row offset (0-indexed), `p.per_page`, `o.column`, `o.direction`, `w` = object.
     * Formato A (compact):
     * `p.r` = row offset, `p.s` = per_page, `o.field`, `o.type`, `w` = array of `{f, ao, v, lo}`.
     *
     * @bodyParam p object Pagination. `page`/`per_page` or `r`/`s`.
     * @bodyParam f string[] Fields to return. Example: ["id","name"]
     * @bodyParam o object Order. `column`+`direction` or `field`+`type`.
     * @bodyParam w object|array Where filters. Object for simple equality, array for advanced operators.
     * @bodyParam totalCount boolean Include total count (default true). Example: true
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Products retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Products retrieved.', $this->productService->index($filters), ProductResource::class);
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
     * Next product key
     *
     * Sugerencia de la siguiente clave para mostrarla en el formulario de alta:
     * `CAT[-GRUPO][-SEGMENTO]-NNN`, a partir del maximo que ya exista para ese
     * prefijo. No reserva nada: el alta sin `key` la vuelve a calcular.
     *
     * @queryParam idMainCategory integer required Main category ID. Example: 1
     * @queryParam idSizeGroup integer Size group ID (`NIN` for Niños). Example: 2
     * @queryParam keySegment string Optional extra segment. Example: DAM
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Next product key.","data":{"key":"GUA-NIN-022"}}
     */
    public function nextKey(NextProductKeyRequest $request): JsonResponse
    {
        return ApiResponse::ok('Next product key.', ['key' => $this->productKeyService->preview($request->toDTO())]);
    }

    /**
     * Create product
     *
     * Si se crea con `status: inactive`, sus variantes nacen `inactive` aunque el
     * payload las mande como `active`. Lo inverso si se respeta: en un producto
     * activo puede haber variantes `inactive` (por ejemplo una talla descontinuada).
     *
     * `key` y `variants[].sku` son opcionales: si no vienen se generan (ver
     * `GET /api/products/next-key` y la regla `CLAVE-S{idTalla}-C{idColor}`).
     *
     * @bodyParam categories integer[] required Category IDs. Example: [1,2]
     * @bodyParam idMainCategory integer Main category ID (gives the key prefix). Defaults to the first of `categories`. Example: 1
     * @bodyParam key string Internal unique key. Generated when omitted. Example: CAM-043
     * @bodyParam keySegment string Extra key segment, only used when `key` is generated. Example: DAM
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
     * Cambiar `status` cascadea a todas las variantes del producto: `inactive` las
     * desactiva, `active` las reactiva. Solo se dispara si el valor cambia, asi que
     * reenviar el formulario completo con el mismo `status` no las toca.
     *
     * Con `cascadeKeys: true`, cambiar `key` renombra tambien los SKU de sus variantes
     * (solo lo que sigue el patron automatico; todo o nada: si algo choca responde
     * 422 con `data.conflicts` y no guarda nada). Con `dryRun: true` no guarda:
     * responde la vista previa `{ totals, products, variants, conflicts }`.
     *
     * @urlParam id integer required Product ID. Example: 1
     * @bodyParam cascadeKeys boolean Rename what derives from `key` too. Example: false
     * @bodyParam dryRun boolean Only preview the cascade rename, save nothing. Example: false
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Product updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        if ($request->isDryRun()) {
            return $this->keyRenamePreviewResponse($this->productService->keyRenamePreview($id, $request->toDTO()), 'Product not found.');
        }

        $product = $this->productService->update($id, $request->toDTO());

        if (!$product) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::ok('Product updated.', new ProductResource($product));
    }

    /**
     * Upload product image
     *
     * Multipart request with an `image` file field. Stores the file on the
     * `public` disk, generates a 200px thumbnail and replaces any previous image.
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @bodyParam image file required Image file (jpeg, png, webp; max 4MB).
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Product image updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function uploadImage(UploadProductImageRequest $request, int $id): JsonResponse
    {
        $product = $this->productService->setImage($id, $request->file('image'));

        if (!$product) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::ok('Product image updated.', new ProductResource($product));
    }

    /**
     * Delete product image
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Product image deleted.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Product not found.","data":null}
     */
    public function deleteImage(int $id): JsonResponse
    {
        $product = $this->productService->clearImage($id);

        if (!$product) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::ok('Product image deleted.', new ProductResource($product));
    }

    /**
     * Deactivate product
     *
     * Sets status to `inactive`. Does not delete the record.
     *
     * Cascadea: todas las variantes del producto pasan tambien a `inactive`, por lo
     * que su `totalStock` queda en 0. Reactivar el producto con
     * `PUT /api/products/{id}` y `status: active` las reactiva todas.
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
