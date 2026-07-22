<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\SizeGroup\CreateSizeGroupRequest;
use App\Http\Requests\SizeGroup\UpdateSizeGroupRequest;
use App\Http\Resources\SizeGroup\SizeGroupResource;
use App\Services\SizeGroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Inventory
 *
 * @authenticated
 */
class SizeGroupController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(private SizeGroupService $sizeGroupService) {}

    /**
     * List size groups
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size groups retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['p', 'f', 'o', 'w', 'totalCount']);
        $result  = $this->sizeGroupService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Size groups retrieved.', SizeGroupResource::collection($items), $total);
    }

    /**
     * Query size groups (POST)
     *
     * Same payload formats as `POST /products/query`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size groups retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
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
                $filters['w'] = $this->translateGridFilters($w);
            } else {
                $filters['w'] = $w;
            }
        }

        if (array_key_exists('totalCount', $body)) {
            $filters['totalCount'] = $body['totalCount'];
        }

        $result = $this->sizeGroupService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Size groups retrieved.', SizeGroupResource::collection($items), $total);
    }

    /**
     * Get size group
     *
     * @urlParam id integer required Size group ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size group retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Size group not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $sizeGroup = $this->sizeGroupService->show($id);

        if (!$sizeGroup) {
            return ApiResponse::error('Size group not found.', 404);
        }

        return ApiResponse::ok('Size group retrieved.', new SizeGroupResource($sizeGroup));
    }

    /**
     * Create size group
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Size group created.","data":{}}
     */
    public function store(CreateSizeGroupRequest $request): JsonResponse
    {
        return ApiResponse::created('Size group created.', new SizeGroupResource($this->sizeGroupService->store($request->toDTO())));
    }

    /**
     * Update size group
     *
     * @urlParam id integer required Size group ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size group updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Size group not found.","data":null}
     */
    public function update(UpdateSizeGroupRequest $request, int $id): JsonResponse
    {
        $sizeGroup = $this->sizeGroupService->update($id, $request->toDTO());

        if (!$sizeGroup) {
            return ApiResponse::error('Size group not found.', 404);
        }

        return ApiResponse::ok('Size group updated.', new SizeGroupResource($sizeGroup));
    }

    /**
     * Deactivate size group
     *
     * @urlParam id integer required Size group ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Size group deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Size group not found.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->sizeGroupService->destroy($id)) {
            return ApiResponse::error('Size group not found.', 404);
        }

        return ApiResponse::ok('Size group deactivated.');
    }
}
