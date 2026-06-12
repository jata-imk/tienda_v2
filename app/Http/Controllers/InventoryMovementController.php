<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryMovement\CreateMovementRequest;
use App\Http\Resources\InventoryMovement\InventoryMovementResource;
use App\Services\InventoryMovementService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * @group Inventory
 *
 * @authenticated
 */
class InventoryMovementController extends Controller
{
    public function __construct(private InventoryMovementService $movementService) {}

    /**
     * Register inventory movement
     *
     * Registra una entrada/salida/ajuste sobre una variante y actualiza su existencia.
     * Tipos que incrementan: `entry`, `return`, `cancel`. Tipos que disminuyen: `sale`, `adjustment`.
     *
     * @bodyParam idProductVariant integer required Variant ID. Example: 1
     * @bodyParam movementType string required entry|sale|adjustment|return|cancel. Example: entry
     * @bodyParam quantity number required Cantidad movida (magnitud positiva). Example: 5
     * @bodyParam referenceType string Origen del movimiento. Example: manual_adjustment
     * @bodyParam referenceId integer Documento origen, si aplica. Example: null
     * @bodyParam notes string Comentario. Example: Entrada manual de mercancia
     * @bodyParam idUser integer required Usuario que genera el movimiento. Example: 1
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Movement registered.","data":{}}
     * @response 422 {"ok":false,"code":422,"status":"Unprocessable Entity","message":"La existencia no puede quedar negativa.","data":null}
     */
    public function store(CreateMovementRequest $request): JsonResponse
    {
        try {
            $movement = $this->movementService->register($request->toDTO());
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created('Movement registered.', new InventoryMovementResource($movement));
    }
}
