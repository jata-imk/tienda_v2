<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

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
        // Paths ya escritos en disco en esta llamada, para poder limpiarlos si algo
        // falla a mitad (la transaccion DB no revierte los ficheros del storage).
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($product, $color, $files, &$storedPaths) {
                return collect($files)->map(function (UploadedFile $file) use ($product, $color, &$storedPaths) {
                    $stored = $this->imageService->store($file, "products/{$product->id}/colors/{$color->id}");

                    $storedPaths[] = $stored['path'];
                    $storedPaths[] = $stored['thumb'];

                    return ProductImage::create([
                        'id_product' => $product->id,
                        'id_color'   => $color->id,
                        'path'       => $stored['path'],
                        'path_thumb' => $stored['thumb'],
                    ]);
                });
            });
        } catch (Throwable $e) {
            $this->imageService->delete(...$storedPaths);

            throw $e;
        }
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
