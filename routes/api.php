<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ZoneController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\BannerController as V1BannerController;
use App\Http\Controllers\Api\V1\CartController as V1CartController;
use App\Http\Controllers\Api\V1\CheckoutController as V1CheckoutController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\CustomerCheckoutController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\AppSettingController as ApiAppSettingController;
use App\Http\Controllers\Admin\AppSettingController as AdminAppSettingController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\DeliveryZoneController;

Route::get('/support-info', [SupportController::class, 'info']);
Route::get('/health', [HealthController::class, 'index']);

// الرابط بتاع تسجيل الدخول (مفتوح لأي حد)
Route::post('/login-phone', [AuthController::class, 'loginWithPhone']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// الروابط بتاعة المنتجات والأقسام (مفتوحة لأي حد يتفرج)
Route::prefix('v1')->group(function () {
    // Flutter startup API endpoints
    Route::get('config', [ConfigController::class, 'getConfig']);
    Route::get('config/get-zone-id', [ZoneController::class, 'getZoneId']);
    Route::get('module', [ModuleController::class, 'getModules']);
    Route::get('banners', [V1BannerController::class, 'getBanners']);
    
    // Cart API
    Route::get('customer/cart/list', [V1CartController::class, 'getCartList']);
    Route::post('customer/cart/add', [V1CartController::class, 'addToCart']);
    Route::post('customer/cart/update', [V1CartController::class, 'updateCart']);
    Route::delete('customer/cart/remove-item', [V1CartController::class, 'removeItem']);
    Route::delete('customer/cart/remove', [V1CartController::class, 'clearCart']);

    // Stores endpoints
    Route::get('stores/recommended', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'stores' => []
        ]);
    });
    
    Route::get('stores/latest', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'stores' => []
        ]);
    });
    
    Route::get('stores/top-offer-near-me', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'stores' => []
        ]);
    });
    
    // Flash sale endpoints
    Route::get('flash-sales', function () {
        return response()->json([
            'id' => null,
            'title' => null,
            'start_date' => null,
            'end_date' => null,
            'products' => []
        ]);
    });
    
    // Promotional banners
    Route::get('other-banners', function () {
        return response()->json([]);
    });
    
    // Items endpoints
    Route::get('items/discounted', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'products' => []
        ]);
    });
    
    Route::get('items/recommended', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'products' => []
        ]);
    });
    
    // Campaign endpoints
    Route::get('campaigns/basic', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'campaigns' => []
        ]);
    });
    
    Route::get('campaigns/item', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'campaigns' => []
        ]);
    });

    Route::get('/page/{slug}', [PageController::class, 'show']);
    Route::get('/terms-and-conditions', [PageController::class, 'termsAndConditions']);
    Route::get('/support-info', [SupportController::class, 'info']);

    // Mock endpoints for Flutter startup
    Route::get('/config', function () {
        $currencySymbol = \App\Models\AppSetting::where('key', 'currency_symbol')->value('value') ?? 'ل.س';

        return response()->json([
            'status' => true,
            'business_name' => 'Demo E-commerce',
            'logo_full_url' => 'https://placehold.co/100x100?text=Logo',
            'currency_symbol' => $currencySymbol,
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
                ['key' => 'en', 'value' => 'English']
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
                    'description' => 'Food Delivery'
                ]
            ]
        ]);
    });
    Route::get('/module', function () {
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
            ]
        ]);
    });
    Route::get('/config/get-zone-id', function () {
        return response()->json(['zone_id' => '[1]']);
    });
    Route::get('/banners', function () {
        $bannersArr = array_map(function($b) {
            return [
                'id' => $b['id'],
                'image_full_url' => $b['image_url'],
                'title' => $b['title'],
                'type' => 'default',
                'link' => null,
                'store' => null,
                'item' => null,
            ];
        }, \App\Models\Banner::where('is_active', true)->get()->toArray());
        
        return response()->json(['campaigns' => [], 'banners' => $bannersArr]);
    });
    Route::get('/categories', function () {
        $cats = array_map(function($c) {
            return [
                'id' => $c['id'],
                'name' => $c['name'],
                'image' => 'https://placehold.co/100x100?text=' . urlencode($c['name']), // Mock categories image
                'slug' => strtolower($c['name'])
            ];
        }, \App\Models\Category::all()->toArray());
        return response()->json($cats);
    });

    $formatProduct = function($p) {
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'description' => $p['description'] ?? 'A delicious item',
            'image_full_url' => $p['image'] ?? 'https://placehold.co/100x100?text=Item',
            'price' => (float) $p['price'],
            'tax' => 0,
            'discount' => (float) ($p['discount_percent'] ?? 0),
            'discount_type' => 'percent',
            'available_time_starts' => '00:00:00',
            'available_time_ends' => '23:59:59',
            'store_id' => 1,
            'store_name' => 'Demo Store',
            'zone_id' => 1,
            'rating_count' => 10,
            'avg_rating' => (float) ($p['rating'] ?? 4.5),
            'module_id' => 1,
            'module_type' => 'food',
            'veg' => 0,
            'stock' => 100,
            'unit_type' => 'pc',
            'category_ids' => [
                ['id' => (string)($p['category_id'] ?? 1), 'position' => 1]
            ]
        ];
    };

    Route::get('/items/popular', function () use ($formatProduct) {
        $products = \App\Models\Product::where('is_popular', true)->limit(10)->get();
        $formattedProducts = $products->map(function($p) use ($formatProduct) {
            return $formatProduct($p->toArray());
        });
        
        return response()->json([
            'total_size' => $formattedProducts->count(),
            'limit' => 10,
            'offset' => 1,
            'products' => $formattedProducts
        ]);
    });

    Route::get('/items/most-reviewed', function () use ($formatProduct) {
        $products = \App\Models\Product::limit(10)->get();
        $formattedProducts = $products->map(function($p) use ($formatProduct) {
            return $formatProduct($p->toArray());
        });
        return response()->json([
            'total_size' => $formattedProducts->count(),
            'limit' => 10,
            'offset' => 1,
            'products' => $formattedProducts
        ]);
    });

    Route::get('/stores/get-stores/all', function () {
        return response()->json([
            'total_size' => 0,
            'limit' => 10,
            'offset' => 1,
            'stores' => []
        ]);
    });

    Route::get('/advertisement/list', function () {
        return response()->json([]);
    });

    Route::get('/store/popular', function () {
        return response()->json([
            'total_size' => 3, 'limit' => 10, 'offset' => 1,
            'stores' => [
                [
                    'id' => 1, 'name' => 'Burger King', 'logo' => 'https://placehold.co/100x100?text=BK',
                    'address' => '123 Main St', 'rating' => 4.5, 'zone_id' => 1,
                ],
                [
                    'id' => 2, 'name' => 'Pizza Hut', 'logo' => 'https://placehold.co/100x100?text=PH',
                    'address' => '456 Elm St', 'rating' => 4.0, 'zone_id' => 1,
                ],
                [
                    'id' => 3, 'name' => 'McDonalds', 'logo' => 'https://placehold.co/100x100?text=MD',
                    'address' => '789 Oak St', 'rating' => 4.8, 'zone_id' => 1,
                ]
            ]
        ]);
    });
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('customer/cart/summary', [V1CartController::class, 'cartSummary']);
    Route::post('customer/checkout', [CustomerCheckoutController::class, 'store']);
    Route::get('customer/addresses', [CustomerAddressController::class, 'index']);
    Route::post('customer/addresses', [CustomerAddressController::class, 'store']);
    Route::put('customer/addresses/{id}', [CustomerAddressController::class, 'update']);
    Route::delete('customer/addresses/{id}', [CustomerAddressController::class, 'destroy']);
    Route::get('customer/orders', [CustomerOrderController::class, 'index']);
    Route::get('customer/orders/{id}', [CustomerOrderController::class, 'show']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
});

