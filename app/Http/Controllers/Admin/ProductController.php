<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateProductPriceRequest;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function store(StoreProductRequest $request, ImageService $imageService): JsonResponse
    {
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity']);
        $data['quantity'] = $request->input('quantity', 0);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->upload($request->file('image'), 'products');
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, int $id, ImageService $imageService): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->replace($product->image_path ?? null, $request->file('image'), 'products');
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh(),
        ]);
    }

    public function destroy(int $id, ImageService $imageService): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->image_path) {
            $imageService->delete($product->image_path);
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
}
