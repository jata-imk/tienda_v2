<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryDomainException;
use App\Exceptions\OrderDomainException;
use App\Http\Controllers\Concerns\TranslatesGridFilters;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\OrderTransitionRequest;
use App\Http\Requests\Order\ReceiveOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\InventoryMovement\InventoryMovementResource;
use App\Http\Resources\Order\OrderResource;
use App\Http\Resources\Order\OrderStatusHistoryResource;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Closure;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Orders
 *
 * Pedidos de compra a proveedor. Estatus: `draft` → `confirmed` →
 * `partially_received` → `received`; `draft` y `confirmed` (sin recepciones)
 * pueden pasar a `cancelled`. Recibir mercancia genera movimientos de
 * inventario tipo `purchase`.
 *
 * @authenticated
 */
class OrderController extends Controller
{
    use TranslatesGridFilters;

    public function __construct(
        private OrderService $orderService,
        private OrderPdfService $orderPdfService,
    ) {}

    /**
     * Query purchase orders (POST)
     *
     * Same payload formats as `POST /products/query`. Campos virtuales en `w`:
     * `search` (folio `PED-12` o `12`, notas, proveedor, usuario), `id_product`
     * y `sku` (pedidos que incluyen ese producto o variante).
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Orders retrieved.","data":{"items":[],"totalCount":0,"summary":[0]}}
     */
    public function query(Request $request): JsonResponse
    {
        $filters = $this->extractGridFilters($request);

        return $this->gridResponse('Orders retrieved.', $this->orderService->index($filters), OrderResource::class);
    }

    /**
     * Show a purchase order
     *
     * Incluye las lineas con los datos de su variante.
     *
     * @urlParam order integer required Order ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Order retrieved.","data":{"id":1,"key":"PED","folio":1,"folioLabel":"PED-1","status":"draft","idCurrency":1,"currencyCode":"MXN","exchangeRate":1,"totalItems":10,"totalAmount":6000,"supplierName":"Telas del Sureste","supplierDetails":null,"shippingAddress":null,"notes":null,"dateOrder":"2026-10-15","dateExpected":"2026-10-30","dateReceived":null,"idUser":1,"userName":"admin","createdAt":"2026-10-15 10:00:00","updatedAt":null,"details":[{"id":1,"idOrder":1,"idProductVariant":1,"quantityOrdered":10,"quantityReceived":0,"quantityPending":10,"unitCost":600,"subtotal":6000,"notes":null,"idProduct":1,"productKey":"CAM-001","productName":"Camisa lino caballero","sku":"CAM-001-34-BLA","size":"34","color":"Blanco"}]}}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Order not found.","data":null}
     */
    public function show(int $order): JsonResponse
    {
        $found = $this->orderService->show($order);

        if (!$found) {
            return $this->notFound();
        }

        return ApiResponse::ok('Order retrieved.', new OrderResource($found));
    }

