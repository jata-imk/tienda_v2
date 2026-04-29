<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (!$usuario || $usuario->tipoUsuario?->type_user !== 'administrador') {
            return response()->json([
                'result'  => 'error',
                'message' => 'Acceso restringido a administradores.',
                'data'    => null,
            ], 403);
        }

        return $next($request);
    }
}
