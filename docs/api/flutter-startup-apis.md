# Flutter Startup APIs Documentation

This document describes the three Laravel API endpoints that the Flutter app calls during startup.

## Overview

These APIs provide configuration, zone, and module data needed for the Flutter app to initialize properly. All responses match the exact JSON structure expected by Flutter models.

---

## 1. Config API

**Endpoint:** `GET /api/v1/config`

**Description:** Returns application configuration and business settings.

**Flutter Model:** `ConfigModel`

**Authentication:** Not required

**Response Status:** 200 OK

### Response Example

```json
{
  "business_name": "6amMart",
  "logo_full_url": "https://placehold.co/200x200/png?text=Logo",
  "address": "123 Business Street, City, Country",
  "phone": "+1234567890",
  "email": "support@6ammart.com",
  "country": "US",
  "default_location": {
    "lat": "40.7128",
    "lng": "-74.0060"
  },
  "currency_symbol": "$",
  "currency_symbol_direction": "left",
  "app_minimum_version_android": 1.0,
  "app_url_android": "https://play.google.com/store/apps",
  "app_minimum_version_ios": 1.0,
  "app_url_ios": "https://apps.apple.com/app",
  "customer_verification": true,
  "schedule_order": true,
  "order_delivery_verification": true,
  "cash_on_delivery": true,
  "digital_payment": true,
  "per_km_shipping_charge": 5.0,
  "minimum_shipping_charge": 2.0,
  "demo": false,
  "maintenance_mode": false,
  "order_confirmation_model": "manual",
  "show_dm_earning": true,
  "canceled_by_deliveryman": true,
  "timeformat": "24",
  "language": [
    {"key": "en", "value": "English"},
    {"key": "ar", "value": "Arabic"}
  ],
  "toggle_veg_non_veg": true,
  "toggle_dm_registration": true,
  "toggle_store_registration": true,
  "schedule_order_slot_duration": 30,
  "digit_after_decimal_point": 2,
  "module_config": {
    "module_type": ["food", "grocery"],
    "food": {
      "order_place_to_schedule_interval": true,
      "add_on": true,
      "stock": false,
      "veg_non_veg": true,
      "unit": false,
      "order_attachment": false,
      "show_restaurant_text": true,
      "is_parcel": false,
      "is_taxi": false,
      "new_variation": true,
      "description": "Food delivery service"
    }
  },
  "module": null,
  "parcel_per_km_shipping_charge": 3.0,
  "parcel_minimum_shipping_charge": 1.5,
  "landing_page_settings": {
    "mobile_app_section_image": "https://placehold.co/600x400/png?text=App",
    "top_content_image": "https://placehold.co/1200x600/png?text=Banner"
  },
  "social_media": [
    {"id": 1, "name": "facebook", "link": "https://facebook.com", "status": 1},
    {"id": 2, "name": "twitter", "link": "https://twitter.com", "status": 1}
  ],
  "footer_text": "© 2024 6amMart. All rights reserved.",
  "download_user_app_links": {
    "playstore_url_status": "1",
    "playstore_url": "https://play.google.com/store/apps",
    "apple_store_url_status": "1",
    "apple_store_url": "https://apps.apple.com/app"
  },
  "loyalty_point_exchange_rate": 100,
  "loyalty_point_item_purchase_point": 1.0,
  "loyalty_point_status": 1,
  "loyalty_point_minimum_point": 10,
  "customer_wallet_status": 1,
  "dm_tips_status": 1,
  "ref_earning_status": 1,
  "ref_earning_exchange_rate": 50.0,
  "social_login": [
    {"login_medium": "google", "status": true, "client_id": "", "redirect_url_flutter": ""},
    {"login_medium": "facebook", "status": true, "client_id": "", "redirect_url_flutter": ""}
  ],
  "apple_login": [
    {"login_medium": "apple", "status": true, "client_id": "", "redirect_url_flutter": ""}
  ],
  "refund_active_status": true,
  "refund_policy": 1,
  "cancelation_policy": 1,
  "shipping_policy": 1,
  "prescription_order_status": false,
  "cookies_text": "We use cookies to improve your experience",
  "home_delivery_status": 1,
  "takeaway_status": 1,
  "partial_payment_status": 1,
  "partial_payment_method": "wallet",
  "additional_charge_status": 1,
  "additional_charge_name": "Service Fee",
  "additional_charge": 2.5,
  "active_payment_method_list": [
    {"gateway": "cash_on_delivery", "gateway_title": "Cash on Delivery", "gateway_image_full_url": ""},
    {"gateway": "digital_payment", "gateway_title": "Digital Payment", "gateway_image_full_url": ""}
  ],
  "digital_payment_info": {
    "digital_payment": true,
    "plugin_payment_gateways": true,
    "default_payment_gateways": true
  },
  "add_fund_status": 1,
  "offline_payment_status": 1,
  "guest_checkout_status": 1,
  "admin_commission": 15.0,
  "subscription_free_trial_days": 7,
  "subscription_free_trial_status": 1,
  "subscription_business_model": 1,
  "commission_business_model": 1,
  "subscription_free_trial_type": "day",
  "country_picker_status": 1,
  "firebase_otp_verification": 1,
  "centralize_login": {
    "manual_login_status": 1,
    "otp_login_status": 1,
    "social_login_status": 1,
    "google_login_status": 1,
    "facebook_login_status": 1,
    "apple_login_status": 1,
    "email_verification_status": 1,
    "phone_verification_status": 1
  },
  "vehicle_distance_min": 10.0,
  "vehicle_hourly_min": 50.0,
  "vehicle_day_wise_min": 200.0,
  "admin_free_delivery": {
    "status": 1,
    "free_delivery_over_amount": 50.0,
    "free_delivery_over_amount_status": 1
  },
  "is_sms_active": true,
  "is_mail_active": true,
  "parcel_cancellation_status": 1,
  "parcel_cancellation_basic_setup": {
    "cancellation_fee_type": "percentage",
    "cancellation_fee": 10.0
  },
  "parcel_return_time_fee": {
    "return_time": 24,
    "return_fee": 5.0
  },
  "websocket_status": 0,
  "websocket_url": ""
}
```

