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

        Log::info('CHECKOUT_REQUEST', [
            'coupon_code_from_validated' => $validated['coupon_code'] ?? 'NOT_PRESENT',
            'coupon_code_from_request' => $request->input('coupon_code', 'NOT_PRESENT'),
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
