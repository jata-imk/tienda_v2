<?php

namespace App\Http\Controllers;

use App\Http\Requests\Config\ActualizarTipoMonedaRequest;
use App\Http\Requests\Config\CrearTipoMonedaRequest;
use App\Http\Resources\Config\TipoMonedaResource;
use App\Services\TipoMonedaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Configuración
 */
class TipoMonedaController extends Controller
{
    public function __construct(private TipoMonedaService $tipoMonedaService) {}

    /**
     * Listar tipos de moneda
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Tipos de moneda obtenidos.","data":[{"id":1,"status":"activo","name":"Pesos Mexicanos","code":"MXN","symbol":"$","dateCreation":"2024-01-01 00:00:00"}]}
     */
    public function index(): JsonResponse
    {
        $monedas = $this->tipoMonedaService->index();

        return ApiResponse::ok('Tipos de moneda obtenidos.', TipoMonedaResource::collection($monedas));
    }

    /**
     * Ver tipo de moneda
     *
     * @urlParam id integer required ID del tipo de moneda. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Tipo de moneda obtenido.","data":{"id":1,"status":"activo","name":"Pesos Mexicanos","code":"MXN","symbol":"$","dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Tipo de moneda no encontrado.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $moneda = $this->tipoMonedaService->show($id);

        if (!$moneda) {
            return ApiResponse::error('Tipo de moneda no encontrado.', 404);
        }

        return ApiResponse::ok('Tipo de moneda obtenido.', new TipoMonedaResource($moneda));
    }

    /**
     * Crear tipo de moneda
     *
     * @bodyParam name string required Nombre de la moneda. Example: Dólar Americano
     * @bodyParam code string required Código ISO (3 letras). Example: USD
     * @bodyParam symbol string required Símbolo. Example: $
     * @bodyParam status string optional Estado. Valores: activo, inactivo. Example: activo
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Tipo de moneda creado.","data":{"id":2,"status":"activo","name":"Dólar Americano","code":"USD","symbol":"$","dateCreation":"2024-01-01 00:00:00"}}
     * @response 422 {"message":"The code has already been taken.","errors":{"code":["The code has already been taken."]}}
     */
    public function store(CrearTipoMonedaRequest $request): JsonResponse
    {
        $moneda = $this->tipoMonedaService->store($request->toDTO());

        return ApiResponse::created('Tipo de moneda creado.', new TipoMonedaResource($moneda));
    }

    /**
     * Actualizar tipo de moneda
     *
     * @urlParam id integer required ID del tipo de moneda. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Tipo de moneda actualizado.","data":{"id":1,"status":"activo","name":"Pesos Mexicanos","code":"MXN","symbol":"$","dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Tipo de moneda no encontrado.","data":null}
     */
    public function update(ActualizarTipoMonedaRequest $request, int $id): JsonResponse
    {
        $moneda = $this->tipoMonedaService->update($id, $request->toDTO());

        if (!$moneda) {
            return ApiResponse::error('Tipo de moneda no encontrado.', 404);
        }

        return ApiResponse::ok('Tipo de moneda actualizado.', new TipoMonedaResource($moneda));
    }

    /**
     * Desactivar tipo de moneda
     *
     * @urlParam id integer required ID del tipo de moneda. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Tipo de moneda desactivado.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Tipo de moneda no encontrado.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        $encontrado = $this->tipoMonedaService->destroy($id);

        if (!$encontrado) {
            return ApiResponse::error('Tipo de moneda no encontrado.', 404);
        }

        return ApiResponse::ok('Tipo de moneda desactivado.');
    }
}
