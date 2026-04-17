<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->orders()->with('orderItems')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'total_price' => 'required|numeric',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer',
            'items.*.price' => 'required|numeric',
        ]);

        $order = $request->user()->orders()->create([
            'subtotal' => $request->total_price,
            'total_price' => $request->total_price,
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
