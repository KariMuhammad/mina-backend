<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

class CustomerOrderFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        $order->loadMissing(['address', 'orderItems.product']);

        return [
            'id'                 => $order->id,
            'user_id'            => $order->user_id,
            'shipping_address_id'=> $order->address_id,
            'subtotal'           => (float) $order->subtotal,
            'total_price'        => (float) $order->subtotal,
            'discount'           => (float) $order->discount_amount,
            'discount_amount'    => (float) $order->discount_amount,
            'final_price'        => (float) $order->total_price,
            'coupon_code'        => $order->coupon_code,
            'status'             => $order->status,
            'payment_method'     => $order->payment_method,
            'payment_status'     => $order->payment_status,
            'notes'              => $order->notes,
            'tracking_number'    => $order->tracking_number,
            'shipping_address'   => $order->shipping_address,
            'created_at'         => $order->created_at?->toIso8601String(),
            'updated_at'         => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function items(Order $order): array
    {
        $order->loadMissing('orderItems.product');

        return $order->orderItems->map(fn (OrderItem $i) => self::item($i))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(OrderItem $i): array
    {
        return [
            'id' => $i->id,
            'order_id' => $i->order_id,
            'product_id' => $i->product_id,
            'product_name' => $i->product_name,
            'price' => (float) $i->price,
            'quantity' => (int) $i->quantity,
            'created_at' => $i->created_at?->toIso8601String(),
            'updated_at' => $i->updated_at?->toIso8601String(),
            'product' => $i->relationLoaded('product') && $i->product ? [
                'id' => $i->product->id,
                'name' => $i->product->name,
                'price' => (float) $i->product->price,
                'image' => $i->product->image,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'final_price' => (float) $order->total_price,
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount_amount,
            'order_items_count' => (int) ($order->order_items_count ?? $order->orderItems()->count()),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
