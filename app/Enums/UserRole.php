<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Seller = 'seller';
    case Warehouse = 'warehouse';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
