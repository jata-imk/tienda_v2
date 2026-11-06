<?php

namespace App\Http\Middleware;

use App\Exceptions\AuditUnavailableException;
use App\Models\UserSession;
use App\Services\AuditTrail;
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
            if (! $this->auth->parseToken()->authenticate()) {
                return $this->deny('Usuario no encontrado.', 'user_not_found');
            }
        } catch (TokenBlacklistedException) {
            return $this->deny('Sesión revocada. El token fue invalidado (logout previo).', 'token_blacklisted');
        } catch (TokenExpiredException) {
            return $this->deny('Token expirado. Inicia sesión nuevamente.', 'token_expired', $this->expiryClaims($request));
        } catch (TokenInvalidException) {
            return $this->deny('Token inválido o malformado.', 'token_invalid');
        } catch (JWTException) {
            return $this->deny('No se proporcionó token de autenticación.', 'token_missing');
        }

        $token = $request->bearerToken();
        $session = UserSession::where('token_hash', $token)->first();

        if (! $session) {
            return $this->deny('Sesión no registrada.', 'session_unregistered');
        }

        if ($session->revoked_at !== null) {
            return $this->deny('Sesión revocada.', 'session_revoked');
        }

        if ($session->expires_at->isPast()) {
            return $this->deny('Sesión expirada. Inicia sesión nuevamente.', 'session_expired');
        }

        $user = $request->user();
        $user?->loadMissing('userType');

        if (! $user || $user->status !== 'active' || $user->userType?->status !== 'active') {
            if ($user) {
                $audit = app(AuditTrail::class);
                try {
                    $audit->run('security', 'auth.sessions_revoked', fn () => $audit->bulkUpdate(
                        UserSession::where('id_user', $user->id)->whereNull('revoked_at'),
                        ['revoked_at' => now()],
                    ));
                } catch (AuditUnavailableException) {
                    // La cuenta sigue rechazada aunque la bitácora no esté disponible.
                }
            }

            return $this->deny('La cuenta o su rol están inactivos. Inicia sesión nuevamente.', 'user_or_role_inactive');
        }

        app(AuditTrail::class)->identify($user);

        return $next($request);
    }

    private function deny(string $message, string $reason, ?array $data = null): Response
    {
        app(AuditTrail::class)->metadata(['reason' => $reason]);

        return ApiResponse::error($message, 401, $data);
    }

    private function expiryClaims(Request $request): ?array
    {
        try {
            $token = $request->bearerToken();
            if (! $token) {
                return null;
            }
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            return [
                'issuedAt' => isset($payload['iat']) ? date('Y-m-d H:i:s', $payload['iat']) : null,
                'expiredAt' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
