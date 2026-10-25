<?php

namespace App\Http\Controllers\Concerns;

use App\DTOs\Product\KeyRenamePlan;
use App\Http\Resources\Product\KeyRenamePlanResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Respuesta del `PUT ... { dryRun: true }`, comun a producto, categoria y
 * grupo de tallas: el plan del renombrado en cascada, sin guardar nada.
 */
trait RespondsWithKeyRenamePreview
{
    protected function keyRenamePreviewResponse(?KeyRenamePlan $plan, string $notFoundMessage): JsonResponse
    {
        if ($plan === null) {
            return ApiResponse::error($notFoundMessage, 404);
        }

        return ApiResponse::ok('Key rename preview.', new KeyRenamePlanResource($plan));
    }
}
