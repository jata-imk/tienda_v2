<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Users
 *
 * @authenticated
 */
class UserController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(private UserService $userService) {}

    /**
     * List users
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Users retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Users retrieved.', $this->userService->index($filters), UserResource::class);
    }

    /**
     * Query users (POST)
     *
     * Same payload formats as `POST /products/query`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Users retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Users retrieved.', $this->userService->index($filters), UserResource::class);
    }

    /**
     * Get user
     *
     * @urlParam id integer required User ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"User retrieved.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"User not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->show($id);

        if (!$user) {
            return ApiResponse::error('User not found.', 404);
        }

        return ApiResponse::ok('User retrieved.', new UserResource($user));
    }

    /**
     * Create user
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"User created.","data":{}}
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        return ApiResponse::created('User created.', new UserResource($this->userService->store($request->toDTO())));
    }

    /**
     * Update user
     *
     * @urlParam id integer required User ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"User updated.","data":{}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"User not found.","data":null}
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->toDTO());

        if (!$user) {
            return ApiResponse::error('User not found.', 404);
        }

        return ApiResponse::ok('User updated.', new UserResource($user));
    }

    /**
     * Deactivate user
     *
     * Sets status to `inactive`. Does not delete the record.
     *
     * @urlParam id integer required User ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"User deactivated.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"User not found.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->userService->destroy($id)) {
            return ApiResponse::error('User not found.', 404);
        }

        return ApiResponse::ok('User deactivated.');
    }
}
