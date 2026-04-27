<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Support\CustomerOrderFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->orders()->with('orderItems')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'order_amount'        => 'nullable|numeric',
            'payment_method'      => 'nullable|string',
            'coupon_code'         => 'nullable|string',
            'order_note'          => 'nullable|string',
            'address_id'          => 'nullable|exists:addresses,id',
            'cart'                => 'nullable|string',
            'items'               => 'nullable|array',
            'items.*.product_id'  => 'nullable|exists:products,id',
            'items.*.quantity'   => 'nullable|integer',
            'items.*.price'       => 'nullable|numeric',
        ]);

        // --- 1. Calculate subtotal from user's cart ---
        $cartLines = $user->cartItems()->with('product')->get();

        if ($cartLines->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 400);
        }

        $subtotal = 0.0;
        $rows = [];
        foreach ($cartLines as $line) {
            $unit = (float) ($line->product?->price ?? 0);
            $qty  = (int) $line->quantity;
            $subtotal += $unit * $qty;
            $rows[] = [
                'product'    => $line->product,
                'product_id' => $line->product_id,
                'quantity'   => $qty,
                'unit_price' => $unit,
            ];
        }
        $subtotal = round($subtotal, 2);

        // --- 2. Resolve coupon ---
        $couponCode = $request->input('coupon_code');
        $coupon = null;
        $discountAmount = 0.0;

        if ($couponCode) {
            $coupon = Coupon::where('code', strtoupper($couponCode))
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->where(function ($q) {
                    $q->whereNull('max_uses')
                      ->orWhereColumn('used_count', '<', 'max_uses');
                })
                ->first();

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'كود الخصم غير صالح أو منتهي الصلاحية',
                ], 422);
            }

            if ($subtotal < (float) $coupon->min_order) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحد الأدنى للطلب لتفعيل هذا الكوبون هو ' . $coupon->min_order . ' ل.س',
                ], 422);
            }

            $discountAmount = $coupon->calculateDiscount($subtotal);
        }

        Log::info('ORDER_PLACE_COUPON', [
            'coupon_code' => $couponCode,
            'coupon_id'   => $coupon?->id,
            'discount'    => $discountAmount,
            'subtotal'    => $subtotal,
        ]);

        // --- 3. Delivery fee & totals ---
        $addressId = $request->input('address_id');
        $address = null;

        if ($addressId) {
            $address = Address::where('id', $addressId)
                ->where('user_id', $user->id)
                ->first();
        }

        // Fallback: if no address_id, try to resolve city from raw address string
        if ($address) {
            $cityInput = mb_strtolower(trim($address->city));
            $customerAddress = $address->address_line_1 . '، ' . $address->city;
        } else {
            $rawAddress = $request->input('address') ?? '';
            $cityInput = mb_strtolower(trim($rawAddress));
            $customerAddress = $rawAddress;
        }

        $deliveryZone = DeliveryZone::where('is_active', true)
            ->where(function ($q) use ($cityInput) {
                $q->whereRaw('LOWER(TRIM(name)) LIKE ?', ["%{$cityInput}%"])
                  ->orWhereRaw('LOWER(TRIM(english_name)) LIKE ?', ["%{$cityInput}%"]);
            })
            ->first();

        if (!$deliveryZone) {
            return response()->json([
                'success' => false,
                'message' => 'التوصيل غير متاح في هذه المنطقة حاليًا',
            ], 422);
        }

        $deliveryPrice = (float) $deliveryZone->delivery_fee;
        $finalPrice = round($subtotal - $discountAmount + $deliveryPrice, 2);

        $paymentMethod = $request->input('payment_method', 'cod');
        $paymentMethod = in_array($paymentMethod, ['cod', 'none'], true) ? $paymentMethod : 'cod';
        $paymentStatus = $paymentMethod === 'cod' ? 'unpaid' : 'pending';

        $customerName  = $request->input('contact_person_name')  ?? $user->name;
        $customerPhone = $request->input('contact_person_number') ?? $user->phone;

        // --- 4. Create order with coupon fields ---
        $order = DB::transaction(function () use (
            $user, $subtotal, $discountAmount, $deliveryPrice, $finalPrice,
            $coupon, $paymentMethod, $paymentStatus, $customerName, $customerPhone,
            $rows, $request, $address, $customerAddress, $deliveryZone
        ) {
            $order = Order::query()->create([
                'user_id'          => $user->id,
                'address_id'       => $address?->id,
                'delivery_zone_id' => $deliveryZone->id,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discountAmount,
                'final_price'      => $finalPrice,
                'coupon_id'        => $coupon?->id,
                'coupon_code'      => $coupon?->code,
                'total_price'      => $finalPrice,
                'delivery_price'   => $deliveryPrice,
                'products_total'   => $subtotal,
                'customer_name'    => $customerName,
                'customer_phone'   => $customerPhone,
                'customer_address' => $customerAddress,
                'status'           => 'Pending',
                'payment_status'   => $paymentStatus,
                'payment_method'   => $paymentMethod,
                'notes'            => $request->input('order_note'),
            ]);

            foreach ($rows as $row) {
                $order->orderItems()->create([
                    'product_id'   => $row['product_id'],
                    'product_name' => $row['product']?->name,
                    'quantity'      => $row['quantity'],
                    'price'         => $row['unit_price'],
                ]);

                if ($row['product']) {
                    $row['product']->decrement('quantity', $row['quantity']);
                }
            }

            // Increment coupon usage
            if ($coupon !== null) {
                $coupon->increment('used_count');
            }

            // Clear user's cart
            $user->cartItems()->delete();

            return $order;
        });

        Log::info('ORDER_CREATE_DATA', [
            'order_id'       => $order->id,
            'coupon_id'      => $coupon?->id,
            'discount_amount' => $discountAmount,
            'final_price'    => $finalPrice,
            'coupon_code'    => $coupon?->code,
        ]);

        return response()->json([
            'message' => 'Order created successfully',
            'order'   => CustomerOrderFormatter::order($order->load('orderItems.product')),
            'items'   => CustomerOrderFormatter::items($order),
        ], 201);
    }

    public function track(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'phone' => 'required|string',
        ]);

        $order = Order::where('id', $request->order_id)
            ->whereHas('user', function ($query) use ($request) {
                $query->where('phone', $request->phone);
            })
            ->with('orderItems.product')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }
}
