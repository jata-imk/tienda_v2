<?php

namespace App\Enums;

/**
 * Estatus de un pedido de compra. Las transiciones permitidas viven en
 * OrderService, no aqui.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