Route::get('/banners', [BannerController::class, 'index']);
Route::post('/banners', [BannerController::class, 'store']);
Route::post('/banners/{id}', [BannerController::class, 'update']);

Route::get('/settings', [SettingController::class, 'index']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::post('/categories/{id}', [CategoryController::class, 'update']);

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::post('/products/{id}', [ProductController::class, 'update']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/orders/track', [OrderController::class, 'track']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/support/info', [SupportController::class, 'info']);

// Cart Endpoints
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart/add', [CartController::class, 'add']);
Route::post('/cart/update', [CartController::class, 'update']);
Route::delete('/cart/remove', [CartController::class, 'remove']);

Route::post('/checkout', [V1CheckoutController::class, 'store']);

// Aliases matching simpler clients
Route::post('/add-to-cart', [CartController::class, 'add']);
Route::get('/cart-items', [CartController::class, 'index']);

// الرابط بتاع الطلبات (لازم اليوزر يكون مسجل دخول ومعاه التوكن)
Route::middleware('auth:sanctum')->group(function () {
    // لما اليوزر يعوز يعرف بياناته
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);

    

    // Address Endpoints
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);

    // Wallet & Promotions
    Route::get('/wallet', [WalletController::class, 'wallet']);
    Route::get('/loyalty-points', [WalletController::class, 'loyaltyPoints']);
    Route::get('/coupons', [CouponController::class, 'index']);

    // لما اليوزر يعوز يشوف طلباته
    Route::get('/orders', [OrderController::class, 'index']);

    // لما اليوزر يدوس تأكيد الطلب
    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'stats']);

    Route::post('products', [AdminProductController::class, 'store']);
    Route::put('products/{id}', [AdminProductController::class, 'update']);
    Route::post('products/{id}', [AdminProductController::class, 'update']);
    Route::delete('products/{id}', [AdminProductController::class, 'destroy']);
    Route::patch('products/{id}/price', [AdminProductController::class, 'updatePrice']);

    Route::post('categories', [AdminCategoryController::class, 'store']);
    Route::put('categories/{id}', [AdminCategoryController::class, 'update']);
    Route::post('categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/stats', [AdminOrderController::class, 'stats']);
    Route::patch('orders/{id}', [AdminOrderController::class, 'update']);
    Route::patch('orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::patch('orders/{id}/payment', [AdminOrderController::class, 'updatePayment']);
    Route::post('orders/{id}/send-whatsapp', [AdminOrderController::class, 'sendWhatsApp']);
    Route::post('orders/{id}/whatsapp-sent', [AdminOrderController::class, 'markWhatsappSent']);
    Route::delete('orders/{id}', [AdminOrderController::class, 'destroy']);

    // App Settings — Admin
    Route::get('app-settings', [AdminAppSettingController::class, 'index']);
    Route::post('app-settings/bulk', [AdminAppSettingController::class, 'bulkUpdate']);
    Route::put('app-settings/{key}', [AdminAppSettingController::class, 'update']);

    // Banners — Admin
    Route::get('banners', [AdminBannerController::class, 'index']);
    Route::post('banners', [AdminBannerController::class, 'store']);
    Route::post('banners/{id}', [AdminBannerController::class, 'update']);
    Route::delete('banners/{id}', [AdminBannerController::class, 'destroy']);
    Route::patch('banners/{id}/toggle', [AdminBannerController::class, 'toggle']);

    // Admin Profile
    Route::put('change-password', [AdminProfileController::class, 'changePassword']);

    // Delivery Zones — Admin
    Route::get('delivery-zones', [DeliveryZoneController::class, 'index']);
    Route::post('delivery-zones', [DeliveryZoneController::class, 'store']);
    Route::put('delivery-zones/{id}', [DeliveryZoneController::class, 'update']);
    Route::delete('delivery-zones/{id}', [DeliveryZoneController::class, 'destroy']);

    // Pricing — Admin
    Route::post('/pricing', [ApiAppSettingController::class, 'updatePricing']);
});

// App Settings — Public
Route::prefix('app-settings')->group(function () {
    Route::get('/', [ApiAppSettingController::class, 'index']);
    Route::get('/{key}', [ApiAppSettingController::class, 'show']);
});

// ===== Pricing Routes =====
// Public (mobile app)
Route::get('/pricing', [ApiAppSettingController::class, 'pricing']);
