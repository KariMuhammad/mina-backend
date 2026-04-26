<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $path = Storage::disk('cloudinary')->putFile('categories', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category,
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $this->deleteImage($category->image_path);

            $path = Storage::disk('cloudinary')->putFile('categories', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->image_path) {
            $this->deleteImage($category->image_path);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
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
