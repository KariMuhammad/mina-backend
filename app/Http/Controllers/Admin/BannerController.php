<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::orderBy('order')->get();

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    public function store(Request $request, ImageService $imageService): JsonResponse
    {
        $request->validate([
            'image'     => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'title'     => 'nullable|string|max:255',
            'link'      => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'order'     => 'nullable|integer',
        ]);

        $data = $request->only(['title', 'link']);
        $data['is_active'] = $request->input('is_active', true);
        $data['order'] = $request->input('order', 0);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->upload($request->file('image'), 'banners');
        }

        $banner = Banner::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully.',
            'data' => $banner,
        ], 201);
    }

    public function update(Request $request, int $id, ImageService $imageService): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'title'     => 'nullable|string|max:255',
            'link'      => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'order'     => 'nullable|integer',
        ]);

        $data = $request->only(['title', 'link']);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->input('is_active');
        }

        if ($request->has('order')) {
            $data['order'] = $request->input('order');
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->replace($banner->image_path ?? null, $request->file('image'), 'banners');
        }

        $banner->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully.',
            'data' => $banner->fresh(),
        ]);
    }

    public function destroy(int $id, ImageService $imageService): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        if ($banner->image_path) {
            $imageService->delete($banner->image_path);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully.',
        ]);
    }

    public function toggle(int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->save();

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated.',
            'data' => $banner,
        ]);
    }
}
