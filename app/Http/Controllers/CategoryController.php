<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\CreateCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Services\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categories retrieved.","data":[]}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['p', 'f', 'o', 'w', 'totalCount']);
        $result  = $this->categoryService->index($filters);

        return ApiResponse::ok('Categories retrieved.', is_array($result) && isset($result['items'])
            ? ['items' => CategoryResource::collection($result['items']), 'total' => $result['total'] ?? null, 'page' => $result['page'] ?? null, 'pages' => $result['pages'] ?? null]
            : CategoryResource::collection($result)
        );
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
