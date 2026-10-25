<?php

namespace App\Http\Resources\Product;

use App\DTOs\Product\KeyRenamePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property KeyRenamePlan $resource
 */
class KeyRenamePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'totals'    => [
                'products'  => count($this->resource->products),
                'variants'  => count($this->resource->variants),
                'conflicts' => count($this->resource->conflicts),
            ],
            'products'  => $this->resource->products,
            'variants'  => $this->resource->variants,
            'conflicts' => $this->resource->conflicts,
        ];
    }
}
