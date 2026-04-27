<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::where('is_active', true)
                         ->where(function($query) {
                             $query->whereNull('expires_at')
                                   ->orWhere('expires_at', '>', now());
                         })
                         ->where(function($query) {
                             $query->whereNull('max_uses')
                                   ->orWhereColumn('used_count', '<', 'max_uses');
                         })
                         ->get();

        return response()->json($coupons);
    }
}
