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
                             $query->whereNull('valid_until')
                                   ->orWhere('valid_until', '>=', now());
                         })
                         ->get();

        return response()->json($coupons);
    }
}
