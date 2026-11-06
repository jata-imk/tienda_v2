<?php

namespace App\Support;

use App\Services\AuditTrail;
use Closure;

final class Audit
{
    public static function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return app(AuditTrail::class)->transaction($callback, $attempts);
    }
}
