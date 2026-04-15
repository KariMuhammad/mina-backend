<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $validated['payment_method'] = $request->input('payment_method', 'cod');
        $validated['notes'] = $request->input('notes');

        $order = $checkout->checkout(
            $user,
            $isGuest ? (string) $validated['guest_id'] : null,
            $validated,
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order,
        ], 201);
    }
}
