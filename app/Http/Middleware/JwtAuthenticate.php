<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private JWTAuth $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!$this->auth->parseToken()->authenticate()) {
                return ApiResponse::error('Usuario no encontrado.', 401);
            }
        } catch (TokenBlacklistedException) {
            return ApiResponse::error('Sesión revocada. El token fue invalidado (logout previo).', 401);
        } catch (TokenExpiredException) {
            return ApiResponse::error('Token expirado. Inicia sesión nuevamente.', 401, $this->expiryClaims($request));
        } catch (TokenInvalidException) {
            return ApiResponse::error('Token inválido o malformado.', 401);
        } catch (JWTException) {
            return ApiResponse::error('No se proporcionó token de autenticación.', 401);
        }

        $token   = $request->bearerToken();
        $session = UserSession::where('token_hash', $token)->first();

        if (!$session) {
            return ApiResponse::error('Sesión no registrada.', 401);
        }

        if ($session->revoked_at !== null) {
            return ApiResponse::error('Sesión revocada.', 401);
        }

        if ($session->expires_at->isPast()) {
            return ApiResponse::error('Sesión expirada. Inicia sesión nuevamente.', 401);
        }

        return $next($request);
    }

    private function expiryClaims(Request $request): ?array
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return null;
            }
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            return [
                'issuedAt'  => isset($payload['iat']) ? date('Y-m-d H:i:s', $payload['iat']) : null,
                'expiredAt' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
