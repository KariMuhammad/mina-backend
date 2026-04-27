<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send an order summary message to the customer via WhatsApp Cloud API.
     */
    public function sendOrder(Order $order): array
    {
        $token   = config('services.whatsapp.token');
        $phoneId = config('services.whatsapp.phone_id');

        if (! $token || ! $phoneId) {
            return [
                'success' => false,
                'message' => 'WhatsApp API credentials are not configured.',
            ];
        }

        $to = $this->normalizePhone($order->customer_phone);

        if (! $to) {
            return [
                'success' => false,
                'message' => 'Customer phone number is missing.',
            ];
        }

        $message = $this->formatOrderMessage($order);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post(
                    "https://graph.facebook.com/v19.0/{$phoneId}/messages",
                    [
                        'messaging_product' => 'whatsapp',
                        'to'                => $to,
                        'type'              => 'text',
                        'text'              => ['body' => $message],
                    ]
                );

            if ($response->successful()) {
                Log::info('whatsapp.order.sent', [
                    'order_id' => $order->id,
                    'to'       => $to,
                ]);

                return [
                    'success' => true,
                    'message' => 'WhatsApp message sent successfully.',
                ];
            }

            Log::error('whatsapp.order.failed', [
                'order_id'  => $order->id,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'WhatsApp API returned an error.',
                'status'  => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('whatsapp.order.exception', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to WhatsApp API.',
            ];
        }
    }

    /**
     * Build the Arabic order summary message.
     */
    public function formatOrderMessage(Order $order): string
    {
        $order->loadMissing('orderItems.product');

        $statusMap = Order::getStatuses();
        $statusAr  = $statusMap[$order->status] ?? $order->status;

        $items = $order->orderItems->map(function ($item) {
            $name  = $item->product->name ?? $item->product_name ?? 'منتج';
            $line  = "• {$name} ×{$item->quantity} — " . $this->formatPrice($item->price * $item->quantity);
            return $line;
        })->implode("\n");

        $productsTotal = $order->products_total !== null
            ? (float) $order->products_total
            : $order->orderItems->sum(fn ($i) => $i->price * $i->quantity);
        $deliveryPrice = $order->delivery_price !== null
            ? (float) $order->delivery_price
            : (float) (AppSetting::get('delivery_price') ?? 0);
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $finalPrice = ($order->final_price !== null && (float) $order->final_price > 0)
            ? (float) $order->final_price
            : ($productsTotal - $discountAmount + $deliveryPrice);

        $customerName  = $order->customer_name  ?: ($order->user?->name  ?? $order->guest_name  ?? '—');
        $customerPhone = $order->customer_phone ?: ($order->user?->phone ?? $order->guest_phone ?? '—');
        $customerAddr  = $order->customer_address ?: (
            $order->shipping_address
                ? collect([$order->shipping_address['address_line_1'] ?? null, $order->shipping_address['city'] ?? null])->filter()->implode(', ')
                : '—'
        );

        $discountLines = '';
        $afterDiscountLine = '';
        if ($discountAmount > 0) {
            $couponCode = $order->coupon_code ?? '';
            $totalPrice = ($order->total_price !== null && (float) $order->total_price > 0)
                ? (float) $order->total_price
                : ($productsTotal - $discountAmount);
            $discountLines = "🏷️ *الخصم:* -{$this->formatPrice($discountAmount)} ({$couponCode})\n";
            $afterDiscountLine = "� *السعر بعد الخصم:* {$this->formatPrice($totalPrice)}\n";
        }

        $message = <<<TEXT
🛒 *طلب جديد #{$order->id}*

👤 *الاسم:* {$customerName}
📱 *الجوال:* {$customerPhone}
📍 *العنوان:* {$customerAddr}

📦 *المنتجات:*
{$items}

💰 *إجمالي المنتجات:* {$this->formatPrice($productsTotal)}
🚚 *سعر التوصيل:* {$this->formatPrice($deliveryPrice)}
{$discountLines}{$afterDiscountLine}✅ *السعر الكلي:* {$this->formatPrice($finalPrice)}

📋 *حالة الطلب:* {$statusAr}
TEXT;

        return $message;
    }

    /**
     * Format a price with the configured currency symbol.
     */
    private function formatPrice(float $amount): string
    {
        $symbol   = AppSetting::get('currency_symbol') ?: 'ل.س';
        $position = AppSetting::get('currency_position') ?: 'after';
        $formatted = number_format($amount, 0, '.', ',');

        return $position === 'before'
            ? "{$symbol} {$formatted}"
            : "{$formatted} {$symbol}";
    }

    /**
     * Normalize a phone number for the WhatsApp API (international format without +).
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Remove spaces, dashes, parentheses, plus sign
        $clean = preg_replace('/[\s\-\(\)\+]/', '', $phone);

        // If it starts with 0, replace with country code (default Syria 963)
        if (str_starts_with($clean, '0')) {
            $clean = '963' . substr($clean, 1);
        }

        return $clean ?: null;
    }
}
