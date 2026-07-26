<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ProductImageService
{
    public function __construct(private ImageService $imageService) {}

    public function listImages(Product $product, Color $color): EloquentCollection
    {
        return ProductImage::where('id_product', $product->id)
            ->where('id_color', $color->id)
            ->get();
    }

    /**
     * @param UploadedFile[] $files
     */
    public function addImages(Product $product, Color $color, array $files): Collection
    {
        return collect($files)->map(function (UploadedFile $file) use ($product, $color) {
            $stored = $this->imageService->store($file, "products/{$product->id}/colors/{$color->id}");

            return ProductImage::create([
                'id_product' => $product->id,
                'id_color'   => $color->id,
                'path'       => $stored['path'],
                'path_thumb' => $stored['thumb'],
            ]);
        });
    }

    public function findImage(Product $product, Color $color, int $imageId): ?ProductImage
    {
        return ProductImage::where('id_product', $product->id)
            ->where('id_color', $color->id)
            ->find($imageId);
    }

    public function deleteImage(ProductImage $image): void
    {
        $this->imageService->delete($image->path, $image->path_thumb);
        $image->delete();
    }
}
