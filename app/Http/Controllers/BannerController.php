<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use App\Services\ImageService;

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

    public function store(StoreBannerRequest $request, ImageService $imageService)
    {
        $data = $request->only(['title']);
        $data['is_active'] = $request->input('is_active', true);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->upload($request->file('image'), 'banners');
        }

        $banner = Banner::create($data);
        return response()->json(['message' => 'Banner created successfully.', 'banner' => $banner], 201);
    }

    public function update(UpdateBannerRequest $request, $id, ImageService $imageService)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->only(['title']);
        if ($request->has('is_active')) {
            $data['is_active'] = $request->input('is_active');
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->replace($banner->image_path ?? null, $request->file('image'), 'banners');
        }

        $banner->update($data);
        return response()->json(['message' => 'Banner updated successfully.', 'banner' => $banner]);
    }
}