    /**
     * Create a purchase order
     *
     * Crea el pedido en `draft` con folio consecutivo `PED-n`. Sin `idCurrency`
     * toma la moneda base de la empresa. Sin `unitCost` la linea toma
     * `products.cost` (convertido a la moneda del pedido). Las variantes deben
     * estar activas y su producto debe manejar existencias.
     *
     * @bodyParam idCurrency integer Moneda del pedido. Default: moneda base. Example: 1
     * @bodyParam exchangeRate number Tipo de cambio; se congela al confirmar. Default: el de la moneda. Example: null
     * @bodyParam dateOrder date Fecha del pedido. Default: hoy. Example: 2026-10-15
     * @bodyParam dateExpected date Fecha de entrega esperada (≥ dateOrder). Example: 2026-10-30
     * @bodyParam supplierName string Proveedor (texto libre). Example: Telas del Sureste
     * @bodyParam supplierDetails string Direccion, RFC o contacto del proveedor. Example: null
     * @bodyParam shippingAddress string Direccion de envio. Default en PDF: la de la empresa. Example: null
     * @bodyParam notes string Comentarios. Example: null
     * @bodyParam details array required Lineas del pedido (1 a 200).
     * @bodyParam details[].idProductVariant integer required Variante; no se repite en el pedido. Example: 1
     * @bodyParam details[].quantityOrdered number required Cantidad pedida. Example: 10
     * @bodyParam details[].unitCost number Costo unitario en la moneda del pedido. Example: 600
     * @bodyParam details[].notes string Comentario de la linea. Example: null
     *
     * @response 201 {"ok":true,"code":201,"status":"Created","message":"Order created.","data":{"id":1,"folioLabel":"PED-1","status":"draft"}}
     * @response 422 {"ok":false,"code":422,"status":"Unprocessable Entity","message":"El producto de la variante SERV-001-U no maneja existencias.","data":{"idProductVariant":9,"sku":"SERV-001-U"}}
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        return $this->handle(fn() => ApiResponse::created(
            'Order created.',
            new OrderResource($this->orderService->store($request->toDTO(), $request->user()->id)),
        ));
    }

    /**
     * Update a draft purchase order
     *
     * Solo en `draft` (409 en cualquier otro estatus). Los campos omitidos no se
     * tocan. Si se envia `details`, reemplaza todas las lineas (se identifican
     * por `idProductVariant`). Mismos campos que el alta.
     *
     * @urlParam order integer required Order ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Order updated.","data":{"id":1,"status":"draft"}}
     * @response 409 {"ok":false,"code":409,"status":"Conflict","message":"Solo se puede editar un pedido en borrador.","data":{"status":"confirmed"}}
     */
    public function update(UpdateOrderRequest $request, int $order): JsonResponse
    {
        return $this->handle(function () use ($request, $order) {
            $updated = $this->orderService->update($order, $request->toDTO());

            return $updated ? ApiResponse::ok('Order updated.', new OrderResource($updated)) : $this->notFound();
        });
    }

    /**
     * Confirm a purchase order
     *
     * `draft` → `confirmed`. Congela el tipo de cambio. No afecta existencias.
     *
     * @urlParam order integer required Order ID. Example: 1
     * @bodyParam notes string Comentario para el historial. Example: Enviado al proveedor
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Order confirmed.","data":{"id":1,"status":"confirmed"}}
     * @response 409 {"ok":false,"code":409,"status":"Conflict","message":"Solo se puede confirmar un pedido en borrador.","data":{"status":"confirmed"}}
     */
    public function confirm(OrderTransitionRequest $request, int $order): JsonResponse
    {
        return $this->handle(function () use ($request, $order) {
            $confirmed = $this->orderService->confirm($order, $request->toDTO(), $request->user()->id);

            return $confirmed ? ApiResponse::ok('Order confirmed.', new OrderResource($confirmed)) : $this->notFound();
        });
    }

    /**
     * Cancel a purchase order
     *
     * Solo desde `draft` o `confirmed`. Un pedido con recepciones
     * (`partially_received`) nunca se cancela.
     *
     * @urlParam order integer required Order ID. Example: 1
     * @bodyParam notes string Motivo de la cancelacion. Example: Proveedor sin existencia
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Order cancelled.","data":{"id":1,"status":"cancelled"}}
     * @response 409 {"ok":false,"code":409,"status":"Conflict","message":"Un pedido con recepciones parciales no se puede cancelar.","data":null}
     */
    public function cancel(OrderTransitionRequest $request, int $order): JsonResponse
    {
        return $this->handle(function () use ($request, $order) {
            $cancelled = $this->orderService->cancel($order, $request->toDTO(), $request->user()->id);

            return $cancelled ? ApiResponse::ok('Order cancelled.', new OrderResource($cancelled)) : $this->notFound();
        });
    }

