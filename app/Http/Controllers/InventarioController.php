<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventario\ActualizarInventarioRequest;
use App\Http\Requests\Inventario\CrearInventarioRequest;
use App\Http\Resources\Inventario\InventarioResource;
use App\Services\InventarioService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Inventario
 */
class InventarioController extends Controller
{
    public function __construct(private InventarioService $inventarioService) {}

    /**
     * Listar productos
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Productos obtenidos.","data":[{"id":1,"idCategory":1,"category":"Camisas lino","status":"activo","key":"000001","name":"Guayabera blanca","description":"Guayabera caballero 100% lino","codebar":"8888888888881","price":800,"cost":600,"stockControl":true,"stock":20,"discount":0,"typeIVA":1,"tipoIva":"general","rateIVA":null,"quotaIVA":null,"ISR":0,"impESP":0,"dateCreation":"2024-01-01 00:00:00"}]}
     */
    public function index(): JsonResponse
    {
        $productos = $this->inventarioService->index();

        return ApiResponse::ok('Productos obtenidos.', InventarioResource::collection($productos));
    }

    /**
     * Ver producto
     *
     * @urlParam id integer required ID del producto. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Producto obtenido.","data":{"id":1,"idCategory":1,"category":"Camisas lino","status":"activo","key":"000001","name":"Guayabera blanca","description":"Guayabera caballero 100% lino","codebar":"8888888888881","price":800,"cost":600,"stockControl":true,"stock":20,"discount":0,"typeIVA":1,"tipoIva":"general","rateIVA":null,"quotaIVA":null,"ISR":0,"impESP":0,"dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Producto no encontrado.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $producto = $this->inventarioService->show($id);

        if (!$producto) {
            return ApiResponse::error('Producto no encontrado.', 404);
        }

        return ApiResponse::ok('Producto obtenido.', new InventarioResource($producto));
    }

    /**
     * Crear producto
     *
     * @bodyParam category_id integer required ID de la categoría. Example: 1
     * @bodyParam clave string required Clave interna única del producto. Example: 000001
     * @bodyParam name string required Nombre del producto. Example: Guayabera blanca
     * @bodyParam description string optional Descripción. Example: Guayabera caballero 100% lino
     * @bodyParam codebar string optional Código de barras. Example: 8888888888881
     * @bodyParam price number required Precio base (sin IVA). Example: 800
     * @bodyParam cost number required Costo. Example: 600
     * @bodyParam stock_control boolean required Activar control de stock. Example: true
     * @bodyParam stock number required Stock inicial. Example: 20
     * @bodyParam discount number optional Porcentaje de descuento (0-100). Example: 0
     * @bodyParam type_iva_id integer required ID del tipo de IVA (1=general, 2=tasa, 3=cuota, 4=no aplica). Example: 1
     * @bodyParam rate_iva number optional Tasa IVA específica. Requerido si type_iva_id=2. Example: null
     * @bodyParam quota_iva number optional Cuota IVA fija por unidad. Requerido si type_iva_id=3. Example: null
     * @bodyParam isr number optional Porcentaje ISR (0-100). Example: 0
     * @bodyParam imp_esp number optional Porcentaje impuesto especial (0-100). Example: 0
     * @bodyParam status string optional Estado. Valores: activo, baja. Example: activo
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Producto creado.","data":{"id":1,"idCategory":1,"category":"Camisas lino","status":"activo","key":"000001","name":"Guayabera blanca","description":"Guayabera caballero 100% lino","codebar":"8888888888881","price":800,"cost":600,"stockControl":true,"stock":20,"discount":0,"typeIVA":1,"tipoIva":"general","rateIVA":null,"quotaIVA":null,"ISR":0,"impESP":0,"dateCreation":"2024-01-01 00:00:00"}}
     * @response 422 {"message":"The clave has already been taken.","errors":{"clave":["The clave has already been taken."]}}
     */
    public function store(CrearInventarioRequest $request): JsonResponse
    {
        $producto = $this->inventarioService->store($request->toDTO());

        return ApiResponse::created('Producto creado.', new InventarioResource($producto));
    }

    /**
     * Actualizar producto
     *
     * Todos los campos son opcionales. Solo enviar los que se quieran modificar.
     *
     * @urlParam id integer required ID del producto. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Producto actualizado.","data":{"id":1,"idCategory":1,"category":"Camisas lino","status":"activo","key":"000001","name":"Guayabera blanca","description":"Guayabera caballero 100% lino","codebar":"8888888888881","price":900,"cost":600,"stockControl":true,"stock":20,"discount":0,"typeIVA":1,"tipoIva":"general","rateIVA":null,"quotaIVA":null,"ISR":0,"impESP":0,"dateCreation":"2024-01-01 00:00:00"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Producto no encontrado.","data":null}
     */
    public function update(ActualizarInventarioRequest $request, int $id): JsonResponse
    {
        $producto = $this->inventarioService->update($id, $request->toDTO());

        if (!$producto) {
            return ApiResponse::error('Producto no encontrado.', 404);
        }

        return ApiResponse::ok('Producto actualizado.', new InventarioResource($producto));
    }

    /**
     * Dar de baja producto
     *
     * Cambia el status del producto a `baja`. No elimina el registro.
     *
     * @urlParam id integer required ID del producto. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Producto dado de baja.","data":null}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Producto no encontrado.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        $encontrado = $this->inventarioService->destroy($id);

        if (!$encontrado) {
            return ApiResponse::error('Producto no encontrado.', 404);
        }

        return ApiResponse::ok('Producto dado de baja.');
    }
}
