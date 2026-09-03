<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\Color\CreateColorRequest;
use App\Http\Requests\Color\UpdateColorRequest;
use App\Http\Resources\Color\ColorResource;
use App\Services\ColorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Inventory
 *
 * @authenticated
 */
class ColorController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(private ColorService $colorService) {}

    /**
     * List colors
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Colors retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Colors retrieved.', $this->colorService->index($filters), ColorResource::class);
    }

    /**
     * Query colors (POST)
     *
     * Same payload formats as `POST /products/query`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Colors retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Colors retrieved.', $this->colorService->index($filters), ColorResource::class);
    }

    /**
     * Get color
     *
     * @urlParam id integer required Color ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Color retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Color not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $color = $this->colorService->show($id);

        if (!$color) {
            return ApiResponse::error('Color not found.', 404);
        }

        return ApiResponse::ok('Color retrieved.', new ColorResource($color));
    }

    /**
     * Create color
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Color created.","data":{}}
     */
    public function store(CreateColorRequest $request): JsonResponse
    {
        return ApiResponse::created('Color created.', new ColorResource($this->colorService->store($request->toDTO())));
    }

    /**
     * Update color
     *
     * @urlParam id integer required Color ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Color updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Color not found.","data":null}
     */
    public function update(UpdateColorRequest $request, int $id): JsonResponse
    {
        $color = $this->colorService->update($id, $request->toDTO());

        if (!$color) {
            return ApiResponse::error('Color not found.', 404);
        }

        return ApiResponse::ok('Color updated.', new ColorResource($color));
    }

    /**
     * Deactivate color
     *
     * @urlParam id integer required Color ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Color deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Color not found.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->colorService->destroy($id)) {
            return ApiResponse::error('Color not found.', 404);
        }

        return ApiResponse::ok('Color deactivated.');
    }
}
