<?php

namespace App\Services;

use App\DTOs\Auth\LoginDTO;
use App\Models\CompanyInfo;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function login(LoginDTO $dto): array
    {
        $user = User::where('user_name', $dto->userName)->first();

        if (!$user || $user->status !== 'active') {
            return ['result' => 'error', 'message' => 'User not found or inactive', 'data' => null];
        }

        if (!Hash::check($dto->password, $user->password)) {
            return ['result' => 'error', 'message' => 'Incorrect password', 'data' => null];
        }

        $token   = $this->resolveToken($user);
        $company = CompanyInfo::first();

        return [
            'result'  => 'ok',
            'message' => 'Login successful',
            'data'    => [
                'token'      => $token,
                'companyInfo' => $this->formatCompany($company),
                'user'       => $this->formatUser($user),
            ],
        ];
    }

    public function logout(string $tokenHash): bool
    {
        $session = UserSession::where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (!$session) {
            return false;
        }

        $session->update(['revoked_at' => Carbon::now()]);

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
            return $activeSession->token_hash;
        }

        return $this->createSession($user);
    }

    private function createSession(User $user): string
    {
        $jwtString = JWTAuth::fromUser($user);

        UserSession::create([
            'id_user'    => $user->id,
            'token_hash' => $jwtString,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        return $jwtString;
    }

    private function formatUser(User $user): array
    {
        return [
            'firstName' => $user->first_name,
            'lastName'  => $user->last_name,
            'userName'  => $user->user_name,
            'email'     => $user->email,
            'userType'  => $user->id_user_type,
        ];
    }

    private function formatCompany(?CompanyInfo $company): array
    {
        if (!$company) {
            return [];
        }

        return [
            'name'         => $company->name,
            'logo'         => $company->logo,
            'gridSettings' => $company->grid_settings ?? [],
            'status'       => $company->status,
            'updatedAt'    => $company->updated_at?->toDateTimeString(),
        ];
    }
}
