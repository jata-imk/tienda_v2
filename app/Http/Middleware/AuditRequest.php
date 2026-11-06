<?php

namespace App\Http\Middleware;

use App\Exceptions\AuditUnavailableException;
use App\Services\AuditTrail;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditRequest
{
    public function __construct(private AuditTrail $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->audit->initialize($request);
        $started = microtime(true);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $this->audit->finish($status);
            $response->headers->set('X-Request-Id', $this->audit->requestId());

            return $response;
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof AuditUnavailableException => 503,
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => $exception->status() ?? 403,
                $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $this->audit->finish($status, $exception);
            throw $exception;
        } finally {
            $this->audit->logRequest($status, (microtime(true) - $started) * 1000);
        }
    }
}
