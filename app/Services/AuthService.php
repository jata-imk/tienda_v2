<?php

namespace App\Services;

use App\DTOs\Auth\LoginDTO;
use App\Models\Company;
use App\Models\Sesion;
use App\Models\Token;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function login(LoginDTO $dto): array
    {
        $usuario = Usuario::where('username', $dto->username)->first();

        if (!$usuario || $usuario->status !== 'activo') {
            return ['result' => 'error', 'message' => 'Usuario no encontrado o inactivo', 'data' => null];
        }

        if (!Hash::check($dto->password, $usuario->password)) {
            return ['result' => 'error', 'message' => 'Contraseña incorrecta', 'data' => null];
        }

        $jwtToken = $this->resolveToken($usuario);

        $company = Company::first();

        return [
            'result'  => 'ok',
            'message' => 'Inicio de sesión exitoso',
            'data'    => [
                'token'   => $jwtToken,
                'empresa' => $this->formatCompany($company),
                'user'    => $this->formatUser($usuario),
            ],
        ];
    }

    private function resolveToken(Usuario $usuario): string
    {
        // Buscar sesión vigente del usuario
        $sesion = Sesion::where('user_id', $usuario->id)
            ->where('status', 'vigente')
            ->with('token')
            ->latest('date_start')
            ->first();

        if ($sesion) {
            // Sesión existe — verificar si el token sigue vigente
            if ($sesion->token->status === 'vigente' && Carbon::now()->lt($sesion->token->date_expiration)) {
                return $sesion->token->token;
            }

            // Token caducado — marcar sesión y token como finalizados
            $sesion->token->update(['status' => 'caducado']);
            $sesion->update(['status' => 'finalizado', 'date_end' => Carbon::now()]);
        }

        // Crear nuevo JWT y registrar en BD
        return $this->createTokenAndSession($usuario);
    }

    private function createTokenAndSession(Usuario $usuario): string
    {
        $expiration = Carbon::now()->addHours(24);

        $jwtString = JWTAuth::fromUser($usuario);

        $token = Token::create([
            'status'          => 'vigente',
            'token'           => $jwtString,
            'date_start'      => Carbon::now(),
            'date_expiration' => $expiration,
        ]);

        Sesion::create([
            'user_id'    => $usuario->id,
            'token_id'   => $token->id,
            'status'     => 'vigente',
            'date_start' => Carbon::now(),
        ]);

        return $jwtString;
    }

    private function formatUser(Usuario $usuario): array
    {
        return [
            'nombre'          => $usuario->name,
            'primerApellido'  => $usuario->first_name,
            'segundoApellido' => $usuario->last_name,
            'usuario'         => $usuario->username,
            'email'           => $usuario->email,
            'tipoUsuario'     => (string) $usuario->user_type_id,
            'permisos'        => [],
        ];
    }

    private function formatCompany(?Company $company): array
    {
        if (!$company) {
            return [];
        }

        return [
            'nombre'      => $company->company_name,
            'logo'        => $company->img,
            'modoOscuro'  => false,
            'configImp'   => [],
            'fechaUpdate' => $company->date_creation?->toDateTimeString(),
            'settings'    => ['grids' => []],
        ];
    }
}