### cURL Example

```bash
curl -X GET "http://localhost:8000/api/v1/config"
```

---

## 2. Zone API

**Endpoint:** `GET /api/v1/config/get-zone-id`

**Description:** Returns zone information based on geographic coordinates.

**Flutter Model:** `ZoneModel`

**Authentication:** Not required

**Query Parameters:**
- `lat` (required, numeric): Latitude coordinate
- `lng` (required, numeric): Longitude coordinate

**Response Status:** 200 OK

### Response Example

```json
{
  "zone_id": "[1,2]",
  "zone_data": [
    {
      "id": 1,
      "status": 1,
      "cash_on_delivery": true,
      "digital_payment": true,
      "offline_payment": false,
      "modules": [
        {
          "id": 1,
          "module_name": "Food",
          "module_type": "food",
          "thumbnail": "food_thumb.png",
          "status": "active",
          "stores_count": 150,
          "created_at": "2024-01-01T00:00:00.000000Z",
          "updated_at": "2024-01-15T10:30:00.000000Z",
          "icon": "food_icon.png",
          "theme_id": 1,
          "description": "Order food from your favorite restaurants",
          "all_zone_service": 0,
          "pivot": {
            "zone_id": 1,
            "module_id": 1,
            "per_km_shipping_charge": 5.0,
            "minimum_shipping_charge": 2.0,
            "maximum_shipping_charge": 50.0,
            "maximum_cod_order_amount": 500.0,
            "delivery_charge_type": "distance",
            "fixed_shipping_charge": 0.0
          }
        }
      ]
    }
  ]
}
```

### Important Notes

- The `zone_id` field is a **JSON-encoded string** (e.g., `"[1,2]"`), not a direct JSON array
- This is a special requirement from Flutter's ZoneModel parsing logic

### cURL Example

```bash
curl -X GET "http://localhost:8000/api/v1/config/get-zone-id?lat=40.7128&lng=-74.0060"
```

### Error Responses

**Missing Parameters (400 Bad Request):**
```json
{
  "error": true,
  "message": "The lat field is required.",
  "code": "VALIDATION_ERROR"
}
```

**Invalid Coordinates (400 Bad Request):**
```json
{
  "error": true,
  "message": "The lat must be a number.",
  "code": "VALIDATION_ERROR"
}
```

---

## 3. Module API

**Endpoint:** `GET /api/v1/module`

**Description:** Returns list of available service modules.

**Flutter Model:** `ModuleModel`

**Authentication:** Not required

**Response Status:** 200 OK

