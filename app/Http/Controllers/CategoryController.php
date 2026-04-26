<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $path = Storage::disk('cloudinary')->putFile('categories', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $category = Category::create($data);
        return response()->json(['message' => 'Category created successfully.', 'category' => $category], 201);
    }

    public function update(UpdateCategoryRequest $request, $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $this->deleteImage($category->image_path);

            $path = Storage::disk('cloudinary')->putFile('categories', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $category->update($data);
        return response()->json(['message' => 'Category updated successfully.', 'category' => $category]);
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
