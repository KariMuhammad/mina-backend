<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request, ImageService $imageService): JsonResponse
    {
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->upload($request->file('image'), 'categories');
        }

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category,
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, int $id, ImageService $imageService): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->replace($category->image_path ?? null, $request->file('image'), 'categories');
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(int $id, ImageService $imageService): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->image_path) {
            $imageService->delete($category->image_path);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
