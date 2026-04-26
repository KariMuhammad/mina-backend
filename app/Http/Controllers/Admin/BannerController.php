<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function publicIndex(): JsonResponse
    {
        $banners = Cache::remember('public_banners', 300, function () {
            return Banner::where('is_active', true)
                ->orderBy('order', 'asc')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data'    => $banners,
        ]);
    }

    public function index(): JsonResponse
    {
        $banners = Banner::orderBy('order')->get();

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image'     => 'required|image|mimes:jpeg,jpg,png,webp,gif,svg|max:20480',
            'title'     => 'nullable|string|max:255',
            'link'      => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'order'     => 'nullable|integer',
        ]);

        $data = $request->only(['title', 'link']);
        $data['is_active'] = $request->input('is_active', true);
        $data['order'] = $request->input('order', 0);

        if ($request->hasFile('image')) {
            $path = Storage::disk('cloudinary')->putFile('banners', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $banner = Banner::create($data);
        Cache::forget('public_banners');

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully.',
            'data' => $banner,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'image'     => 'nullable|image|mimes:jpeg,jpg,png,webp,gif,svg|max:20480',
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
            $this->deleteImage($banner->image_path);

            $path = Storage::disk('cloudinary')->putFile('banners', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $banner->update($data);
        Cache::forget('public_banners');

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully.',
            'data' => $banner->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        if ($banner->image_path) {
            $this->deleteImage($banner->image_path);
        }

        $banner->delete();
        Cache::forget('public_banners');

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
        Cache::forget('public_banners');

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated.',
            'data' => $banner,
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
