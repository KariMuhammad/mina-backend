<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerCheckoutRequest;
use App\Services\CheckoutService;
use App\Support\CustomerOrderFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CustomerCheckoutController extends Controller
{
    public function store(CustomerCheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        // Explicitly ensure coupon_code is in validated data
        // (nullable fields may be absent from $validated if client doesn't send them)
        $validated['coupon_code'] = $validated['coupon_code'] ?? $request->input('coupon_code') ?? $request->query('coupon_code');

        Log::info('CHECKOUT_REQUEST', [
            'coupon_code_from_validated' => $validated['coupon_code'] ?? 'NOT_PRESENT',
            'coupon_code_from_request' => $request->input('coupon_code', 'NOT_PRESENT'),
            'query_coupon' => $request->query('coupon_code', 'NOT_PRESENT'),
            'all_keys' => array_keys($validated),
        ]);

        $order = $checkout->checkout($request->user(), null, $validated);

        return response()->json([
            'message' => 'Order created successfully!',
            'order' => CustomerOrderFormatter::order($order),
            'items' => CustomerOrderFormatter::items($order),
        ], 201);
    }
}
