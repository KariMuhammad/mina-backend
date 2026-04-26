<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::with('category:id,name')->get());
    }

    public function show($id)
    {
        return response()->json(Product::findOrFail($id));
    }

    public function popular()
    {
        return response()->json(Product::where('is_popular', true)->get());
    }

    public function search(Request $request)
    {
        $query = $request->query('q');

        $products = Product::where('name', 'LIKE', "%{$query}%")
                           ->orWhere('description', 'LIKE', "%{$query}%")
                           ->get();

        return response()->json($products);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity']);
        $data['quantity'] = $request->input('quantity', 0);

        if ($request->hasFile('image')) {
            $path = Storage::disk('cloudinary')->putFile('products', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $product = Product::create($data);
        return response()->json(['message' => 'Product created successfully.', 'product' => $product], 201);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity']);

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image_path);

            $path = Storage::disk('cloudinary')->putFile('products', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $product->update($data);
        return response()->json(['message' => 'Product updated successfully.', 'product' => $product]);
    }

    private function deleteImage(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $publicId = $this->extractCloudinaryPublicId($path);
            if ($publicId) {
                Storage::disk('cloudinary')->delete($publicId);
            }
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function extractCloudinaryPublicId(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        if (preg_match('#/image/upload/(?:[^/]+/)*v\d+/(.+)#', $path, $matches)) {
            return preg_replace('/\.[^.]+$/', '', $matches[1]);
        }

        if (preg_match('#/image/upload/(.+)#', $path, $matches)) {
            return preg_replace('/\.[^.]+$/', '', $matches[1]);
        }

        return null;
    }
}
