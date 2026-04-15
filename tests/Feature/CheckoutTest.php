<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\GuestCartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduct(int $stock = 100, float $price = 10.0): Product
    {
        $category = Category::query()->create(['name' => 'Cat '.uniqid(), 'image' => null]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Widget',
            'price' => $price,
            'quantity' => $stock,
            'image' => null,
            'description' => 'Desc',
        ]);
    }

    private function checkoutPayload(int $addressId, array $extra = []): array
    {
        return array_merge([
            'shipping_address_id' => $addressId,
            'payment_method' => 'cod',
        ], $extra);
    }

    public function test_invalid_bearer_token_returns_401_before_guest_validation(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/v1/customer/checkout', [
                'shipping_address_id' => 1,
                'payment_method' => 'cod',
            ])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_checkout_fails_when_cart_is_empty(): void
    {
        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'Home',
            'phone' => '555',
            'address_line_1' => '1 Main',
            'city' => 'City',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/checkout', $this->checkoutPayload($address->id))
            ->assertStatus(400)
            ->assertJson(['message' => 'Your cart is empty.']);
    }

    public function test_authenticated_checkout_succeeds_and_clears_cart(): void
    {
        $product = $this->seedProduct(stock: 10, price: 5.0);
        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'Home',
            'phone' => '555',
            'address_line_1' => '1 Main',
            'city' => 'City',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/customer/checkout', $this->checkoutPayload($address->id, [
            'notes' => 'Please call on arrival',
        ]));

        $response->assertCreated()
            ->assertJsonPath('message', 'Order created successfully!')
            ->assertJsonPath('order.final_price', 10)
            ->assertJsonPath('order.subtotal', 10)
            ->assertJsonPath('order.total_price', 10)
            ->assertJsonPath('order.discount', 0)
            ->assertJsonPath('order.discount_amount', 0)
            ->assertJsonPath('order.status', 'Pending')
            ->assertJsonPath('order.payment_status', 'unpaid')
            ->assertJsonPath('order.payment_method', 'cod')
            ->assertJsonPath('order.notes', 'Please call on arrival');

        $this->assertCount(1, $response->json('items'));

        $this->assertDatabaseCount('cart_items', 0);
        $product->refresh();
        $this->assertSame(8, (int) $product->quantity);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_authenticated_checkout_with_multiple_items(): void
    {
        $p1 = $this->seedProduct(stock: 20, price: 4.0);
        $p2 = $this->seedProduct(stock: 20, price: 6.0);
        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'Home',
            'phone' => '555',
            'address_line_1' => '1 Main',
            'city' => 'City',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $p1->id, 'quantity' => 1]);
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $p2->id, 'quantity' => 2]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/checkout', $this->checkoutPayload($address->id))
            ->assertCreated()
            ->assertJsonPath('order.subtotal', 16)
            ->assertJsonPath('order.final_price', 16);

        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_checkout_fails_when_stock_insufficient(): void
    {
        $product = $this->seedProduct(stock: 1, price: 5.0);
        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'Home',
            'phone' => '555',
            'address_line_1' => '1 Main',
            'city' => 'City',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/checkout', $this->checkoutPayload($address->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_checkout_rejects_address_not_owned_by_user(): void
    {
        $product = $this->seedProduct();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $owner->id,
            'name' => 'Home',
            'phone' => '555',
            'address_line_1' => '1 Main',
            'city' => 'City',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        CartItem::query()->create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($other);

        $this->postJson('/api/v1/customer/checkout', $this->checkoutPayload($address->id))
            ->assertStatus(403)
            ->assertJson(['message' => 'Access denied! This resource does not belong to you.']);
    }

    public function test_guest_checkout_succeeds_and_clears_guest_cart(): void
    {
        $product = $this->seedProduct(stock: 5, price: 10.0);
        $guestId = 'guest_xyz';

        GuestCartItem::query()->create([
            'guest_id' => $guestId,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->postJson('/api/checkout?guest_id='.$guestId, [
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '999',
            'shipping_address_line_1' => '9 Road',
            'shipping_city' => 'Town',
            'shipping_zip' => '11111',
        ])
            ->assertCreated()
            ->assertJsonPath('order.guest_email', 'guest@example.com')
            ->assertJsonPath('order.user_id', null);

        $this->assertDatabaseMissing('guest_cart_items', ['guest_id' => $guestId]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_coupon_percent_applies(): void
    {
        $product = $this->seedProduct(stock: 10, price: 100.0);
        Coupon::query()->create([
            'code' => 'SAVE10',
            'discount_amount' => 0,
            'discount_percent' => 10,
            'minimum_order' => 0,
            'valid_until' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'Home',
            'phone' => '555',
            'address_line_1' => '1 Main',
            'city' => 'City',
            'zip' => 'Z',
            'country' => 'US',
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/checkout', $this->checkoutPayload($address->id, [
            'coupon_code' => 'save10',
        ]))
            ->assertCreated()
            ->assertJsonPath('order.subtotal', 100)
            ->assertJsonPath('order.discount', 10)
            ->assertJsonPath('order.discount_amount', 10)
            ->assertJsonPath('order.final_price', 90)
            ->assertJsonPath('order.coupon_code', 'SAVE10');
    }
}