**Response Type:** JSON Array

### Response Example

```json
[
  {
    "id": 1,
    "module_name": "Food",
    "module_type": "food",
    "slug": "food",
    "thumbnail_full_url": "https://placehold.co/300x150/png?text=Food",
    "icon_full_url": "https://placehold.co/100x100/png?text=Food",
    "theme_id": 1,
    "description": "Order food from your favorite restaurants",
    "stores_count": 150,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "zones": [
      {
        "id": 1,
        "name": "Downtown",
        "status": 1,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-10T08:00:00.000000Z",
        "cash_on_delivery": true,
        "digital_payment": true
      }
    ]
  },
  {
    "id": 2,
    "module_name": "Grocery",
    "module_type": "grocery",
    "slug": "grocery",
    "thumbnail_full_url": "https://placehold.co/300x150/png?text=Grocery",
    "icon_full_url": "https://placehold.co/100x100/png?text=Grocery",
    "theme_id": 1,
    "description": "Fresh groceries delivered to your door",
    "stores_count": 75,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "zones": [
      {
        "id": 1,
        "name": "Downtown",
        "status": 1,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-10T08:00:00.000000Z",
        "cash_on_delivery": true,
        "digital_payment": true
      }
    ]
  }
]
```

### Module Types

Valid `module_type` values:
- `food` - Food delivery
- `grocery` - Grocery delivery
- `pharmacy` - Medicine and health products
- `ecommerce` - General e-commerce
- `parcel` - Parcel delivery service
- `rental` - Rental services

### cURL Example

```bash
curl -X GET "http://localhost:8000/api/v1/module"
```

---

## Common Error Response Format

All endpoints use a consistent error response format:

```json
{
  "error": true,
  "message": "Error description here",
  "code": "ERROR_CODE"
}
```

### Error Codes

- `VALIDATION_ERROR` - Invalid or missing parameters
- `INTERNAL_ERROR` - Unexpected server error

---

## Data Type Notes

### Important Field Types

1. **Numeric Fields**: All numeric values (prices, charges, versions) are returned as numbers, not strings
2. **Boolean Fields**: Boolean values are returned as `true`/`false` or `1`/`0` depending on Flutter's parsing logic
3. **Timestamps**: All timestamps use ISO 8601 format: `YYYY-MM-DDTHH:MM:SS.000000Z`
4. **URLs**: All URL fields contain complete URLs or empty strings (never null)
5. **Arrays**: Array fields are always arrays (possibly empty `[]`), never null

### Special Cases

- **zone_id in Zone API**: Must be a JSON-encoded string (e.g., `"[1,2]"`), not a direct array
- **module in Config API**: Can be `null` or a complete ModuleModel object
- **All snake_case**: All JSON keys use snake_case naming convention

---

## Testing the APIs

### Quick Test

```bash
# Test Config API
curl http://localhost:8000/api/v1/config

# Test Zone API
curl "http://localhost:8000/api/v1/config/get-zone-id?lat=40.7128&lng=-74.0060"

# Test Module API
curl http://localhost:8000/api/v1/module
```

### Expected Behavior

1. All three endpoints should return 200 OK status
2. Responses should match the exact JSON structure shown above
3. No null values for required fields
4. All data types should match Flutter model expectations

---

## Implementation Notes

### Current Status

- **Phase 1**: Mock data implementation (COMPLETE)
- All three controllers return hardcoded mock data
- Data structures exactly match Flutter model expectations
- Error handling implemented for Zone API validation

### Future Enhancements

- Replace mock data with database queries
- Add authentication for sensitive endpoints
- Implement zone detection based on actual geographic boundaries
- Add caching for frequently accessed configuration data
- Implement rate limiting

---

## Controller Locations

- **ConfigController**: `backend/app/Http/Controllers/Api/V1/ConfigController.php`
- **ZoneController**: `backend/app/Http/Controllers/Api/V1/ZoneController.php`
- **ModuleController**: `backend/app/Http/Controllers/Api/V1/ModuleController.php`

## Route Definitions

Routes are defined in: `backend/routes/api.php`

```php
Route::prefix('v1')->group(function () {
    Route::get('config', [ConfigController::class, 'getConfig']);
    Route::get('config/get-zone-id', [ZoneController::class, 'getZoneId']);
    Route::get('module', [ModuleController::class, 'getModules']);
});
```
