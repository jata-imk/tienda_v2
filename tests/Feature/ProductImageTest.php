<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Storage::fake('public');
    }

    public function test_upload_stores_image_and_thumbnail(): void
    {
        $response = $this->withoutMiddleware()->post('/api/products/1/image', [
            'image' => UploadedFile::fake()->image('camisa.png', 800, 600),
        ]);

        $response->assertOk();

        $product = Product::find(1);

        $this->assertNotNull($product->image);
        $this->assertNotNull($product->image_thumb);

        Storage::disk('public')->assertExists($product->image);
        Storage::disk('public')->assertExists($product->image_thumb);

        [$width] = getimagesizefromstring(Storage::disk('public')->get($product->image_thumb));

        $this->assertSame(200, $width);
        $this->assertStringContainsString('/storage/', $response->json('data.image'));
    }

    public function test_upload_replaces_the_previous_image(): void
    {
        $this->withoutMiddleware()->post('/api/products/1/image', [
            'image' => UploadedFile::fake()->image('uno.png', 400, 400),
        ])->assertOk();

        $first = Product::find(1)->only(['image', 'image_thumb']);

        $this->withoutMiddleware()->post('/api/products/1/image', [
            'image' => UploadedFile::fake()->image('dos.png', 400, 400),
        ])->assertOk();

        Storage::disk('public')->assertMissing($first['image']);
        Storage::disk('public')->assertMissing($first['image_thumb']);
        Storage::disk('public')->assertExists(Product::find(1)->image);
    }

    public function test_delete_clears_image_columns_and_files(): void
    {
        $this->withoutMiddleware()->post('/api/products/1/image', [
            'image' => UploadedFile::fake()->image('uno.png', 400, 400),
        ])->assertOk();

        $stored = Product::find(1)->only(['image', 'image_thumb']);

        $this->withoutMiddleware()
            ->deleteJson('/api/products/1/image')
            ->assertOk()
            ->assertJsonPath('data.image', null)
            ->assertJsonPath('data.imageThumb', null);

        Storage::disk('public')->assertMissing($stored['image']);
        Storage::disk('public')->assertMissing($stored['image_thumb']);
    }

    public function test_upload_rejects_non_images(): void
    {
        $this->withoutMiddleware()
            ->post(
                '/api/products/1/image',
                ['image' => UploadedFile::fake()->create('nota.pdf', 10)],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_returns_404_for_a_missing_product(): void
    {
        $this->withoutMiddleware()
            ->post('/api/products/9999/image', ['image' => UploadedFile::fake()->image('x.png')])
            ->assertNotFound();
    }
}
