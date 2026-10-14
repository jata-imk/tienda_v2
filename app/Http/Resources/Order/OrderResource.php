<?php

namespace App\Http\Resources\Order;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'key'             => $this->key,
            'folio'           => $this->folio,
            'folioLabel'      => $this->folio_label,
            'status'          => $this->status?->value,
            'idCurrency'      => $this->id_currency,
            'currencyCode'    => $this->whenLoaded('currency', fn() => $this->currency?->code),
            // Tasa efectiva: la congelada si el pedido ya se confirmo; en
            // draft, la vigente de `currencies` (o la capturada).
            'exchangeRate'    => $this->whenLoaded('currency', fn() => app(OrderService::class)->effectiveExchangeRate($this->resource)),
            'totalItems'      => (float) $this->total_items,
            'totalAmount'     => (float) $this->total_amount,
            'supplierName'    => $this->supplier_name,
            'supplierDetails' => $this->supplier_details,
            'shippingAddress' => $this->shipping_address,
            'notes'           => $this->notes,
            'dateOrder'       => $this->date_order?->toDateString(),
            'dateExpected'    => $this->date_expected?->toDateString(),
            'dateReceived'    => $this->date_received?->toDateTimeString(),
            'idUser'          => $this->id_user,
            'userName'        => $this->whenLoaded('user', fn() => $this->user?->user_name),
            'createdAt'       => $this->created_at?->toDateTimeString(),
            'updatedAt'       => $this->updated_at?->toDateTimeString(),
            'details'         => OrderDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
