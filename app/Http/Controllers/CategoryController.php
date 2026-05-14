<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\CreateCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Services\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Inventory
 *
 * @authenticated
 */
class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    /**
     * List categories
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * @queryParam p[page] integer Page number. Example: 1
     * @queryParam p[per_page] integer Items per page. Example: 15
     * @queryParam f[] string[] Fields to return. Example: ["id","name"]
     * @queryParam o[column] string Order by column. Example: name
     * @queryParam o[direction] string Order direction (asc/desc). Example: asc
     * @queryParam w[status] string Filter by status. Example: active
     * @queryParam totalCount boolean Return total count. Example: true
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categories retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['p', 'f', 'o', 'w', 'totalCount']);
        $result  = $this->categoryService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Categories retrieved.', CategoryResource::collection($items), $total);
    }

    /**
     * Query categories (POST)
     *
     * Same payload formats as `POST /products/query`.
     *
     * @bodyParam p object Pagination. `page`/`per_page` (row offset + size) or `r`/`s`.
     * @bodyParam f string[] Fields to return. Example: ["id","name"]
     * @bodyParam o object Order. `column`+`direction` or `field`+`type`.
     * @bodyParam w object|array Where filters. Object for simple equality, array for advanced operators.
     * @bodyParam totalCount boolean Include total count (default true). Example: true
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categories retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $body    = $request->json()->all();
        $filters = [];

        if (!empty($body['p'])) {
            $p       = $body['p'];
            $perPage = (int) ($p['per_page'] ?? $p['s'] ?? 15);
            $offset  = (int) ($p['page'] ?? $p['r'] ?? 0);
            $filters['p'] = [
                'per_page' => $perPage > 0 ? $perPage : 15,
                'page'     => $perPage > 0 ? max(1, intdiv($offset, $perPage) + 1) : 1,
            ];
        }

        if (isset($body['f']) && is_array($body['f']) && count($body['f']) > 0) {
            $filters['f'] = array_map(fn($f) => Str::snake($f), $body['f']);
        }

        if (!empty($body['o'])) {
            $filters['o'] = [
                'column'    => $body['o']['column'] ?? $body['o']['field'] ?? 'id',
                'direction' => $body['o']['direction'] ?? $body['o']['type'] ?? 'asc',
            ];
        }

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

        if (array_key_exists('totalCount', $body)) {
            $filters['totalCount'] = $body['totalCount'];
        }

        $result = $this->categoryService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Categories retrieved.', CategoryResource::collection($items), $total);
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
     * Get category
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Category retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Category not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->show($id);

        if (!$category) {
            return ApiResponse::error('Category not found.', 404);
        }

        return ApiResponse::ok('Category retrieved.', new CategoryResource($category));
    }

    /**
     * Create category
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Category created.","data":{}}
     */
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        return ApiResponse::created('Category created.', new CategoryResource($this->categoryService->store($request->toDTO())));
    }

    /**
     * Update category
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Category updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Category not found.","data":null}
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->update($id, $request->toDTO());

        if (!$category) {
            return ApiResponse::error('Category not found.', 404);
        }

        return ApiResponse::ok('Category updated.', new CategoryResource($category));
    }

    /**
     * Deactivate category
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Category deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Category not found.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->categoryService->destroy($id)) {
            return ApiResponse::error('Category not found.', 404);
        }

        return ApiResponse::ok('Category deactivated.');
    }
}
