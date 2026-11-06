<?php

use App\Exceptions\AuditUnavailableException;
use App\Exceptions\KeyRenameConflictException;
use App\Http\Middleware\AuditRequest;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RequireRole;
use App\Http\Resources\Product\KeyRenamePlanResource;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AuditRequest::class]);
        $middleware->alias([
            'jwt.authenticate' => JwtAuthenticate::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuditUnavailableException $e, Request $request) {
            return ApiResponse::error('No se pudo guardar la auditoría. La operación fue cancelada.', 503);
        });
        $exceptions->respond(function (Response $response) {
            if ($id = request()->attributes->get('auditRequestId')) {
                $response->headers->set('X-Request-Id', $id);
            }

            return $response;
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ApiResponse::error('No autenticado.', 401);
        });

        // Producto, categoria y grupo de tallas: el PUT con cascadeKeys choco.
        $exceptions->render(function (KeyRenameConflictException $e, Request $request) {
            return ApiResponse::error($e->getMessage(), 422, (new KeyRenamePlanResource($e->plan))->resolve($request));
        });
    })->create();
