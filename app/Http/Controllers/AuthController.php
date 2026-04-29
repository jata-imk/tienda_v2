<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Autenticación
 *
 * Endpoints para el manejo de sesiones de usuario.
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Inicio de sesión
     *
     * Valida credenciales, gestiona la sesión y retorna un JWT válido por 24 horas
     * junto con los datos del usuario y la empresa.
     *
     * Si el usuario ya tiene una sesión vigente con token activo, se reutiliza el mismo JWT.
     * Si el token expiró, se genera uno nuevo y se cierra la sesión anterior.
     *
     * @unauthenticated
     *
     * @bodyParam usurrio string required Nombre de usuario. Example: suriel.dzul
     * @bodyParam pass string required Contraseña en texto plano. Example: suriel2024
     *
     * @response 200 scenario="Login exitoso" {
     *   "result": "ok",
     *   "message": "Inicio de sesión exitoso",
     *   "data": {
     *     "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "empresa": {
     *       "nombre": "Guayaberas Lopez Silva",
     *       "logo": null,
     *       "modoOscuro": false,
     *       "configImp": [],
     *       "fechaUpdate": "2024-01-01 00:00:00",
     *       "settings": { "grids": [] }
     *     },
     *     "user": {
     *       "nombre": "Suriel",
     *       "primerApellido": "Dzul",
     *       "segundoApellido": "Dzul",
     *       "usuario": "suriel.dzul",
     *       "email": "dzulsuriel@gmail.com",
     *       "tipoUsuario": "1",
     *       "permisos": []
     *     }
     *   }
     * }
     *
     * @response 401 scenario="Credenciales incorrectas" {
     *   "result": "error",
     *   "message": "Contraseña incorrecta",
     *   "data": null
     * }
     *
     * @response 401 scenario="Usuario inactivo o no existe" {
     *   "result": "error",
     *   "message": "Usuario no encontrado o inactivo",
     *   "data": null
     * }
     *
     * @response 422 scenario="Campos faltantes" {
     *   "message": "The usurrio field is required.",
     *   "errors": { "usurrio": ["The usurrio field is required."] }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'usurrio' => 'required|string',
            'pass'    => 'required|string',
        ]);

        $result = $this->authService->login(
            $request->input('usurrio'),
            $request->input('pass')
        );

        $status = $result['result'] === 'ok' ? 200 : 401;

        return response()->json($result, $status);
    }
}
