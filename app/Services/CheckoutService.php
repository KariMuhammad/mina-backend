<?php

namespace App\Services;

use App\Models\Address;
use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\GuestCartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * Create an order from the current cart, apply optional coupon, snapshot shipping, decrement stock, clear cart.
     *
     * @param  array<string, mixed>  $data  Validated request data (see CheckoutRequest)
     */
    public function checkout(?User $user, ?string $guestId, array $data): Order
    {
        $addressText = $this->resolveAddressText($user, $data);

        if (! app(DeliveryZoneService::class)->isAddressAllowed($addressText)) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'التوصيل غير متاح في هذه المنطقة حاليًا',
                ], 422)
            );
        }

        $deliveryZone = $this->resolveDeliveryZone($user, $data);

        return DB::transaction(function () use ($user, $guestId, $data, $deliveryZone) {
            if ($user !== null) {
                return $this->placeOrderForUser($user, $data, $deliveryZone);
            }

            return $this->placeOrderForGuest((string) $guestId, $data, $deliveryZone);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placeOrderForUser(User $user, array $data, ?DeliveryZone $deliveryZone): Order
    {
        $lines = $user->cartItems()->with('product')->orderBy('id')->get();
        $this->assertCartNotEmpty($lines->isEmpty());

        [$addressId, $shippingSnapshot] = $this->resolveUserShipping($user, $data);

        $priced = $this->lockProductsAndBuildLines($lines);

        $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $priced['subtotal']);

        $paymentMethod = (string) ($data['payment_method'] ?? 'cod');
        $notes = isset($data['notes']) ? (string) $data['notes'] : null;

        return $this->persistOrder(
            userId: $user->id,
            guestId: null,
            guestName: null,
            guestEmail: null,
            guestPhone: null,
            addressId: $addressId,
            deliveryZoneId: $deliveryZone?->id,
            shippingSnapshot: $shippingSnapshot,
            priced: $priced,
            coupon: $coupon,
            clearCart: fn () => $user->cartItems()->delete(),
            paymentMethod: $paymentMethod,
            notes: $notes,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?int, 1: array<string, mixed>}
     */
    private function resolveUserShipping(User $user, array $data): array
    {
        $inline = $data['shipping_address'] ?? null;
        if (is_array($inline) && $inline !== []) {
            return [null, $this->snapshotFromInline($inline)];
        }

        $addressPk = $data['shipping_address_id'] ?? $data['address_id'] ?? null;
        if ($addressPk === null || $addressPk === '') {
            throw ValidationException::withMessages([
                'shipping_address_id' => ['A shipping address is required.'],
            ]);
        }

        $address = Address::query()->where('id', (int) $addressPk)->first();
        if ($address === null) {
            throw new HttpResponseException(
                response()->json(['message' => 'Resource not found.'], 404)
            );
        }
        if ((int) $address->user_id !== (int) $user->id) {
            throw new HttpResponseException(
                response()->json(['message' => 'Access denied! This resource does not belong to you.'], 403)
            );
        }

        return [$address->id, $this->snapshotFromAddress($address)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placeOrderForGuest(string $guestId, array $data, ?DeliveryZone $deliveryZone): Order
    {
        $lines = GuestCartItem::query()
            ->where('guest_id', $guestId)
            ->with('product')
            ->orderBy('id')
            ->get();

        $this->assertCartNotEmpty($lines->isEmpty());

        $shippingSnapshot = [
            'name' => $data['guest_name'],
            'phone' => $data['guest_phone'],
            'email' => $data['guest_email'],
            'address_line_1' => $data['shipping_address_line_1'],
            'address_line_2' => $data['shipping_address_line_2'] ?? null,
            'city' => $data['shipping_city'],
            'zip' => $data['shipping_zip'],
            'country' => $data['shipping_country'] ?? 'Default Country',
        ];

        $priced = $this->lockProductsAndBuildLines($lines);

        $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $priced['subtotal']);

        $paymentMethod = (string) ($data['payment_method'] ?? 'cod');
        $notes = isset($data['notes']) ? (string) $data['notes'] : null;

        return $this->persistOrder(
            userId: null,
            guestId: $guestId,
            guestName: $data['guest_name'],
            guestEmail: $data['guest_email'],
            guestPhone: $data['guest_phone'],
            addressId: null,
            deliveryZoneId: $deliveryZone?->id,
            shippingSnapshot: $shippingSnapshot,
            priced: $priced,
            coupon: $coupon,
            clearCart: fn () => GuestCartItem::query()->where('guest_id', $guestId)->delete(),
            paymentMethod: $paymentMethod,
            notes: $notes,
        );
    }

    private function assertCartNotEmpty(bool $empty): void
    {
        if ($empty) {
            throw new HttpResponseException(
                response()->json(['message' => 'Your cart is empty.'], 400)
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\CartItem|\App\Models\GuestCartItem>  $lines
     * @return array{subtotal: float, rows: list<array{product: \App\Models\Product, quantity: int, unit_price: float}>}
     */
    private function lockProductsAndBuildLines($lines): array
    {
        $subtotal = 0.0;
        $rows = [];

        foreach ($lines as $line) {
            $product = Product::query()->lockForUpdate()->find($line->product_id);
            if ($product === null) {
                throw ValidationException::withMessages([
                    'cart' => ['A product in your cart is no longer available.'],
                ]);
            }

            $qty = (int) $line->quantity;
            if ($qty < 1) {
                throw ValidationException::withMessages([
                    'cart' => ['Invalid quantity for '.$product->name.'.'],
                ]);
            }

            $available = (int) $product->quantity;
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'cart' => ['Insufficient stock for '.$product->name.'. Available: '.$available.', requested: '.$qty.'.'],
                ]);
            }

            $unit = (float) $product->price;
            $subtotal += $unit * $qty;
            $rows[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_price' => $unit,
            ];
        }

        return ['subtotal' => round($subtotal, 2), 'rows' => $rows];
    }

    private function resolveCoupon(?string $code, float $subtotal): ?Coupon
    {
        if ($code === null || $code === '') {
            Log::info('checkout.coupon', ['coupon_code' => null, 'status' => 'no_code_provided']);
            return null;
        }

        Log::info('checkout.coupon', ['coupon_code' => $code, 'subtotal' => $subtotal]);

        $coupon = Coupon::where('code', strtoupper($code))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('max_uses')
                      ->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->first();

        if ($coupon === null) {
            Log::info('checkout.coupon', ['coupon_code' => $code, 'status' => 'not_found_or_invalid']);
            throw ValidationException::withMessages([
                'coupon_code' => ['الكوبون غير صالح أو منتهي الصلاحية'],
            ]);
        }

        if ($subtotal < (float) $coupon->min_order) {
            Log::info('checkout.coupon', ['coupon_code' => $code, 'status' => 'min_order_not_met', 'subtotal' => $subtotal, 'min_order' => $coupon->min_order]);
            throw ValidationException::withMessages([
                'coupon_code' => ['الحد الأدنى للطلب هو ' . $coupon->min_order . ' جنيه'],
            ]);
        }

        Log::info('checkout.coupon', ['coupon_code' => $code, 'status' => 'valid', 'coupon_id' => $coupon->id, 'type' => $coupon->type, 'value' => $coupon->value]);

        return $coupon;
    }

    private function computeDiscount(?Coupon $coupon, float $subtotal): float
    {
        if ($coupon === null) {
            return 0.0;
        }

        return $coupon->calculateDiscount($subtotal);
    }

    /**
     * @param  array<string, mixed>  $shippingSnapshot
     * @param  array{subtotal: float, rows: list<array{product: Product, quantity: int, unit_price: float}>}  $priced
     */
    private function persistOrder(
        ?int $userId,
        ?string $guestId,
        ?string $guestName,
        ?string $guestEmail,
        ?string $guestPhone,
        ?int $addressId,
        ?int $deliveryZoneId,
        array $shippingSnapshot,
        array $priced,
        ?Coupon $coupon,
        \Closure $clearCart,
        string $paymentMethod = 'cod',
        ?string $notes = null,
    ): Order {
        $subtotal = $priced['subtotal'];
        $discountAmount = $this->computeDiscount($coupon, $subtotal);
        $deliveryPrice = (float) (AppSetting::where('key', 'delivery_price')->value('value') ?? 0);
        $total = round($subtotal - $discountAmount + $deliveryPrice, 2);

        $paymentMethod = in_array($paymentMethod, ['cod', 'none'], true) ? $paymentMethod : 'cod';
        $paymentStatus = $paymentMethod === 'cod' ? 'unpaid' : 'pending';

        // Resolve customer name / phone / address from user or guest data
        $customerName    = $guestName  ?? $userId ? \App\Models\User::find($userId)?->name : null;
        $customerPhone   = $guestPhone ?? $userId ? \App\Models\User::find($userId)?->phone : null;
        $customerAddress = collect([
            $shippingSnapshot['address_line_1'] ?? null,
            $shippingSnapshot['city'] ?? null,
        ])->filter()->implode(', ') ?: null;

        $order = Order::query()->create([
            'user_id' => $userId,
            'guest_id' => $guestId,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'address_id' => $addressId,
            'delivery_zone_id' => $deliveryZoneId,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'final_price' => $total,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'total_price' => $total,
            'delivery_price' => $deliveryPrice,
            'products_total' => $subtotal,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_address' => $customerAddress,
            'status' => 'Pending',
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'notes' => $notes,
            'shipping_address' => $shippingSnapshot,
        ]);

        Log::info('order.created', [
            'order_id' => $order->id,
            'user_id' => $userId,
            'guest_id' => $guestId,
            'payment_method' => $paymentMethod,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'delivery_price' => $deliveryPrice,
            'final_price' => $total,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
        ]);

        foreach ($priced['rows'] as $row) {
            /** @var Product $product */
            $product = $row['product'];
            $qty = $row['quantity'];
            $unit = $row['unit_price'];

            $order->orderItems()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'price' => $unit,
            ]);

            $product->decrement('quantity', $qty);
        }

        if ($coupon !== null) {
            $coupon->increment('used_count');
        }

        $clearCart();

        return $order->load(['orderItems.product']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snapshotFromInline(array $data): array
    {
        $line1 = (string) ($data['address_line'] ?? $data['address_line_1'] ?? '');
        $zip = (string) ($data['postal_code'] ?? $data['zip'] ?? '');

        return [
            'name' => $data['label'] ?? $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line_1' => $line1,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => (string) ($data['city'] ?? ''),
            'zip' => $zip,
            'country' => (string) ($data['country'] ?? 'Default Country'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromAddress(Address $address): array
    {
        return [
            'name' => $address->name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'zip' => $address->zip,
            'country' => $address->country,
        ];
    }

    private function resolveAddressText(?User $user, array $data): string
    {
        $inline = $data['shipping_address'] ?? null;
        if (is_array($inline) && $inline !== []) {
            return collect([
                $inline['address_line'] ?? $inline['address_line_1'] ?? null,
                $inline['city'] ?? null,
            ])->filter()->implode(', ');
        }

        $addressPk = $data['shipping_address_id'] ?? $data['address_id'] ?? null;
        if ($addressPk !== null && $addressPk !== '') {
            $address = Address::find((int) $addressPk);
            if ($address) {
                return collect([
                    $address->address_line_1,
                    $address->city,
                ])->filter()->implode(', ');
            }
        }

        if ($user !== null) {
            $userAddress = Address::where('user_id', $user->id)->first();
            if ($userAddress) {
                return collect([
                    $userAddress->address_line_1,
                    $userAddress->city,
                ])->filter()->implode(', ');
            }
        }

        $guestParts = [
            $data['shipping_address_line_1'] ?? null,
            $data['shipping_city'] ?? null,
        ];

        return collect($guestParts)->filter()->implode(', ');
    }

    private function resolveDeliveryZone(?User $user, array $data): ?DeliveryZone
    {
        $city = null;

        $inline = $data['shipping_address'] ?? null;
        if (is_array($inline) && $inline !== []) {
            $city = $inline['city'] ?? null;
        }

        if ($city === null) {
            $addressPk = $data['shipping_address_id'] ?? $data['address_id'] ?? null;
            if ($addressPk !== null && $addressPk !== '') {
                $address = Address::find((int) $addressPk);
                if ($address) {
                    $city = $address->city;
                }
            }
        }

        if ($city === null && $user !== null) {
            $userAddress = Address::where('user_id', $user->id)->first();
            if ($userAddress) {
                $city = $userAddress->city;
            }
        }

        if ($city === null) {
            $city = $data['shipping_city'] ?? null;
        }

        if ($city === null || trim($city) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($city));

        return DeliveryZone::where('is_active', true)
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(name) = ?', [$normalized])
                      ->orWhereRaw('LOWER(english_name) = ?', [$normalized]);
            })
            ->first();
    }
}
