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
        $order->loadMissing(['user', 'address', 'coupon', 'orderItems.product']);

        // Resolve customer name/phone with user fallback
        $customerName  = $order->customer_name  ?: ($order->user?->name  ?? $order->guest_name  ?? null);
        $customerPhone = $order->customer_phone ?: ($order->user?->phone ?? $order->guest_phone ?? null);
        $customerAddr  = $order->customer_address ?: (
            $order->shipping_address
                ? collect([$order->shipping_address['address_line_1'] ?? null, $order->shipping_address['city'] ?? null])->filter()->implode(', ')
                : null
        );

        // Compute products_total — prefer stored value, else calculate from items
        $productsTotal = ($order->products_total !== null && (float) $order->products_total > 0)
            ? (float) $order->products_total
            : $order->orderItems->sum(fn ($i) => $i->price * $i->quantity);

        // Get delivery fee — prefer stored value, else from settings
        $deliveryPrice = ($order->delivery_price !== null && (float) $order->delivery_price > 0)
            ? (float) $order->delivery_price
            : (float) (\App\Models\AppSetting::get('delivery_price') ?? 0);

        // total_price = subtotal - discount (after coupon, before delivery)
        $totalPrice = ($order->total_price !== null && (float) $order->total_price > 0)
            ? (float) $order->total_price
            : ($productsTotal - (float) $order->discount_amount);

        // final_price = subtotal - discount + delivery (final amount customer pays)
        $finalPrice = ($order->final_price !== null && (float) $order->final_price > 0)
            ? (float) $order->final_price
            : ($totalPrice + $deliveryPrice);

        return [
            'id'                 => $order->id,
            'user_id'            => $order->user_id,
            'shipping_address_id'=> $order->address_id,
            'subtotal'           => (float) $order->subtotal,
            'products_total'     => $productsTotal,
            'delivery_price'     => $deliveryPrice,
            'discount'           => (float) $order->discount_amount,
            'discount_amount'    => (float) $order->discount_amount,
            'total_price'        => $totalPrice,
            'final_price'        => $finalPrice,
            'coupon_code'        => $order->coupon_code ?: $order->coupon?->code,
            'status'             => $order->status,
            'payment_method'     => $order->payment_method,
            'payment_status'     => $order->payment_status,
            'notes'              => $order->notes,
            'tracking_number'    => $order->tracking_number,
            'shipping_address'   => $order->shipping_address,
            'customer_name'      => $customerName,
            'customer_phone'     => $customerPhone,
            'customer_address'   => $customerAddr,
            'guest_name'         => $order->guest_name,
            'guest_phone'        => $order->guest_phone,
            'guest_email'        => $order->guest_email,
            'user'               => $order->user ? [
                'id'    => $order->user->id,
                'name'  => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
            ] : null,
            'whatsapp_sent'      => $order->whatsapp_sent ?? false,
            'whatsapp_sent_at'   => $order->whatsapp_sent_at?->toIso8601String(),
            'order_items'        => self::items($order),
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
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount_amount,
            'discount_amount' => (float) $order->discount_amount,
            'coupon_code' => $order->coupon_code,
            'total_price' => (float) $order->total_price,
            'final_price' => (float) ($order->final_price ?: round((float) $order->total_price + (float) ($order->delivery_price ?? 0), 2)),
            'order_items_count' => (int) ($order->order_items_count ?? $order->orderItems()->count()),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
