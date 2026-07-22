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
        $filters = $request->only(['p', 'f', 'o', 'w', 'totalCount']);
        $result  = $this->colorService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Colors retrieved.', ColorResource::collection($items), $total);
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

        $result = $this->colorService->index($filters);

        $items = is_array($result) && isset($result['items']) ? $result['items'] : $result;
        $total = is_array($result) ? ($result['total'] ?? null) : null;

        return ApiResponse::query('Colors retrieved.', ColorResource::collection($items), $total);
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
