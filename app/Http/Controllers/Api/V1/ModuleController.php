<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function getModules(): JsonResponse
    {
        return response()->json([
            [
                'id' => 1,
                'module_name' => 'Food',
                'module_type' => 'food',
                'slug' => 'food',
                'theme_id' => 1,
                'description' => 'Food delivery service',
                'stores_count' => 10,
                'icon_full_url' => 'https://placehold.co/100x100?text=Food',
                'thumbnail_full_url' => 'https://placehold.co/300x150?text=Food+Thumb',
                'created_at' => date('Y-m-d\TH:i:s.000000\Z'),
                'updated_at' => date('Y-m-d\TH:i:s.000000\Z'),
            ],
        ]);
    }
}
