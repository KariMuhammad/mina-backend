<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        $coupons = Coupon::orderBy('id', 'desc')->get();

        return response()->json([
            'data' => $coupons->map(fn ($c) => [
                'id'         => $c->id,
                'code'       => $c->code,
                'type'       => $c->type,
                'value'      => (float) $c->value,
                'min_order'  => (float) $c->min_order,
                'max_uses'   => $c->max_uses,
                'used_count' => $c->used_count,
                'expires_at' => $c->expires_at?->format('Y-m-d'),
                'is_active'  => $c->is_active,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => 'required|string|max:64|unique:coupons,code',
            'type'       => 'required|in:percent,fixed',
            'value'      => 'required|numeric|min:0',
            'min_order'  => 'nullable|numeric|min:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['min_order'] = $data['min_order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $coupon = Coupon::create($data);

        return response()->json([
            'message' => 'تم إنشاء الكوبون بنجاح',
            'data'    => $this->formatCoupon($coupon),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $data = $request->validate([
            'code'       => 'sometimes|string|max:64|unique:coupons,code,' . $id,
            'type'       => 'sometimes|in:percent,fixed',
            'value'      => 'sometimes|numeric|min:0',
            'min_order'  => 'nullable|numeric|min:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $coupon->update($data);

        return response()->json([
            'message' => 'تم تحديث الكوبون بنجاح',
            'data'    => $this->formatCoupon($coupon->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => 'تم حذف الكوبون بنجاح',
        ]);
    }

    private function formatCoupon(Coupon $c): array
    {
        return [
            'id'         => $c->id,
            'code'       => $c->code,
            'type'       => $c->type,
            'value'      => (float) $c->value,
            'min_order'  => (float) $c->min_order,
            'max_uses'   => $c->max_uses,
            'used_count' => $c->used_count,
            'expires_at' => $c->expires_at?->format('Y-m-d'),
            'is_active'  => $c->is_active,
        ];
    }
}
