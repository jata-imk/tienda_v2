<?php

namespace App\Exceptions;

use DomainException;

/**
 * Excepcion de negocio del modulo de inventario que además transporta
 * contexto estructurado para la respuesta de error (ej. currentStock,
 * requestedQuantity), tal como pide el contrato del endpoint de movimientos.
 */
class InventoryDomainException extends DomainException
{
    public function __construct(string $message, public readonly ?array $context = null)
    {
        parent::__construct($message);
    }
}
