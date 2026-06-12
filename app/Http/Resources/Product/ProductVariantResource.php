<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'idProduct' => $this->id_product,
            'idSize'    => $this->id_size,
            'size'      => $this->size?->name,
            'idColor'   => $this->id_color,
            'color'     => $this->color?->name,
            'hexColor'  => $this->color?->hex_color,
            'sku'       => $this->sku,
            'codeBar'   => $this->code_bar,
            'stock'     => (float) $this->stock,
            'status'    => $this->status,
        ];
    }
}
