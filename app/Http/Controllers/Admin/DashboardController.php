<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_orders' => Order::count(),
            'total_revenue' => (float) Order::sum('total_price'),
            'pending_orders' => Order::where('status', 'Pending')->count(),
            'available_products' => Product::count(),
        ]);
    }
}
