<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function getBanners(): JsonResponse
    {
        $bannersArr = Banner::where('is_active', true)->get()->map(function (Banner $b) {
            return [
                'id' => $b->id,
                'image_full_url' => $b->image_full_url,
                'title' => $b->title,
                'type' => 'default',
                'link' => null,
                'store' => null,
                'item' => null,
            ];
        })->values()->all();

        return response()->json(['campaigns' => [], 'banners' => $bannersArr]);
    }
}
