<?php

namespace App\Exceptions;

use App\DTOs\Product\KeyRenamePlan;
use DomainException;

/**
 * El renombrado en cascada dejaria claves o SKU repetidos. Se lanza dentro de
 * la transaccion del `PUT`, asi que no se aplica nada; `bootstrap/app.php` la
 * responde como 422 con la lista de choques.
 */
class KeyRenameConflictException extends DomainException
{
    public function __construct(public readonly KeyRenamePlan $plan)
    {
        parent::__construct('El renombrado en cascada choca con claves o SKU existentes; no se aplico ningun cambio.');
    }
}
