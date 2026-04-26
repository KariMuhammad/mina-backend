<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::where('is_active', true)->get()->map(function ($b) {
            return [
                'id'             => $b->id,
                'image_full_url' => $b->image_url ?? $b->image_full_url,
                'image_path'     => $b->image_path,
                'title'          => $b->title,
                'type'           => 'default',
                'link'           => null,
                'store'          => null,
                'item'           => null,
            ];
        });
        return response()->json(['campaigns' => [], 'banners' => $banners]);
    }

    public function store(StoreBannerRequest $request)
    {
        $data = $request->only(['title']);
        $data['is_active'] = $request->input('is_active', true);

        if ($request->hasFile('image')) {
            $path = Storage::disk('cloudinary')->putFile('banners', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $banner = Banner::create($data);
        return response()->json(['message' => 'Banner created successfully.', 'banner' => $banner], 201);
    }

    public function update(UpdateBannerRequest $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->only(['title']);
        if ($request->has('is_active')) {
            $data['is_active'] = $request->input('is_active');
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($banner->image_path);

            $path = Storage::disk('cloudinary')->putFile('banners', $request->file('image'));
            $data['image_path'] = Storage::disk('cloudinary')->url($path);
        }

        $banner->update($data);
        return response()->json(['message' => 'Banner updated successfully.', 'banner' => $banner]);
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
