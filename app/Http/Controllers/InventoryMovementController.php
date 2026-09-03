<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryDomainException;
use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\InventoryMovement\CreateMovementRequest;
use App\Http\Resources\InventoryMovement\InventoryMovementResource;
use App\Services\InventoryMovementService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Inventory
 *
 * @authenticated
 */
class InventoryMovementController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(private InventoryMovementService $movementService) {}

    /**
     * List inventory movements (Kardex)
     *
     * Supports filters: `p[page]`, `p[per_page]`, `f[]`, `o[column]`, `o[direction]`, `w[column]`, `totalCount`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Inventory movements retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Inventory movements retrieved.', $this->movementService->index($filters), InventoryMovementResource::class);
    }

    /**
     * Query inventory movements (POST)
     *
     * Same payload formats as `POST /products/query`.
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Inventory movements retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Inventory movements retrieved.', $this->movementService->index($filters), InventoryMovementResource::class);
    }

    /**
     * Register inventory movements
     *
     * Registra uno o varios movimientos sobre variantes de un mismo producto y
     * actualiza su existencia de forma atomica: si cualquier elemento de
     * `movements` falla, no se aplica ninguno. Tipos que incrementan: `entry`,
     * `return`, `cancel`. Tipos que disminuyen: `sale`, `adjustment`.
     *
     * @bodyParam idProduct integer required Producto al que pertenecen todas las variantes. Example: 25
     * @bodyParam idUser integer required Usuario que genera los movimientos (debe ser el de la sesión). Example: 1
     * @bodyParam referenceType string Origen de los movimientos. Example: manual_adjustment
     * @bodyParam referenceId integer Documento origen, si aplica. Example: null
     * @bodyParam notes string Comentario general, aplicado a cada movimiento. Example: Ajuste por conteo físico
     * @bodyParam movements array required Uno o más movimientos de inventario.
     * @bodyParam movements[].idProductVariant integer required Variante a actualizar. Example: 101
     * @bodyParam movements[].movementType string required entry|sale|adjustment|return|cancel. Example: adjustment
     * @bodyParam movements[].quantity number required Cantidad movida (magnitud positiva). Example: 2
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Ajustes de inventario registrados correctamente.","data":{"movements":[{"id":501,"idProductVariant":101,"movementType":"adjustment","quantity":2,"previousStock":8,"newStock":6,"referenceType":"manual_adjustment","referenceId":null,"notes":"Ajuste por conteo físico","idUser":1,"createdAt":"2026-08-12 21:45:00"}],"totalStock":13}}
     * @response 422 {"ok":false,"code":422,"status":"Unprocessable Entity","message":"La variante Negro / M no tiene existencia suficiente.","data":{"idProductVariant":101,"currentStock":1,"requestedQuantity":2}}
     */
    public function store(CreateMovementRequest $request): JsonResponse
    {
        try {
            $result = $this->movementService->register($request->toDTO());
        } catch (DomainException $e) {
            $context = $e instanceof InventoryDomainException ? $e->context : null;

            return ApiResponse::error($e->getMessage(), 422, $context);
        }

        $message = count($result['movements']) === 1
            ? 'Ajuste de inventario registrado correctamente.'
            : 'Ajustes de inventario registrados correctamente.';

        return ApiResponse::created($message, [
            'movements'  => InventoryMovementResource::collection(collect($result['movements'])),
            'totalStock' => $result['totalStock'],
        ]);
    }
}
