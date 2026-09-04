<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $roleCode = $request->user()?->userType?->code;

        if (! $roleCode || ! in_array($roleCode, $roles, true)) {
            return ApiResponse::error('No tienes permisos para realizar esta acción.', 403);
        }

        return $next($request);
    }
}
