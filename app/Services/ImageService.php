<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda imagenes en el disco `public` y genera un thumbnail con GD.
 * Devuelve paths relativos: la URL publica se arma en el Resource.
 */
class ImageService
{
    private const THUMB_MAX_SIDE = 200;

    /**
     * @return array{path: string, thumb: string}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $name      = Str::uuid() . '.' . $extension;

        $disk = Storage::disk('public');
        $path = $file->storeAs($directory, $name, ['disk' => 'public']);

        if ($path === false) {
            throw new RuntimeException('No se pudo guardar la imagen.');
        }

        $thumbPath = $directory . '/thumbs/' . $name;
        $disk->put($thumbPath, $this->makeThumb($disk->get($path), $extension));

        return ['path' => $path, 'thumb' => $thumbPath];
    }

    public function delete(?string ...$paths): void
    {
        $disk = Storage::disk('public');

        foreach (array_filter($paths) as $path) {
            $disk->delete($path);
        }
    }

    /**
     * Escala la imagen para que su lado mayor no pase de THUMB_MAX_SIDE.
     * Si la imagen ya es mas chica, se reencodea igual para normalizarla.
     */
    private function makeThumb(string $contents, string $extension): string
    {
        $source = imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('El archivo no es una imagen valida.');
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        $ratio  = min(self::THUMB_MAX_SIDE / max($width, $height), 1);

        $thumb = imagescale($source, (int) round($width * $ratio), (int) round($height * $ratio));
        imagedestroy($source);

        if ($thumb === false) {
            throw new RuntimeException('No se pudo generar el thumbnail.');
        }

        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        ob_start();

        match ($extension) {
            'png'  => imagepng($thumb, null, 8),
            'webp' => imagewebp($thumb, null, 80),
            default => imagejpeg($thumb, null, 80),
        };

        $output = (string) ob_get_clean();
        imagedestroy($thumb);

        return $output;
    }
}
