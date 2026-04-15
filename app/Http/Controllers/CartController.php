<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\GuestCartItem;
use App\Models\Product;
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
        // Important: without `auth:sanctum` middleware, `$request->user()` may not
        // resolve Bearer tokens. Explicitly check the Sanctum guard first.
        return auth('sanctum')->user() ?? $request->user();
    }

    public function index(Request $request)
    {
        $user = $this->resolveUser($request);
        if ($user) {
            return response()->json($user->cartItems()->with('product')->get());
        }

        if ($this->bearerTokenAttempted($request)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return response()->json(['message' => 'guest_id is required for guest cart'], 422);
        }

        $items = GuestCartItem::query()
            ->where('guest_id', $guestId)
            ->with('product')
            ->get();

        return response()->json($items);
    }

    public function add(Request $request)
    {
        $user = $this->resolveUser($request);
        if ($user) {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);

            $cartItem = $user->cartItems()->where('product_id', $request->product_id)->first();

            if ($cartItem) {
                $cartItem->quantity += $request->quantity;
                $cartItem->save();
            } else {
                $cartItem = $user->cartItems()->create([
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                ]);
            }

            return response()->json([
                'message' => 'Added to cart successfully.',
                'cart_item' => $cartItem->load('product'),
            ]);
        }

        if ($this->bearerTokenAttempted($request) && $request->query('guest_id') === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $guestId = (string) $request->query('guest_id', '');
        if ($guestId === '') {
            return response()->json(['message' => 'guest_id is required for guest cart'], 422);
        }

        $product = Product::query()->findOrFail((int) $request->product_id);

        $item = GuestCartItem::query()
            ->where('guest_id', $guestId)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->quantity += (int) $request->quantity;
            $item->save();
        } else {
            $item = GuestCartItem::query()->create([
                'guest_id' => $guestId,
                'product_id' => $product->id,
                'quantity' => (int) $request->quantity,
            ]);
        }

        return response()->json([
            'message' => 'Added to cart successfully.',
            'cart_item' => $item->load('product'),
        ]);
    }

    public function update(Request $request)
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
            if ($resolved instanceof \Illuminate\Http\JsonResponse) {
                return $resolved;
            }
            $resolved->update(['quantity' => (int) $request->quantity]);

            return response()->json(['message' => 'Cart updated successfully.', 'cart_item' => $resolved->fresh('product')]);
        }

        if ($this->bearerTokenAttempted($request)) {
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
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }
        $resolved->update(['quantity' => (int) $request->quantity]);

        return response()->json(['message' => 'Cart updated successfully.', 'cart_item' => $resolved->fresh('product')]);
    }

    public function remove(Request $request)
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
            if ($resolved instanceof \Illuminate\Http\JsonResponse) {
                return $resolved;
            }
            $resolved->delete();

            return response()->json(['message' => 'Cart item removed successfully.']);
        }

        if ($this->bearerTokenAttempted($request)) {
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
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }
        $resolved->delete();

        return response()->json(['message' => 'Cart item removed successfully.']);
    }

    /**
     * @return CartItem|\Illuminate\Http\JsonResponse
     */
    private function resolveUserCartItemForMutation(int $cartId, $user, ?int $productId)
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
                'message' => 'Cart item not found or does not match this product.',
            ], 404);
        }

        return $row;
    }

    /**
     * @return GuestCartItem|\Illuminate\Http\JsonResponse
     */
    private function resolveGuestCartItemForMutation(int $cartId, string $guestId, ?int $productId)
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
                'message' => 'Cart item not found or does not match this product.',
            ], 404);
        }

        return $row;
    }
}
