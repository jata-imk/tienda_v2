<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
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
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Products retrieved.","data":{"items":[],"total":0,"page":1,"pages":1}}
     */
    public function query(Request $request): JsonResponse
    {
        $body    = $request->json()->all();
        $filters = [];

        // Pagination — p.page/p.r = row offset (0-indexed); p.per_page/p.s = page size
        if (!empty($body['p'])) {
            $p       = $body['p'];
            $perPage = (int) ($p['per_page'] ?? $p['s'] ?? 15);
            $offset  = (int) ($p['page'] ?? $p['r'] ?? 0);
            $filters['p'] = [
                'per_page' => $perPage > 0 ? $perPage : 15,
                'page'     => $perPage > 0 ? max(1, intdiv($offset, $perPage) + 1) : 1,
            ];
        }

        // Field selection
        if (isset($body['f']) && is_array($body['f']) && count($body['f']) > 0) {
            $filters['f'] = array_map(fn($f) => Str::snake($f), $body['f']);
        }

        // Ordering — supports column/direction (Formato B) or field/type (Formato A)
        if (!empty($body['o'])) {
            $filters['o'] = [
                'column'    => $body['o']['column'] ?? $body['o']['field'] ?? 'id',
                'direction' => $body['o']['direction'] ?? $body['o']['type'] ?? 'asc',
            ];
        }

        // Where — associative object (simple) or array of condition objects (advanced)
        if (!empty($body['w'])) {
            $w = $body['w'];
            if (array_is_list($w)) {
                $filters['w'] = array_map(fn($cond) => [
                    'column'   => Str::snake($cond['f'] ?? ''),
                    'operator' => $this->mapOperator($cond['ao'] ?? '=='),
                    'value'    => $cond['v'] ?? null,
                    'logic'    => strtolower($cond['lo'] ?? '&&') === '||' ? 'or' : 'and',
                ], $w);
            } else {
                $filters['w'] = $w;
            }
        }

        // totalCount — default true handled by service
        if (array_key_exists('totalCount', $body)) {
            $filters['totalCount'] = $body['totalCount'];
        }

        $result = $this->productService->index($filters);

        $items    = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total    = is_array($result) ? ($result['total'] ?? null) : null;
        $response = ['data' => ProductResource::collection($items)];

        if ($total !== null) {
            $response['totalCount'] = $total;
            $response['summary']    = [$total];
        }

        return response()->json($response);
    }

    private function mapOperator(string $ao): string
    {
        return match ($ao) {
            '==' => '=',
            '!=' => '!=',
            '>'  => '>',
            '>=' => '>=',
            '<'  => '<',
            '<=' => '<=',
            default => '=',
        };
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
