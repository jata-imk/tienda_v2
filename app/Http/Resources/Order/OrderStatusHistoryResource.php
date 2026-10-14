<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'idOrder'        => $this->id_order,
            'previousStatus' => $this->previous_status,
            'newStatus'      => $this->new_status,
            'notes'          => $this->notes,
            'idUser'         => $this->id_user,
            'userName'       => $this->whenLoaded('user', fn() => $this->user?->user_name),
            'createdAt'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
