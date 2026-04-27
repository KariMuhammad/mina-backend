<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use App\Support\CustomerOrderFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    private function bearerTokenAttempted(Request $request): bool
    {
        $auth = (string) $request->header('Authorization', '');

        return str_starts_with($auth, 'Bearer ');
    }

    private function resolveUser(Request $request)
    {
        return auth('sanctum')->user() ?? $request->user();
    }

    public function store(Request $request, CheckoutService $checkout): JsonResponse
    {
        if ($this->bearerTokenAttempted($request) && $this->resolveUser($request) === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $this->resolveUser($request);
        $isGuest = $user === null;

        if ($isGuest) {
            $request->merge([
                'guest_id' => $request->query('guest_id') ?? $request->input('guest_id'),
            ]);
        }

        $validated = $request->validate(
            CheckoutRequest::rulesFor($isGuest),
            CheckoutRequest::customMessages(),
        );

        // Explicitly ensure coupon_code is in validated data
        // (nullable fields may be absent from $validated if client doesn't send them)
        $validated['coupon_code'] = $validated['coupon_code'] ?? $request->input('coupon_code') ?? $request->query('coupon_code');

        $validated['payment_method'] = $request->input('payment_method', 'cod');
        $validated['notes'] = $request->input('notes');

        Log::info('CHECKOUT_REQUEST_V1', [
            'coupon_code' => $validated['coupon_code'] ?? 'NOT_PRESENT',
            'raw_input_coupon' => $request->input('coupon_code', 'NOT_PRESENT'),
            'query_coupon' => $request->query('coupon_code', 'NOT_PRESENT'),
            'all_input_keys' => array_keys($request->all()),
        ]);

        $order = $checkout->checkout(
            $user,
            $isGuest ? (string) $validated['guest_id'] : null,
            $validated,
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => CustomerOrderFormatter::order($order),
            'items' => CustomerOrderFormatter::items($order),
        ], 201);
    }
}
