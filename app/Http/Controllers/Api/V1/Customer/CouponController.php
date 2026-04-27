<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'code'        => 'required|string|max:64',
            'order_total' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon || !$coupon->isValid((float) $request->order_total)) {
            return response()->json([
                'message' => 'الكوبون غير صالح أو منتهي الصلاحية',
            ], 422);
        }

        $discount = $coupon->calculateDiscount((float) $request->order_total);

        return response()->json([
            'code'            => $coupon->code,
            'type'            => $coupon->type,
            'value'           => (float) $coupon->value,
            'discount_amount' => $discount,
            'final_total'     => round((float) $request->order_total - $discount, 2),
        ]);
    }
}
