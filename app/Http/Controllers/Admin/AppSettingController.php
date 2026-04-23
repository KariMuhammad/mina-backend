<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    /**
     * GET /api/admin/app-settings — all settings with full data.
     */
    public function index(): JsonResponse
    {
        $settings = AppSetting::query()->orderBy('id')->get();

        return response()->json($settings);
    }

    /**
     * POST /api/admin/app-settings/bulk — update multiple settings at once.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'settings'             => 'required|array|min:1',
            'settings.*.key'       => 'required|string|exists:app_settings,key',
            'settings.*.value'     => 'nullable|string',
        ]);

        $updated = [];

        foreach ($request->input('settings') as $item) {
            $setting = AppSetting::query()->where('key', $item['key'])->first();
            if ($setting) {
                $setting->update(['value' => $item['value'] ?? null]);
                $updated[] = $setting;
            }
        }

        return response()->json([
            'message'  => 'Settings updated successfully.',
            'settings' => $updated,
        ]);
    }

    /**
     * PUT /api/admin/app-settings/{key} — update single setting by key.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $setting = AppSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        $request->validate([
            'value' => 'nullable|string',
        ]);

        $setting->update(['value' => $request->input('value')]);

        return response()->json([
            'message' => 'Setting updated successfully.',
            'setting' => $setting,
        ]);
    }
}
