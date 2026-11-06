<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\Audit\AuditQueryRequest;
use App\Services\AuditQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Audit
 *
 * @authenticated
 */
class AuditEventController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(private AuditQueryService $queries) {}

    /**
     * List audit events
     *
     * Solo administrador. Siempre paginado: 15 por defecto, máximo 100.
     * Soporta p, f, o, w, totalCount, dateFrom/dateTo y entityType/entityId.
     *
     * @queryParam p[page] integer Página desde 1. Example: 1
     * @queryParam p[per_page] integer Tamaño entre 1 y 100. Default 15. Example: 15
     * @queryParam totalCount boolean Incluir conteo total; false evita COUNT. Example: true
     * @queryParam dateFrom string Fecha inicial inclusiva. Example: 2026-11-01
     * @queryParam dateTo string Fecha final inclusiva. Example: 2026-11-06
     * @queryParam entityType string Entidad principal o afectada. Example: product
     * @queryParam entityId string ID de la entidad principal o afectada. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Audit events retrieved.","data":{"items":[],"page":1,"perPage":15,"hasMore":false,"totalCount":0,"summary":[0]}}
     */
    public function index(AuditQueryRequest $request): JsonResponse
    {
        return ApiResponse::ok('Audit events retrieved.', $this->queries->index($this->extractGridFilters($request), $request->validated()));
    }

    /**
     * Query audit events (POST)
     *
     * Mismos filtros que GET. p.page y p.r son desplazamientos desde cero.
     *
     * @bodyParam p object Paginación obligatoria en el servidor aunque se omita.
     * @bodyParam p.page integer Desplazamiento desde cero. Example: 0
     * @bodyParam p.per_page integer Tamaño entre 1 y 100. Default 15. Example: 15
     * @bodyParam totalCount boolean Incluir conteo total. Example: true
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Audit events retrieved.","data":{"items":[],"page":1,"perPage":15,"hasMore":false,"totalCount":0,"summary":[0]}}
     */
    public function query(AuditQueryRequest $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Get audit event metadata
     *
     * Los cambios se consultan paginados en /changes.
     *
     * @urlParam id integer required Identificador del evento. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Audit event retrieved.","data":{"id":1,"requestId":"019a55b7-cb40-7000-8000-000000000001","module":"orders","action":"orders.pdf_generated","outcome":"success","actorId":1,"actorName":"admin","roleCode":"administrator","roleName":"Administrador","entityType":"order","entityId":"1","source":"api","metadata":{"delivery":"inline","folio":"PED-0000001"},"occurredAt":"2026-11-06T18:00:00.000000Z"}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Audit event not found.","data":null}
     */
    public function show(int $id): JsonResponse
    {
        $event = $this->queries->show($id);

        return $event ? ApiResponse::ok('Audit event retrieved.', $event) : ApiResponse::error('Audit event not found.', 404);
    }

    /**
     * List changes before/after
     *
     * Siempre paginado: 15 por defecto, máximo 100. Soporta p, f, o, w y totalCount.
     *
     * @urlParam id integer required Identificador del evento. Example: 1
     *
     * @queryParam p[page] integer Página desde 1. Example: 1
     * @queryParam p[per_page] integer Tamaño entre 1 y 100. Example: 15
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Audit changes retrieved.","data":{"items":[{"id":1,"auditEventId":1,"entityType":"product_variant","entityId":"5","operation":"updated","beforeValues":{"stock":"10.000"},"afterValues":{"stock":"12.000"}}],"page":1,"perPage":15,"hasMore":false,"totalCount":1,"summary":[1]}}
     */
    public function changes(AuditQueryRequest $request, int $id): JsonResponse
    {
        if (! $this->queries->show($id)) {
            return ApiResponse::error('Audit event not found.', 404);
        }

        return ApiResponse::ok('Audit changes retrieved.', $this->queries->index($this->extractGridFilters($request), eventId: $id));
    }
}