    /**
     * Receive goods for a purchase order
     *
     * Solo en `confirmed` o `partially_received`. Suma `quantity` a cada linea y
     * al stock de su variante con un movimiento `purchase`
     * (`referenceType = order_detail`, `referenceId` = id de la linea). Si todas
     * las lineas quedan completas el pedido pasa a `received`; si no, a
     * `partially_received`. Atomico: si una linea falla no se aplica nada.
     *
     * @urlParam order integer required Order ID. Example: 1
     * @bodyParam notes string Comentario de la recepcion. Example: Remesa 1
     * @bodyParam lines array required Lineas recibidas (1 a 200).
     * @bodyParam lines[].detailId integer required Id de la linea del pedido. Example: 1
     * @bodyParam lines[].quantity number required Cantidad recibida (≤ pendiente). Example: 4
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Order received.","data":{"order":{"id":1,"status":"partially_received"},"movements":[{"id":10,"idProductVariant":1,"movementType":"purchase","quantity":4,"previousStock":3,"newStock":7,"referenceType":"order_detail","referenceId":1}]}}
     * @response 409 {"ok":false,"code":409,"status":"Conflict","message":"Solo se puede recibir un pedido confirmado o parcialmente recibido.","data":null}
     * @response 422 {"ok":false,"code":422,"status":"Unprocessable Entity","message":"La cantidad recibida excede lo pendiente de la linea.","data":{"detailId":1,"quantityOrdered":10,"quantityReceived":7,"requestedQuantity":5}}
     */
    public function receive(ReceiveOrderRequest $request, int $order): JsonResponse
    {
        return $this->handle(function () use ($request, $order) {
            $result = $this->orderService->receive($order, $request->toDTO(), $request->user()->id);

            if (!$result) {
                return $this->notFound();
            }

            return ApiResponse::ok('Order received.', [
                'order'     => new OrderResource($result['order']),
                'movements' => InventoryMovementResource::collection(collect($result['movements'])),
            ]);
        });
    }

    /**
     * Status history of a purchase order
     *
     * @urlParam order integer required Order ID. Example: 1
     *
     * @response 200 {"ok":true,"code":200,"status":"OK","message":"Order history retrieved.","data":[{"id":1,"idOrder":1,"previousStatus":null,"newStatus":"draft","notes":null,"idUser":1,"userName":"admin","createdAt":"2026-10-15 10:00:00"}]}
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Order not found.","data":null}
     */
    public function history(int $order): JsonResponse
    {
        $history = $this->orderService->history($order);

        if ($history === null) {
            return $this->notFound();
        }

        return ApiResponse::ok('Order history retrieved.', OrderStatusHistoryResource::collection($history));
    }

    /**
     * Purchase order PDF
     *
     * Devuelve el PDF (`application/pdf`), no JSON. Por defecto inline; con
     * `?download=1` como descarga `PED-n.pdf`. Disponible en cualquier
     * estatus; en `draft` y `cancelled` lleva marca de agua.
     *
     * @urlParam order integer required Order ID. Example: 1
     * @queryParam download boolean Descargar en lugar de mostrar. Example: 1
     *
     * @response 404 {"ok":false,"code":404,"status":"Not Found","message":"Order not found.","data":null}
     */
    public function pdf(Request $request, int $order): Response
    {
        $found = $this->orderService->show($order);

        if (!$found) {
            return $this->notFound();
        }

        $pdf      = $this->orderPdfService->render($found);
        $fileName = "{$found->folio_label}.pdf";

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    /**
     * Traduce las reglas de negocio violadas a la respuesta de error estandar.
     */
    private function handle(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (OrderDomainException $e) {
            return ApiResponse::error($e->getMessage(), $e->status, $e->context);
        } catch (InventoryDomainException $e) {
            return ApiResponse::error($e->getMessage(), 422, $e->context);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    private function notFound(): JsonResponse
    {
        return ApiResponse::error('Order not found.', 404);
    }
}
