<?php

namespace App\Services;

use App\DTOs\Auth\LoginDTO;
use App\Http\Resources\Currency\CurrencyResource;
use App\Models\CompanyInfo;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(private CatalogService $catalogService) {}

    public function login(LoginDTO $dto): array
    {
        $user = User::with('userType')->where('user_name', $dto->userName)->first();

        if (! $user || $user->status !== 'active' || $user->userType?->status !== 'active') {
            return ['result' => 'error', 'message' => 'User not found or inactive', 'data' => null];
        }

        if (! Hash::check($dto->password, $user->password)) {
            return ['result' => 'error', 'message' => 'Incorrect password', 'data' => null];
        }

        $token = $this->resolveToken($user);
        $company = CompanyInfo::with('currency')->first();

        return [
            'result' => 'ok',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'companyInfo' => $this->formatCompany($company),
                'user' => $this->formatUser($user),
                'catalogs' => $this->catalogService->all(),
            ],
        ];
    }

    public function logout(string $tokenHash): bool
    {
        $session = UserSession::where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (! $session) {
            return false;
        }

        $session->update(['revoked_at' => Carbon::now()]);

        try {
            JWTAuth::setToken($tokenHash)->invalidate();
        } catch (JWTException) {
            // Token ya inválido, ignorar
        }

        return true;
    }

    private function resolveToken(User $user): string
    {
        $activeSession = UserSession::where('id_user', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if ($activeSession) {
            try {
                JWTAuth::setToken($activeSession->token_hash)->checkOrFail();

                return $activeSession->token_hash;
            } catch (JWTException) {
                // JWT expiró aunque la sesión BD siga "activa" (datos viejos pre-fix)
                $activeSession->update(['revoked_at' => Carbon::now()]);
            }
        }

        return $this->createSession($user);
    }

    private function createSession(User $user): string
    {
        $jwtString = JWTAuth::fromUser($user);

        UserSession::create([
            'id_user' => $user->id,
            'token_hash' => $jwtString,
            'expires_at' => Carbon::now()->addMinutes(config('jwt.ttl')),
        ]);

        return $jwtString;
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'userName' => $user->user_name,
            'email' => $user->email,
            'userType' => $user->id_user_type,
            'roleCode' => $user->userType->code,
            'roleName' => $user->userType->name,
        ];
    }

    private function formatCompany(?CompanyInfo $company): array
    {
        if (! $company) {
            return [];
        }

        return [
            'name' => $company->name,
            'logo' => $company->logo,
            // Misma forma que en GET /api/company-info: una sola fuente de verdad.
            'currency' => $company->currency ? (new CurrencyResource($company->currency))->resolve() : null,
            'gridSettings' => $company->grid_settings ?? [],
            'status' => $company->status,
            'updatedAt' => $company->updated_at?->toDateTimeString(),
        ];
    }
}
