<?php

namespace App\Exceptions;

use DomainException;

/**
 * Regla de negocio violada en el modulo de pedidos. `status` distingue un
 * conflicto de estado (409: editar fuera de draft, cancelar un parcial) de un
 * dato invalido (422: recibir de mas, variante sin control de existencias).
 */
class OrderDomainException extends DomainException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly ?array $context = null,
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $message, ?array $context = null): self
    {
        return new self($message, 409, $context);
    }
}
