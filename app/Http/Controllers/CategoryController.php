<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Services\ImageService;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
    }

    public function store(StoreCategoryRequest $request, ImageService $imageService)
    {
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->upload($request->file('image'), 'categories');
        }

        $category = Category::create($data);
        return response()->json(['message' => 'Category created successfully.', 'category' => $category], 201);
    }

    public function update(UpdateCategoryRequest $request, $id, ImageService $imageService)
    {
        $category = Category::findOrFail($id);
        $data = $request->only(['name']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->replace($category->image_path ?? null, $request->file('image'), 'categories');
        }

        $category->update($data);
        return response()->json(['message' => 'Category updated successfully.', 'category' => $category]);
    }
}
