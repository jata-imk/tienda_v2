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
        $filters = $request->only(['p', 'f', 'o', 'w', 'totalCount']);
        $result  = $this->sizeService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Sizes retrieved.', SizeResource::collection($items), $total);
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

        $result = $this->sizeService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Sizes retrieved.', SizeResource::collection($items), $total);
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
