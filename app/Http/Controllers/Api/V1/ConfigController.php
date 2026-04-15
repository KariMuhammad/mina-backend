<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'business_name' => 'Demo E-commerce',
            'logo_full_url' => 'https://placehold.co/100x100?text=Logo',
            'currency_symbol' => '$',
            'country' => 'US',
            'cash_on_delivery' => true,
            'digital_payment' => true,
            'schedule_order' => true,
            'order_delivery_verification' => false,
            'maintenance_mode' => false,
            'app_minimum_version_android' => 0.0,
            'app_minimum_version_ios' => 0.0,
            'default_location' => ['lat' => '0', 'lng' => '0'],
            'language' => [
                ['key' => 'en', 'value' => 'English'],
            ],
            'base_urls' => [
                'item_image_url' => '',
                'store_image_url' => '',
                'banner_image_url' => '',
                'category_image_url' => '',
                'campaign_image_url' => '',
                'business_logo_url' => '',
                'notification_image_url' => '',
            ],
            'module_config' => [
                'module_type' => ['food'],
                'food' => [
                    'order_place_to_schedule_interval' => true,
                    'add_on' => true,
                    'stock' => false,
                    'veg_non_veg' => true,
                    'unit' => false,
                    'order_attachment' => false,
                    'show_restaurant_text' => true,
                    'is_parcel' => false,
                    'is_taxi' => false,
                    'new_variation' => true,
                    'description' => 'Food Delivery',
                ],
            ],
        ]);
    }
}
