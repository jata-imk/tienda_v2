<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\Size\CreateSizeRequest;
use App\Http\Requests\Size\UpdateSizeRequest;
use App\Http\Resources\Size\SizeResource;
use App\Services\SizeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Inventory
 *
 * @authenticated
 */
class SizeController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(private SizeService $sizeService) {}

    /**
     * List sizes
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     * Filter by group with `w[id_size_group]`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Sizes retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Sizes retrieved.', $this->sizeService->index($filters), SizeResource::class);
    }

    /**
     * Query sizes (POST)
     *
     * Same payload formats as `POST /products/query`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Sizes retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Sizes retrieved.', $this->sizeService->index($filters), SizeResource::class);
    }

    /**
     * Get size
     *
     * @urlParam id integer required Size ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Size not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $size = $this->sizeService->show($id);

        if (!$size) {
            return ApiResponse::error('Size not found.', 404);
        }

        return ApiResponse::ok('Size retrieved.', new SizeResource($size));
    }

    /**
     * Create size
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Size created.","data":{}}
     */
    public function store(CreateSizeRequest $request): JsonResponse
    {
        return ApiResponse::created('Size created.', new SizeResource($this->sizeService->store($request->toDTO())));
    }

    /**
     * Update size
     *
     * @urlParam id integer required Size ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Size not found.","data":null}
     */
    public function update(UpdateSizeRequest $request, int $id): JsonResponse
    {
        $size = $this->sizeService->update($id, $request->toDTO());

        if (!$size) {
            return ApiResponse::error('Size not found.', 404);
        }

        return ApiResponse::ok('Size updated.', new SizeResource($size));
    }

    /**
     * Deactivate size
     *
     * @urlParam id integer required Size ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Size not found.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->sizeService->destroy($id)) {
            return ApiResponse::error('Size not found.', 404);
        }

        return ApiResponse::ok('Size deactivated.');
    }
}
