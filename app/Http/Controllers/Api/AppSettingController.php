<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class AppSettingController extends Controller
{
    /**
     * GET /api/app-settings — all settings as key-value object.
     */
    public function index(): JsonResponse
    {
        $settings = AppSetting::query()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * GET /api/app-settings/{key} — single setting by key.
     */
    public function show(string $key): JsonResponse
    {
        $setting = AppSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'key'   => $setting->key,
                'value' => $setting->value,
                'label' => $setting->label,
            ],
        ]);
    }

    /**
     * GET /api/pricing — Public pricing settings (mobile app uses this).
     */
    public function pricing(): JsonResponse
    {
        $deliveryPrice    = AppSetting::where('key', 'delivery_price')->first();
        $currencySymbol   = AppSetting::where('key', 'currency_symbol')->first();
        $currencyCode     = AppSetting::where('key', 'currency_code')->first();
        $currencyPosition = AppSetting::where('key', 'currency_position')->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'delivery_price'    => (float) ($deliveryPrice->value ?? 0),
                'currency_symbol'   => $currencySymbol->value   ?? 'ل.س',
                'currency_code'     => $currencyCode->value     ?? 'SYP',
                'currency_position' => $currencyPosition->value ?? 'after',
            ],
        ]);
    }

    /**
     * POST /api/admin/pricing — Admin update pricing settings.
     */
    public function updatePricing(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'delivery_price'    => 'required|numeric|min:0',
            'currency_symbol'   => 'required|string|max:10',
            'currency_code'     => 'required|string|max:10',
            'currency_position' => 'required|in:before,after',
        ]);

        $settings = [
            'delivery_price'    => $request->delivery_price,
            'currency_symbol'   => $request->currency_symbol,
            'currency_code'     => $request->currency_code,
            'currency_position' => $request->currency_position,
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pricing settings updated successfully.',
            'data'    => $settings,
        ]);
    }
}
