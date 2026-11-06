<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteStoredImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public array $paths) {}

    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        foreach ($this->paths as $path) {
            if (! Storage::disk('public')->delete($path)) {
                throw new RuntimeException('No se pudo completar la limpieza de imágenes.');
            }
        }
    }
}
