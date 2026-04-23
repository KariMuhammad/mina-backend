<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->orders()->with('orderItems')->get());
    }

    public function store(Request $request)
    {
        $address = $request->input('address') ?? $request->user()->address ?? '';

        if (! app(DeliveryZoneService::class)->isAddressAllowed($address)) {
            return response()->json([
                'success' => false,
                'message' => 'التوصيل غير متاح في هذه المنطقة حاليًا',
            ], 422);
        }

        $request->validate([
            'total_price' => 'required|numeric',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer',
            'items.*.price' => 'required|numeric',
        ]);

        $deliveryPrice = (float) (\App\Models\AppSetting::where('key', 'delivery_price')->value('value') ?? 0);

        $order = $request->user()->orders()->create([
            'subtotal' => $request->total_price,
            'total_price' => $request->total_price + $deliveryPrice,
            'delivery_price' => $deliveryPrice,
            'status' => 'Pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
        ]);

        foreach ($request->items as $item) {
            $order->orderItems()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->load('orderItems'),
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
