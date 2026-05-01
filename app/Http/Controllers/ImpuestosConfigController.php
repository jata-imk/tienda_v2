<?php

namespace App\Http\Controllers;

use App\Http\Requests\Config\ActualizarImpuestosConfigRequest;
use App\Http\Resources\Config\ImpuestosConfigResource;
use App\Services\ImpuestosConfigService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Configuración
 */
class ImpuestosConfigController extends Controller
{
    public function __construct(private ImpuestosConfigService $impuestosConfigService) {}

    /**
     * Ver configuración de impuestos base
     *
     * Retorna los porcentajes base de IVA, ISR e impuesto especial.
     *
     * @response 200 {
     *   "ok": true,
     *   "code": 200,
     *   "status": "OK",
     *   "message": "Configuración de impuestos obtenida.",
     *   "data": { "iva": 16, "isr": 10, "impEsp": 0, "dateCreation": "2024-01-01 00:00:00", "dateUpdate": null }
     * }
     */
    public function show(): JsonResponse
    {
        $config = $this->impuestosConfigService->get();

        return ApiResponse::ok('Configuración de impuestos obtenida.', new ImpuestosConfigResource($config));
    }

    /**
     * Actualizar configuración de impuestos base
     *
     * Actualiza uno o más porcentajes base. Solo se modifican los campos enviados.
     *
     * @bodyParam iva number optional Porcentaje IVA base (0-100). Example: 8
     * @bodyParam isr number optional Porcentaje ISR base (0-100). Example: 10
     * @bodyParam imp_esp number optional Porcentaje impuesto especial base (0-100). Example: 0
     *
     * @response 200 {
     *   "ok": true,
     *   "code": 200,
     *   "status": "OK",
     *   "message": "Configuración de impuestos actualizada.",
     *   "data": { "iva": 8, "isr": 10, "impEsp": 0, "dateCreation": "2024-01-01 00:00:00", "dateUpdate": "2024-01-02 00:00:00" }
     * }
     */
    public function update(ActualizarImpuestosConfigRequest $request): JsonResponse
    {
        $config = $this->impuestosConfigService->update($request->toDTO());

        return ApiResponse::ok('Configuración de impuestos actualizada.', new ImpuestosConfigResource($config));
    }
}
