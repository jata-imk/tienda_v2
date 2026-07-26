<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductColorImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Storage::fake('public');
    }

    public function test_store_uploads_multiple_images_in_one_request(): void
    {
        $response = $this->withoutMiddleware()->post('/api/products/1/colors/1/images', [
            'images' => [
                UploadedFile::fake()->image('rojo-1.png', 400, 400),
                UploadedFile::fake()->image('rojo-2.png', 400, 400),
            ],
        ]);

        $response->assertCreated();

        $images = ProductImage::where('id_product', 1)->where('id_color', 1)->get();

        $this->assertCount(2, $images);

        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->path);
            Storage::disk('public')->assertExists($image->path_thumb);
        }
    }

    public function test_store_appends_to_existing_images_instead_of_replacing(): void
    {
        $this->withoutMiddleware()->post('/api/products/1/colors/1/images', [
            'images' => [UploadedFile::fake()->image('uno.png', 300, 300)],
        ])->assertCreated();

        $this->withoutMiddleware()->post('/api/products/1/colors/1/images', [
            'images' => [UploadedFile::fake()->image('dos.png', 300, 300)],
        ])->assertCreated();

        $this->assertCount(2, ProductImage::where('id_product', 1)->where('id_color', 1)->get());
    }

    public function test_index_only_returns_images_for_the_requested_color(): void
    {
        $this->withoutMiddleware()->post('/api/products/1/colors/1/images', [
            'images' => [UploadedFile::fake()->image('rojo.png', 300, 300)],
        ])->assertCreated();

        $this->withoutMiddleware()->post('/api/products/1/colors/2/images', [
            'images' => [UploadedFile::fake()->image('azul.png', 300, 300)],
        ])->assertCreated();

        $response = $this->withoutMiddleware()->get('/api/products/1/colors/1/images');

        $response->assertOk();
        $response->assertJsonPath('data.totalCount', 1);
    }

    public function test_destroy_removes_only_the_targeted_image(): void
    {
        $this->withoutMiddleware()->post('/api/products/1/colors/1/images', [
            'images' => [
                UploadedFile::fake()->image('uno.png', 300, 300),
                UploadedFile::fake()->image('dos.png', 300, 300),
            ],
        ])->assertCreated();

        $images = ProductImage::where('id_product', 1)->where('id_color', 1)->get();
        $toDelete = $images->first();
        $toKeep   = $images->last();

        $this->withoutMiddleware()
            ->deleteJson("/api/products/1/colors/1/images/{$toDelete->id}")
            ->assertOk();

        Storage::disk('public')->assertMissing($toDelete->path);
        Storage::disk('public')->assertMissing($toDelete->path_thumb);
        Storage::disk('public')->assertExists($toKeep->path);
        $this->assertCount(1, ProductImage::where('id_product', 1)->where('id_color', 1)->get());
    }

    public function test_store_rejects_non_images(): void
    {
        $this->withoutMiddleware()
            ->post(
                '/api/products/1/colors/1/images',
                ['images' => [UploadedFile::fake()->create('nota.pdf', 10)]],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0']);
    }

    public function test_store_returns_404_for_a_missing_product(): void
    {
        $this->withoutMiddleware()
            ->post('/api/products/9999/colors/1/images', [
                'images' => [UploadedFile::fake()->image('x.png')],
            ])
            ->assertNotFound();
    }

    public function test_store_returns_404_for_a_missing_color(): void
    {
        $this->withoutMiddleware()
            ->post('/api/products/1/colors/9999/images', [
                'images' => [UploadedFile::fake()->image('x.png')],
            ])
            ->assertNotFound();
    }
}
