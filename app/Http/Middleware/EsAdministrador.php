<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->userType?->name !== 'administrador') {
            return ApiResponse::error('Access restricted to administrators.', 403);
        }

        return $next($request);
    }
}
