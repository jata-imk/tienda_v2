<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'idProduct'  => $this->id_product,
            'idColor'    => $this->id_color,
            'image'      => Storage::disk('public')->url($this->path),
            'imageThumb' => Storage::disk('public')->url($this->path_thumb),
            'createdAt'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
