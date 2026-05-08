<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Users
 *
 * @authenticated
 */
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * List users
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Users retrieved.","data":[]}
     */
    public function index(): JsonResponse
    {
        return ApiResponse::ok('Users retrieved.', UserResource::collection($this->userService->index()));
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
