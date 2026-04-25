<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = DeliveryZone::orderBy('name')->get();

        return response()->json($zones);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|unique:delivery_zones,name',
            'english_name' => 'nullable|string|max:100',
            'is_active'    => 'boolean',
        ]);

        $zone = DeliveryZone::create($validated);

        return response()->json($zone, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:100|unique:delivery_zones,name,' . $id,
            'english_name' => 'nullable|string|max:100',
            'is_active'    => 'sometimes|boolean',
        ]);

        $zone->update($validated);

        return response()->json($zone);
    }

    public function destroy(int $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);
        $zone->delete();

        return response()->json(['message' => 'Zone deleted successfully.']);
    }
}
