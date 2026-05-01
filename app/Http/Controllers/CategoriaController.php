<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categoria\ActualizarCategoriaRequest;
use App\Http\Requests\Categoria\CrearCategoriaRequest;
use App\Http\Resources\Inventario\CategoriaResource;
use App\Services\CategoriaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Inventario
 *
 * Gestión del inventario de productos. Todos los endpoints requieren JWT y rol administrador.
 *
 * @authenticated
 */
class CategoriaController extends Controller
{
    public function __construct(private CategoriaService $categoriaService) {}

    /**
     * Listar categorías
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categorías obtenidas.","data":[{"id":1,"status":"activo","name":"Camisas lino","description":"Camisas de todos tipos de lino","dateCreation":"2024-01-01 00:00:00"}]}
     */
    public function index(): JsonResponse
    {
        $categorias = $this->categoriaService->index();

        return ApiResponse::ok('Categorías obtenidas.', CategoriaResource::collection($categorias));
    }

    /**
     * Ver categoría
     *
     * @urlParam id integer required ID de la categoría. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categoría obtenida.","data":{"id":1,"status":"activo","name":"Camisas lino","description":"Camisas de todos tipos de lino","dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Categoría no encontrada.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $categoria = $this->categoriaService->show($id);

        if (!$categoria) {
            return ApiResponse::error('Categoría no encontrada.', 404);
        }

        return ApiResponse::ok('Categoría obtenida.', new CategoriaResource($categoria));
    }

    /**
     * Crear categoría
     *
     * @bodyParam name string required Nombre de la categoría. Example: Camisas lino
     * @bodyParam description string optional Descripción. Example: Camisas de todos tipos de lino
     * @bodyParam status string optional Estado. Valores: activo, inactivo. Example: activo
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Categoría creada.","data":{"id":1,"status":"activo","name":"Camisas lino","description":"Camisas de todos tipos de lino","dateCreation":"2024-01-01 00:00:00"}}
     */
    public function store(CrearCategoriaRequest $request): JsonResponse
    {
        $categoria = $this->categoriaService->store($request->toDTO());

        return ApiResponse::created('Categoría creada.', new CategoriaResource($categoria));
    }

    /**
     * Actualizar categoría
     *
     * @urlParam id integer required ID de la categoría. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categoría actualizada.","data":{"id":1,"status":"activo","name":"Camisas lino","description":"Camisas de todos tipos de lino","dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Categoría no encontrada.","data":null}
     */
    public function update(ActualizarCategoriaRequest $request, int $id): JsonResponse
    {
        $categoria = $this->categoriaService->update($id, $request->toDTO());

        if (!$categoria) {
            return ApiResponse::error('Categoría no encontrada.', 404);
        }

        return ApiResponse::ok('Categoría actualizada.', new CategoriaResource($categoria));
    }

    /**
     * Desactivar categoría
     *
     * @urlParam id integer required ID de la categoría. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Categoría desactivada.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Categoría no encontrada.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        $encontrada = $this->categoriaService->destroy($id);

        if (!$encontrada) {
            return ApiResponse::error('Categoría no encontrada.', 404);
        }

        return ApiResponse::ok('Categoría desactivada.');
    }
}
