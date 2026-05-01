<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuario\ActualizarUsuarioRequest;
use App\Http\Requests\Usuario\CrearUsuarioRequest;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;

/**
 * @group Usuarios
 *
 * Gestión de usuarios del sistema. Todos los endpoints requieren JWT y rol administrador.
 *
 * @authenticated
 */
class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $usuarioService) {}

    /**
     * Listar usuarios
     *
     * Retorna todos los usuarios registrados en el sistema.
     *
     * @response 200 {
     *   "result": "ok",
     *   "message": "Usuarios obtenidos.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombre": "Suriel",
     *       "primerApellido": "Dzul",
     *       "segundoApellido": "Dzul",
     *       "usuario": "suriel.dzul",
     *       "email": "dzulsuriel@gmail.com",
     *       "tipoUsuario": "administrador",
     *       "status": "activo",
     *       "dateCreation": "2024-01-01 00:00:00"
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $usuarios = $this->usuarioService->index();

        return response()->json([
            'result'  => 'ok',
            'message' => 'Usuarios obtenidos.',
            'data'    => UsuarioResource::collection($usuarios),
        ]);
    }

    /**
     * Ver usuario
     *
     * Retorna los datos de un usuario específico.
     *
     * @urlParam id integer required ID del usuario. Example: 1
     *
     * @response 200 {"result":"ok","message":"Usuario obtenido.","data":{"id":1,"nombre":"Suriel","primerApellido":"Dzul","segundoApellido":"Dzul","usuario":"suriel.dzul","email":"dzulsuriel@gmail.com","tipoUsuario":"administrador","status":"activo","dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"result":"error","message":"Usuario no encontrado.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->show($id);

        if (!$usuario) {
            return response()->json(['result' => 'error', 'message' => 'Usuario no encontrado.', 'data' => null], 404);
        }

        return response()->json([
            'result'  => 'ok',
            'message' => 'Usuario obtenido.',
            'data'    => new UsuarioResource($usuario),
        ]);
    }

    /**
     * Crear usuario
     *
     * Crea un nuevo usuario en el sistema.
     *
     * @bodyParam name string required Nombre. Example: Juan
     * @bodyParam first_name string required Primer apellido. Example: Pérez
     * @bodyParam last_name string required Segundo apellido. Example: López
     * @bodyParam username string required Nombre de usuario único. Example: juan.perez
     * @bodyParam email string required Correo electrónico único. Example: juan@empresa.com
     * @bodyParam password string required Contraseña (mínimo 8 caracteres). Example: secreto123
     * @bodyParam user_type_id integer required ID del tipo de usuario. Example: 1
     * @bodyParam status string optional Estado inicial. Valores: activo, inactivo. Example: activo
     *
     * @response 201 {"result":"ok","message":"Usuario creado.","data":{"id":2,"nombre":"Juan","primerApellido":"Pérez","segundoApellido":"López","usuario":"juan.perez","email":"juan@empresa.com","tipoUsuario":"administrador","status":"activo","dateCreation":"2024-01-01 00:00:00"}}
     * @response 422 {"message":"The username has already been taken.","errors":{"username":["The username has already been taken."]}}
     */
    public function store(CrearUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->store($request->toDTO());

        return response()->json([
            'result'  => 'ok',
            'message' => 'Usuario creado.',
            'data'    => new UsuarioResource($usuario),
        ], 201);
    }

    /**
     * Actualizar usuario
     *
     * Actualiza los datos de un usuario. Todos los campos son opcionales excepto los que se quieran cambiar.
     * Para cambiar la contraseña, incluir el campo `password`.
     *
     * @urlParam id integer required ID del usuario. Example: 1
     *
     * @bodyParam name string optional Nombre. Example: Juan
     * @bodyParam first_name string optional Primer apellido. Example: Pérez
     * @bodyParam last_name string optional Segundo apellido. Example: López
     * @bodyParam username string optional Nombre de usuario único. Example: juan.perez2
     * @bodyParam email string optional Correo electrónico único. Example: juan2@empresa.com
     * @bodyParam password string optional Nueva contraseña (mínimo 8 caracteres). Example: nuevapass123
     * @bodyParam user_type_id integer optional ID del tipo de usuario. Example: 1
     * @bodyParam status string optional Estado. Valores: activo, inactivo. Example: activo
     *
     * @response 200 {"result":"ok","message":"Usuario actualizado.","data":{"id":1,"nombre":"Juan","primerApellido":"Pérez","segundoApellido":"López","usuario":"juan.perez2","email":"juan2@empresa.com","tipoUsuario":"administrador","status":"activo","dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"result":"error","message":"Usuario no encontrado.","data":null}
     */
    public function update(ActualizarUsuarioRequest $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->update($id, $request->toDTO());

        if (!$usuario) {
            return response()->json(['result' => 'error', 'message' => 'Usuario no encontrado.', 'data' => null], 404);
        }

        return response()->json([
            'result'  => 'ok',
            'message' => 'Usuario actualizado.',
            'data'    => new UsuarioResource($usuario),
        ]);
    }

    /**
     * Desactivar usuario
     *
     * Cambia el status del usuario a `inactivo`. No elimina el registro (soft-delete por status).
     *
     * @urlParam id integer required ID del usuario. Example: 1
     *
     * @response 200 {"result":"ok","message":"Usuario desactivado.","data":null}
     * @response 404 {"result":"error","message":"Usuario no encontrado.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        $encontrado = $this->usuarioService->destroy($id);

        if (!$encontrado) {
            return response()->json(['result' => 'error', 'message' => 'Usuario no encontrado.', 'data' => null], 404);
        }

        return response()->json(['result' => 'ok', 'message' => 'Usuario desactivado.', 'data' => null]);
    }
}
