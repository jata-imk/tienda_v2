<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * @group Authentication
 *
 * Session management endpoints.
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Login
     *
     * Validates credentials, manages session, returns a JWT valid for 24 hours.
     * If the user already has an active session, the same JWT is reused.
     *
     * @unauthenticated
     *
     * @bodyParam userName string required Username. Example: admin
     * @bodyParam password string required Password. Example: admin
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Login successful","data":{"token":"eyJ...","companyInfo":{},"user":{"id":1,"firstName":"Administrador","lastName":"Sistema","userName":"admin","email":"admin@tienda.local","userType":1,"roleCode":"administrator","roleName":"Administrador"},"catalogs":{"currencies":[],"categories":[],"colors":[],"sizeGroups":[],"sizes":[],"userTypes":[{"id":1,"name":"Administrador","code":"administrator","status":"active"}],"ivaTypes":[{"id":1,"name":"General"},{"id":2,"name":"Por producto"},{"id":3,"name":"Cuota fija"},{"id":4,"name":"No aplica"}]}}}
     * @response 401 {"ok":false,"code":401,"status":"Unauthorized","message":"Incorrect password","data":null}
     * @response 422 {"message":"The userName field is required.","errors":{}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->toDTO());

        if ($result['result'] !== 'ok') {
            return ApiResponse::error($result['message'], 401);
        }

        return ApiResponse::ok($result['message'], new LoginResource($result['data']));
    }

    /**
     * Logout
     *
     * Revokes the current session token.
     *
     * @authenticated
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Session closed.","data":null}
     * @response 401 {"ok":false,"code":401,"status":"Unauthorized","message":"No active session found.","data":null}
     */
    public function logout(): JsonResponse
    {
        $token = JWTAuth::getToken();

        if (! $token || ! $this->authService->logout($token->get())) {
            return ApiResponse::error('No active session found.', 401);
        }

        return ApiResponse::ok('Session closed.');
    }
}
