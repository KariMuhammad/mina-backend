<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\ImageService;

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

    public function store(StoreProductRequest $request, ImageService $imageService)
    {
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity']);
        $data['quantity'] = $request->input('quantity', 0);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->upload($request->file('image'), 'products');
        }

        $product = Product::create($data);
        return response()->json(['message' => 'Product created successfully.', 'product' => $product], 201);
    }

    public function update(UpdateProductRequest $request, $id, ImageService $imageService)
    {
        $product = Product::findOrFail($id);
        $data = $request->only(['name', 'price', 'description', 'category_id', 'quantity']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->replace($product->image_path ?? null, $request->file('image'), 'products');
        }

        $product->update($data);
        return response()->json(['message' => 'Product updated successfully.', 'product' => $product]);
    }
}
