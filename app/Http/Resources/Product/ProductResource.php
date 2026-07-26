<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'categories'   => $this->whenLoaded(
                'categories',
                fn() => $this->categories
                    ->map(fn($category) => ['id' => $category->id, 'desc' => $category->name])
                    ->values(),
            ),
            'idSizeGroup'  => $this->id_size_group,
            'sizeGroup'    => $this->sizeGroup?->name,
            'key'          => $this->key,
            'name'         => $this->name,
            'description'  => $this->description,
            'codeBar'      => $this->code_bar,
            'image'        => $this->image ? Storage::disk('public')->url($this->image) : null,
            'imageThumb'   => $this->image_thumb ? Storage::disk('public')->url($this->image_thumb) : null,
            'price'        => (float) $this->price,
            'cost'         => (float) $this->cost,
            'stockControl' => $this->stock_control,
            'totalStock'   => $this->whenLoaded('variants', fn() => $this->total_stock),
            'discount'     => (float) $this->discount,
            'typeIVA'      => $this->type_iva,
            'rateIVA'      => $this->rate_iva !== null ? (float) $this->rate_iva : null,
            'quotaIVA'     => $this->quota_iva !== null ? (float) $this->quota_iva : null,
            'isr'          => (float) $this->isr,
            'impEsp'       => (float) $this->imp_esp,
            'status'       => $this->status,
            'variants'     => ProductVariantResource::collection($this->whenLoaded('variants')),
            'colorImages'  => ProductImageResource::collection($this->whenLoaded('colorImages')),
            'createdAt'    => $this->created_at?->toDateTimeString(),
            'updatedAt'    => $this->updated_at?->toDateTimeString(),
        ];
    }
}
