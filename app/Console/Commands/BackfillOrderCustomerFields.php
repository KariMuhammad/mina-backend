<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class BackfillOrderCustomerFields extends Command
{
    protected $signature = 'orders:backfill-customer';
    protected $description = 'Backfill customer_name, customer_phone, customer_address on existing orders from user/guest/shipping_address data';

    public function handle(): int
    {
        $orders = Order::query()
            ->with('user')
            ->whereNull('customer_name')
            ->orWhereNull('customer_phone')
            ->get();

        $this->info("Found {$orders->count()} orders to backfill.");

        $updated = 0;
        foreach ($orders as $order) {
            $name  = $order->customer_name  ?: ($order->user?->name  ?? $order->guest_name  ?? null);
            $phone = $order->customer_phone ?: ($order->user?->phone ?? $order->guest_phone ?? null);
            $addr  = $order->customer_address ?: (
                $order->shipping_address
                    ? collect([$order->shipping_address['address_line_1'] ?? null, $order->shipping_address['city'] ?? null])->filter()->implode(', ')
                    : null
            );

            $order->customer_name    = $name;
            $order->customer_phone   = $phone;
            $order->customer_address = $addr;
            $order->save();
            $updated++;
        }

        $this->info("Backfilled {$updated} orders.");

        return self::SUCCESS;
    }
}
