<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    public function getZoneId(): JsonResponse
    {
        return response()->json(['zone_id' => '[1]']);
    }
}
