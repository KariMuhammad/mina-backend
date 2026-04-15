<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminOrderUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private AdminOrderUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminOrderUpdateService();
    }

    private function makeOrder(User $user, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id'         => $user->id,
            'subtotal'        => 100.00,
            'total_price'     => 100.00,
            'status'          => 'Pending',
            'payment_status'  => 'unpaid',
            'payment_method'  => 'cod',
            'notes'           => null,
            'tracking_number' => null,
            'shipping_address'=> null,
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // Test: correct old/new values are stored in history
    // -----------------------------------------------------------------------

    public function test_order_history_records_correct_old_and_new_values(): void
    {
        $admin    = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order    = $this->makeOrder($customer);

        $result = $this->service->update($order, [
            'status'          => 'Completed',
            'payment_status'  => 'paid',
            'tracking_number' => 'TRK-UNIT-001',
        ], $admin->id);

        $history = $result['history'];

        $this->assertCount(3, $history);

        $byField = array_column($history, null, 'field');

        $this->assertSame('Pending',      $byField['status']['old_value']);
        $this->assertSame('Completed',    $byField['status']['new_value']);
        $this->assertSame((string) $admin->id, (string) $byField['status']['admin_id']);

        $this->assertSame('unpaid',       $byField['payment_status']['old_value']);
        $this->assertSame('paid',         $byField['payment_status']['new_value']);

        $this->assertNull($byField['tracking_number']['old_value']);
        $this->assertSame('TRK-UNIT-001', $byField['tracking_number']['new_value']);
    }

    // -----------------------------------------------------------------------
    // Test: admin_id is stored correctly
    // -----------------------------------------------------------------------

    public function test_order_history_stores_admin_id(): void
    {
        $admin    = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order    = $this->makeOrder($customer);

        $this->service->update($order, ['status' => 'Processing'], $admin->id);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'field'    => 'status',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: no history rows when nothing changes
    // -----------------------------------------------------------------------

    public function test_no_history_rows_when_nothing_changed(): void
    {
        $admin    = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order    = $this->makeOrder($customer, ['status' => 'Pending']);

        $result = $this->service->update($order, ['status' => 'Pending'], $admin->id);

        $this->assertSame([], $result['history']);
        $this->assertDatabaseCount('order_histories', 0);
    }

    // -----------------------------------------------------------------------
    // Test: admin note is stored on each history row
    // -----------------------------------------------------------------------

    public function test_admin_note_stored_on_history_rows(): void
    {
        $admin    = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order    = $this->makeOrder($customer);

        $note = 'Investigated manually — marking paid';

        $this->service->update($order, ['payment_status' => 'paid'], $admin->id, $note);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'field'    => 'payment_status',
            'notes'    => $note,
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: notes change creates history row and persists on order
    // -----------------------------------------------------------------------

    public function test_notes_change_creates_history_and_persists(): void
    {
        $admin    = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order    = $this->makeOrder($customer, ['notes' => null]);

        $result = $this->service->update($order, ['notes' => 'Verified by admin'], $admin->id);

        $this->assertNotEmpty($result['history']);
        $byField = array_column($result['history'], null, 'field');
        $this->assertArrayHasKey('notes', $byField);
        $this->assertNull($byField['notes']['old_value']);
        $this->assertSame('Verified by admin', $byField['notes']['new_value']);

        // notes persisted on the order model
        $this->assertSame('Verified by admin', $order->notes);
        $this->assertDatabaseHas('orders', [
            'id'    => $order->id,
            'notes' => 'Verified by admin',
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: payment_method update creates history row
    // -----------------------------------------------------------------------

    public function test_payment_method_change_creates_history(): void
    {
        $admin    = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order    = $this->makeOrder($customer, ['payment_method' => 'cod']);

        $this->service->update($order, ['payment_method' => 'bank_transfer'], $admin->id);

        $this->assertDatabaseHas('order_histories', [
            'order_id'  => $order->id,
            'field'     => 'payment_method',
            'old_value' => 'cod',
            'new_value' => 'bank_transfer',
        ]);
    }
}
