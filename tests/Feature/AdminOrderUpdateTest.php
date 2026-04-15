<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderUpdateTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeOrder(User $customer, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id'         => $customer->id,
            'subtotal'        => 50.00,
            'total_price'     => 50.00,
            'status'          => 'Pending',
            'payment_status'  => 'unpaid',
            'payment_method'  => 'cod',
            'notes'           => null,
            'tracking_number' => null,
            'shipping_address'=> ['city' => 'TestCity'],
        ], $overrides));
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeCustomer(): User
    {
        return User::factory()->create();
    }

    private function seedProduct(): Product
    {
        $cat = Category::query()->create(['name' => 'Cat '.uniqid(), 'image' => null]);
        return Product::query()->create([
            'category_id' => $cat->id,
            'name'        => 'Widget',
            'price'       => 10.00,
            'quantity'    => 100,
            'image'       => null,
            'description' => 'A widget',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: full update
    // -----------------------------------------------------------------------

    public function test_admin_can_update_order_all_fields(): void
    {
        $customer = $this->makeCustomer();
        $admin    = $this->makeAdmin();
        $order    = $this->makeOrder($customer);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/orders/{$order->id}", [
            'status'          => 'Processing',
            'payment_status'  => 'paid',
            'payment_method'  => 'card',
            'tracking_number' => 'TRK-123456',
            'notes'           => 'Payment received offline',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Order updated successfully.')
            ->assertJsonPath('order.status', 'Processing')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_method', 'card')
            ->assertJsonPath('order.tracking_number', 'TRK-123456');

        // History array must contain entries for changed fields
        $history = $response->json('history');
        $this->assertNotEmpty($history);

        $fields = array_column($history, 'field');
        $this->assertContains('status', $fields);
        $this->assertContains('payment_status', $fields);
        $this->assertContains('payment_method', $fields);
        $this->assertContains('tracking_number', $fields);

        // DB assertions
        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'status'         => 'Processing',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'tracking_number'=> 'TRK-123456',
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'field'    => 'status',
            'old_value'=> 'Pending',
            'new_value'=> 'Processing',
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'field'    => 'payment_status',
            'old_value'=> 'unpaid',
            'new_value'=> 'paid',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: mark order paid
    // -----------------------------------------------------------------------

    public function test_admin_mark_order_paid(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}/payment", [
            'payment_status' => 'paid',
        ])->assertOk()
          ->assertJsonPath('order.payment_status', 'paid');

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: customer sees admin updates via customer endpoint
    // -----------------------------------------------------------------------

    public function test_customer_sees_admin_updates(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        // Admin updates
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status'          => 'Completed',
            'payment_status'  => 'paid',
            'tracking_number' => 'TRACK-999',
        ])->assertOk();

        // Customer fetches their order and sees updated values
        Sanctum::actingAs($customer);
        $this->getJson("/api/v1/customer/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.status', 'Completed')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.tracking_number', 'TRACK-999');
    }

    // -----------------------------------------------------------------------
    // Test: non-admin cannot update (403)
    // -----------------------------------------------------------------------

    public function test_non_admin_cannot_update(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        Sanctum::actingAs($customer); // Regular user — not admin

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Completed',
        ])->assertForbidden();

        // DB must not have changed
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'Pending',
        ]);

        $this->assertDatabaseCount('order_histories', 0);
    }

    // -----------------------------------------------------------------------
    // Test: unauthenticated request returns 401
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_update(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Completed',
        ])->assertUnauthorized();
    }

    // -----------------------------------------------------------------------
    // Test: invalid status returns 422
    // -----------------------------------------------------------------------

    public function test_invalid_status_returns_422(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'flying',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['status']);
    }

    // -----------------------------------------------------------------------
    // Test: status-only route works
    // -----------------------------------------------------------------------

    public function test_admin_status_only_route(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'Cancelled',
        ])->assertOk()
          ->assertJsonPath('order.status', 'Cancelled');

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'Cancelled',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: no-op update (same values) returns empty history
    // -----------------------------------------------------------------------

    public function test_no_op_update_returns_empty_history(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer, ['status' => 'Pending', 'payment_status' => 'unpaid']);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/orders/{$order->id}", [
            'status'         => 'Pending',   // same
            'payment_status' => 'unpaid',    // same
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('history'));
        $this->assertDatabaseCount('order_histories', 0);
    }

    // -----------------------------------------------------------------------
    // Test: notes field is persisted to order and recorded in history
    // -----------------------------------------------------------------------

    public function test_notes_field_is_persisted_and_audited(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer, ['notes' => null]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'notes' => 'Payment confirmed by phone',
        ])->assertOk()
          ->assertJsonPath('order.notes', 'Payment confirmed by phone');

        // DB: notes persisted on order
        $this->assertDatabaseHas('orders', [
            'id'    => $order->id,
            'notes' => 'Payment confirmed by phone',
        ]);

        // History row for notes change
        $this->assertDatabaseHas('order_histories', [
            'order_id'  => $order->id,
            'field'     => 'notes',
            'old_value' => null,
            'new_value' => 'Payment confirmed by phone',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: notes update replaces previous notes value
    // -----------------------------------------------------------------------

    public function test_notes_update_replaces_previous(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer, ['notes' => 'Initial note']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'notes' => 'Updated note',
        ])->assertOk()
          ->assertJsonPath('order.notes', 'Updated note');

        $this->assertDatabaseHas('order_histories', [
            'order_id'  => $order->id,
            'field'     => 'notes',
            'old_value' => 'Initial note',
            'new_value' => 'Updated note',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: legacy 'pending' payment_status maps to 'unpaid'
    // -----------------------------------------------------------------------

    public function test_legacy_pending_maps_to_unpaid(): void
    {
        $admin    = $this->makeAdmin();
        $customer = $this->makeCustomer();
        // Start with paid so legacy 'pending' will actually change something
        $order    = $this->makeOrder($customer, ['payment_status' => 'paid']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}/payment", [
            'payment_status' => 'pending',   // legacy alias
        ])->assertOk()
          ->assertJsonPath('order.payment_status', 'unpaid');

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'payment_status' => 'unpaid',
        ]);
    }
}
