<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateProductPriceRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity', 'is_popular']);
        $data['quantity'] = $request->input('quantity', 0);

        if ($request->hasFile('image')) {
            $path = Storage::disk('cloudinary')->putFile('products', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity', 'is_popular']);

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image_path);

            $path = Storage::disk('cloudinary')->putFile('products', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->image_path) {
            $this->deleteImage($product->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    public function updatePrice(UpdateProductPriceRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['price' => $request->validated('price')]);

        return response()->json([
            'message' => 'Price updated successfully.',
            'product' => $product->fresh(),
        ]);
    }

    private function deleteImage(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        // Cloudinary URL — extract public_id and delete via cloudinary disk
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $publicId = $this->extractCloudinaryPublicId($path);
            if ($publicId) {
                Storage::disk('cloudinary')->delete($publicId);
            }
            return;
        }

        // Legacy local path — delete from public disk
        Storage::disk('public')->delete($path);
    }

    private function extractCloudinaryPublicId(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        // Match /image/upload/{optional transformations}/v{version}/{public_id}.{ext}
        if (preg_match('#/image/upload/(?:[^/]+/)*v\d+/(.+)#', $path, $matches)) {
            return preg_replace('/\.[^.]+$/', '', $matches[1]);
        }

        // Fallback: match /image/upload/{public_id}.{ext} without version
        if (preg_match('#/image/upload/(.+)#', $path, $matches)) {
            return preg_replace('/\.[^.]+$/', '', $matches[1]);
        }

        return null;
    }
}
