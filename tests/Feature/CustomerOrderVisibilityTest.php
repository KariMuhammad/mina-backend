<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_admin_updates_immediately(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 15.00,
            'total_price' => 15.00,
            'status' => 'Pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'notes' => null,
            'tracking_number' => null,
            'shipping_address' => ['city' => 'Riverdale'],
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/orders/'.$order->id, [
            'status' => 'Completed',
            'payment_status' => 'paid',
            'tracking_number' => 'TRACK-999',
        ])->assertOk();

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/customer/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('order.status', 'Completed')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.tracking_number', 'TRACK-999');
    }
}
