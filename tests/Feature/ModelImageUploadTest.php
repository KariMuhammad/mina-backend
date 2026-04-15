<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Category;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModelImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_category_image()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('category.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/categories', [
            'name' => 'New Category',
            'image' => $file,
        ]);

        $response->assertStatus(201);
        $category = Category::first();
        $this->assertNotNull($category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
    }

    public function test_category_upload_fails_on_oversized_image()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('giant.jpg', 3000, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/categories', [
            'name' => 'Category Two',
            'image' => $file,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image']);
    }

    public function test_can_replace_category_image_and_delete_old()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        
        $firstFile = UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg');
        $this->actingAs($user)->postJson('/api/categories', ['name' => 'Cat1', 'image' => $firstFile]);
        
        $category = Category::first();
        $oldPath = $category->image_path;
        Storage::disk('public')->assertExists($oldPath);

        $secondFile = UploadedFile::fake()->create('second.png', 100, 'image/png');
        $response = $this->actingAs($user)->postJson("/api/categories/{$category->id}", [
            'name' => 'Cat1 Updated',
            'image' => $secondFile,
        ]);
        
        $category->refresh();
        $newPath = $category->image_path;

        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_can_upload_product_image()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Test Cat']);
        
        $file = UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/products', [
            'name' => 'Test Prod',
            'price' => 99.99,
            'category_id' => $category->id,
            'image' => $file,
        ]);

        $response->assertStatus(201);
        $product = Product::first();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_can_upload_banner_image()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        
        $file = UploadedFile::fake()->create('banner.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/banners', [
            'title' => 'Sale!',
            'is_active' => true,
            'image' => $file,
        ]);

        $response->assertStatus(201);
        $banner = Banner::first();
        $this->assertNotNull($banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);
    }
}
