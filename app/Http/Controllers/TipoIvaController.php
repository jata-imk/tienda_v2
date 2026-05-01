<?php

namespace App\Http\Controllers;

use App\Http\Resources\Config\TipoIvaResource;
use App\Services\TipoIvaService;
use Illuminate\Http\JsonResponse;
use App\Support\ApiResponse;

/**
 * @group Configuración
 *
 * Catálogos y configuración del sistema. Todos los endpoints requieren JWT y rol administrador.
 *
 * @authenticated
 */
class TipoIvaController extends Controller
{
    public function __construct(private TipoIvaService $tipoIvaService) {}

    /**
     * Listar tipos de IVA
     *
     * Retorna el catálogo completo de tipos de IVA disponibles.
     *
     * @response 200 {
     *   "ok": true,
     *   "code": 200,
     *   "status": "OK",
     *   "message": "Tipos de IVA obtenidos.",
     *   "data": [
     *     { "id": 1, "name": "general", "description": "General (base: 16%)", "dateCreation": "2024-01-01 00:00:00" },
     *     { "id": 2, "name": "tasa_producto", "description": "Tasa por producto", "dateCreation": "2024-01-01 00:00:00" },
     *     { "id": 3, "name": "cuota_producto", "description": "Cuota por producto", "dateCreation": "2024-01-01 00:00:00" },
     *     { "id": 4, "name": "no_aplica", "description": "No aplica", "dateCreation": "2024-01-01 00:00:00" }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $tipos = $this->tipoIvaService->index();

        return ApiResponse::ok('Tipos de IVA obtenidos.', TipoIvaResource::collection($tipos));
    }
}
