<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerFlowApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduct(float $price = 10.0): Product
    {
        $category = Category::query()->create(['name' => 'C', 'image' => null]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Item',
            'price' => $price,
            'quantity' => 50,
            'image' => null,
            'description' => 'd',
        ]);
    }

    public function test_cart_summary_requires_authentication(): void
    {
        $this->getJson('/api/v1/customer/cart/summary')->assertStatus(401);
    }

    public function test_cart_summary_returns_subtotal_and_items(): void
    {
        $user = User::factory()->create();
        $p = $this->seedProduct(5.0);
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $p->id, 'quantity' => 2]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/customer/cart/summary')
            ->assertOk()
            ->assertJsonPath('subtotal', 10)
            ->assertJsonCount(1, 'items');
    }

    public function test_address_crud_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/customer/addresses', [
            'label' => 'Home',
            'phone' => '555',
            'address_line' => '10 Oak St',
            'city' => 'Cairo',
            'postal_code' => '12345',
            'country' => 'EG',
            'is_default' => true,
        ]);

        $create->assertCreated();
        $id = $create->json('address.id');
        $this->assertNotNull($id);

        $this->getJson('/api/v1/customer/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson('/api/v1/customer/addresses/'.$id, [
            'city' => 'Alex',
        ])->assertOk()
            ->assertJsonPath('address.city', 'Alex');

        $this->deleteJson('/api/v1/customer/addresses/'.$id)
            ->assertOk();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_address_update_forbidden_for_other_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $addr = Address::query()->create([
            'user_id' => $a->id,
            'name' => 'X',
            'phone' => '1',
            'address_line_1' => 'L',
            'city' => 'C',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        Sanctum::actingAs($b);

        $this->putJson('/api/v1/customer/addresses/'.$addr->id, ['city' => 'Y'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Access denied! This resource does not belong to you.']);
    }

    public function test_orders_list_only_returns_current_user_orders(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Order::query()->create([
            'user_id' => $u1->id,
            'subtotal' => 10,
            'discount_amount' => 0,
            'total_price' => 10,
            'status' => 'Pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
        ]);

        Order::query()->create([
            'user_id' => $u2->id,
            'subtotal' => 20,
            'discount_amount' => 0,
            'total_price' => 20,
            'status' => 'Pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
        ]);

        Sanctum::actingAs($u1);

        $this->getJson('/api/v1/customer/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_order_show_owner_ok_non_owner_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $owner->id,
            'subtotal' => 10,
            'discount_amount' => 0,
            'total_price' => 10,
            'status' => 'Pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->seedProduct()->id,
            'quantity' => 1,
            'price' => 10,
            'product_name' => 'Item',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/customer/orders/'.$order->id)
            ->assertOk()
            ->assertJsonStructure(['order', 'items']);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/customer/orders/'.$order->id)
            ->assertStatus(403)
            ->assertJson(['message' => 'Access denied! This resource does not belong to you.']);
    }

    public function test_order_show_missing_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/customer/orders/99999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_checkout_with_unknown_address_id_returns_404(): void
    {
        $user = User::factory()->create();
        $p = $this->seedProduct();
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $p->id, 'quantity' => 1]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/checkout', [
            'shipping_address_id' => 99999,
            'payment_method' => 'cod',
        ])
            ->assertStatus(404)
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_checkout_invalid_payment_method_returns_422(): void
    {
        $user = User::factory()->create();
        $addr = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'H',
            'phone' => '1',
            'address_line_1' => 'L',
            'city' => 'C',
            'zip' => 'Z',
            'country' => 'US',
        ]);
        $p = $this->seedProduct();
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $p->id, 'quantity' => 1]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/checkout', [
            'shipping_address_id' => $addr->id,
            'payment_method' => 'stripe',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_v1_auth_logout_alias_works(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);
    }
}
