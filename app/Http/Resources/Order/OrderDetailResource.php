<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ordered  = (float) $this->quantity_ordered;
        $received = (float) $this->quantity_received;

        return [
            'id'               => $this->id,
            'idOrder'          => $this->id_order,
            'idProductVariant' => $this->id_product_variant,
            'quantityOrdered'  => $ordered,
            'quantityReceived' => $received,
            'quantityPending'  => round($ordered - $received, 3),
            'unitCost'         => (float) $this->unit_cost,
            'subtotal'         => (float) $this->subtotal,
            'notes'            => $this->notes,
            'idProduct'        => $this->whenLoaded('variant', fn() => $this->variant?->id_product),
            'productKey'       => $this->whenLoaded('variant', fn() => $this->variant?->product?->key),
            'productName'      => $this->whenLoaded('variant', fn() => $this->variant?->product?->name),
            'sku'              => $this->whenLoaded('variant', fn() => $this->variant?->sku),
            'size'             => $this->whenLoaded('variant', fn() => $this->variant?->size?->name),
            'color'            => $this->whenLoaded('variant', fn() => $this->variant?->color?->name),
        ];
    }
}
