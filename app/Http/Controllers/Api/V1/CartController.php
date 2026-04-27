<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\AppSetting;
use App\Models\GuestCartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
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

    public function getCartList(Request $request): JsonResponse
    {
        if ($this->bearerTokenAttempted($request) && !$this->resolveUser($request) && $request->query('guest_id') === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $items = $this->resolveCartItems($request);

        return response()->json($items);
    }

    /**
     * Authenticated cart subtotal and optional active coupons (requires auth:sanctum on route).
     */
    public function cartSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $lines = $user->cartItems()->with('product')->orderBy('id')->get();

        $subtotal = 0.0;
        $items = [];

        foreach ($lines as $line) {
            $unit = (float) ($line->product?->price ?? 0);
            $qty = (int) $line->quantity;
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;
            $items[] = [
                'cart_id' => $line->id,
                'product_id' => $line->product_id,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'product' => $line->product ? [
                    'id' => $line->product->id,
                    'name' => $line->product->name,
                    'price' => $unit,
                ] : null,
            ];
        }

        $subtotal = round($subtotal, 2);

        $deliveryFee = (float) (AppSetting::where('key', 'delivery_price')->value('value') ?? 0);
        $total = round($subtotal + $deliveryFee, 2);

        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q): void {
                $q->whereNull('max_uses')
                    ->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'type', 'value', 'min_order', 'expires_at'])
            ->map(fn (Coupon $c) => [
                'code' => $c->code,
                'type' => $c->type,
                'value' => (float) $c->value,
                'min_order' => (float) $c->min_order,
                'expires_at' => $c->expires_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return response()->json([
            'items' => $items,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'coupons' => $coupons,
        ]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user) {
            $request->validate([
                // Flutter template sends item_id, your simpler client sends product_id
                'product_id' => 'nullable|exists:products,id',
                'item_id' => 'nullable|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            $productId = (int) ($request->input('product_id') ?? $request->input('item_id'));
            if ($productId <= 0) {
                return response()->json(['message' => 'product_id (or item_id) is required'], 422);
            }

            $cartItem = $user->cartItems()->where('product_id', $productId)->first();
            if ($cartItem) {
                $cartItem->quantity += (int) $request->quantity;
                $cartItem->save();
            } else {
                $cartItem = $user->cartItems()->create([
                    'product_id' => $productId,
                    'quantity' => (int) $request->quantity,
                ]);
            }
            return response()->json($this->resolveCartItems($request));
        }

        if ($this->bearerTokenAttempted($request) && $request->query('guest_id') === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            // Flutter template sends item_id, your simpler client sends product_id
            'product_id' => 'nullable|exists:products,id',
            'item_id' => 'nullable|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $productId = (int) ($request->input('product_id') ?? $request->input('item_id'));
        if ($productId <= 0) {
            return response()->json(['message' => 'product_id (or item_id) is required'], 422);
        }

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return response()->json(['message' => 'guest_id is required for guest cart'], 422);
        }

        $product = Product::query()->findOrFail($productId);
        $item = GuestCartItem::query()
            ->where('guest_id', $guestId)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->quantity += (int) $request->quantity;
            $item->save();
        } else {
            GuestCartItem::query()->create([
                'guest_id' => $guestId,
                'product_id' => $product->id,
                'quantity' => (int) $request->quantity,
            ]);
        }

        return response()->json($this->resolveCartItems($request));
    }

    public function updateCart(Request $request): JsonResponse
    {
        $request->validate([
            'cart_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'product_id' => 'nullable|integer|exists:products,id',
        ]);

        $user = $this->resolveUser($request);
        if ($user) {
            $resolved = $this->resolveUserCartItemForMutation(
                (int) $request->cart_id,
                $user,
                $request->filled('product_id') ? (int) $request->product_id : null
            );
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            $resolved->update(['quantity' => (int) $request->quantity]);

            return response()->json([
                'message' => 'Cart item updated successfully.',
                'cart' => $this->resolveCartItems($request),
            ], 200);
        }

        if ($this->bearerTokenAttempted($request) && $request->query('guest_id') === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return response()->json(['message' => 'guest_id is required for guest cart'], 422);
        }
        $resolved = $this->resolveGuestCartItemForMutation(
            (int) $request->cart_id,
            $guestId,
            $request->filled('product_id') ? (int) $request->product_id : null
        );
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $resolved->update(['quantity' => (int) $request->quantity]);

        return response()->json([
            'message' => 'Cart item updated successfully.',
            'cart' => $this->resolveCartItems($request),
        ], 200);
    }

    public function removeItem(Request $request): JsonResponse
    {
        $request->validate([
            'cart_id' => 'required|integer',
            'product_id' => 'nullable|integer|exists:products,id',
        ]);

        $user = $this->resolveUser($request);
        if ($user) {
            $resolved = $this->resolveUserCartItemForMutation(
                (int) $request->cart_id,
                $user,
                $request->filled('product_id') ? (int) $request->product_id : null
            );
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            $resolved->delete();

            return response()->json([
                'message' => 'Cart item removed successfully.',
                'cart' => $this->resolveCartItems($request),
            ]);
        }

        if ($this->bearerTokenAttempted($request) && $request->query('guest_id') === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return response()->json(['message' => 'guest_id is required for guest cart'], 422);
        }
        $resolved = $this->resolveGuestCartItemForMutation(
            (int) $request->cart_id,
            $guestId,
            $request->filled('product_id') ? (int) $request->product_id : null
        );
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $resolved->delete();

        return response()->json([
            'message' => 'Cart item removed successfully.',
            'cart' => $this->resolveCartItems($request),
        ]);
    }

    /**
     * @return CartItem|JsonResponse
     */
    private function resolveUserCartItemForMutation(int $cartId, $user, ?int $productId): CartItem|JsonResponse
    {
        $row = CartItem::query()->find($cartId);
        if ($row === null) {
            return response()->json(['message' => 'Cart item not found!'], 404);
        }
        if ((int) $row->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Access denied! This cart item does not belong to you.',
            ], 403);
        }
        if ($productId !== null && (int) $row->product_id !== $productId) {
            return response()->json([
                'message' => 'Cart item not found for the given product.',
            ], 404);
        }

        return $row;
    }

    /**
     * @return GuestCartItem|JsonResponse
     */
    private function resolveGuestCartItemForMutation(int $cartId, string $guestId, ?int $productId): GuestCartItem|JsonResponse
    {
        $row = GuestCartItem::query()->find($cartId);
        if ($row === null) {
            return response()->json(['message' => 'Cart item not found!'], 404);
        }
        if ($row->guest_id !== $guestId) {
            return response()->json([
                'message' => 'Access denied! This cart item does not belong to you.',
            ], 403);
        }
        if ($productId !== null && (int) $row->product_id !== $productId) {
            return response()->json([
                'message' => 'Cart item not found for the given product.',
            ], 404);
        }

        return $row;
    }

    public function clearCart(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user) {
            $user->cartItems()->delete();
            return response()->json([]);
        }

        if ($this->bearerTokenAttempted($request) && $request->query('guest_id') === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return response()->json(['message' => 'guest_id is required for guest cart'], 422);
        }

        GuestCartItem::query()->where('guest_id', $guestId)->delete();
        return response()->json([]);
    }

    /**
     * Returns an array shaped like the Flutter `OnlineCartModel` expects.
     */
    private function resolveCartItems(Request $request): array
    {
        $user = $this->resolveUser($request);
        if ($user) {
            return $user
                ->cartItems()
                ->with('product')
                ->get()
                ->map(function ($ci) {
                    return [
                        'id' => $ci->id,
                        'user_id' => $ci->user_id,
                        'module_id' => null,
                        'item_id' => $ci->product_id,
                        'is_guest' => false,
                        'add_on_ids' => [],
                        'add_on_qtys' => [],
                        'item_type' => 'product',
                        'price' => (double) ($ci->product?->price ?? 0),
                        'quantity' => (int) $ci->quantity,
                        'variation' => [],
                        'created_at' => optional($ci->created_at)->toISOString(),
                        'updated_at' => optional($ci->updated_at)->toISOString(),
                        'item' => $ci->product ? [
                            'id' => $ci->product->id,
                            'name' => $ci->product->name,
                            'price' => (double) $ci->product->price,
                            'image_full_url' => $ci->product->image_url,
                            'description' => $ci->product->description,
                        ] : null,
                    ];
                })
                ->values()
                ->all();
        }

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return [];
        }

        return GuestCartItem::query()
            ->where('guest_id', $guestId)
            ->with('product')
            ->get()
            ->map(function ($ci) use ($guestId) {
                return [
                    'id' => $ci->id,
                    'user_id' => null,
                    'module_id' => null,
                    'item_id' => $ci->product_id,
                    'is_guest' => true,
                    'add_on_ids' => [],
                    'add_on_qtys' => [],
                    'item_type' => 'product',
                    'price' => (double) ($ci->product?->price ?? 0),
                    'quantity' => (int) $ci->quantity,
                    'variation' => [],
                    'created_at' => optional($ci->created_at)->toISOString(),
                    'updated_at' => optional($ci->updated_at)->toISOString(),
                    'item' => $ci->product ? [
                        'id' => $ci->product->id,
                        'name' => $ci->product->name,
                        'price' => (double) $ci->product->price,
                        'image_full_url' => $ci->product->image_url,
                        'description' => $ci->product->description,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }
}
