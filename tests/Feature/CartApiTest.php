<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_to_cart_using_real_bearer_token_header(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'image' => null]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 9.99,
            'image' => null,
            'description' => 'Desc',
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cart/add', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Added to cart successfully.']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_guest_can_add_and_fetch_cart_items_using_guest_id(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'image' => null]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 9.99,
            'image' => null,
            'description' => 'Desc',
        ]);

        $guestId = 'guest_123';

        $this->postJson("/api/cart/add?guest_id={$guestId}", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->getJson("/api/cart?guest_id={$guestId}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_authenticated_user_can_add_and_fetch_cart_items(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'image' => null]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 9.99,
            'image' => null,
            'description' => 'Desc',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_token_expiry_or_invalid_token_returns_unauthenticated_for_protected_user_endpoint(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_invalid_bearer_token_returns_401_on_cart_endpoints_when_guest_id_not_provided(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/cart/add', [
                'product_id' => 1,
                'quantity' => 1,
            ])
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/cart')
            ->assertStatus(401);
    }

    public function test_v1_cart_endpoints_work_for_guest(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'image' => null]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 9.99,
            'image' => null,
            'description' => 'Desc',
        ]);

        $guestId = 'guest_abc';

        $this->postJson("/api/v1/customer/cart/add?guest_id={$guestId}", [
            'item_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->getJson("/api/v1/customer/cart/list?guest_id={$guestId}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['item_id' => $product->id, 'quantity' => 1, 'is_guest' => true]);
    }

    public function test_v1_remove_item_returns_404_when_cart_id_does_not_exist(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/customer/cart/remove-item', [
                'cart_id' => 99999,
            ])
            ->assertStatus(404)
            ->assertJson(['message' => 'Cart item not found!']);
    }

    public function test_v1_remove_item_returns_403_when_cart_belongs_to_another_user(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'image' => null]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 9.99,
            'image' => null,
            'description' => 'Desc',
        ]);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $cartItem = CartItem::query()->create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $token = $other->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/customer/cart/remove-item', [
                'cart_id' => $cartItem->id,
                'product_id' => $product->id,
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Access denied! This cart item does not belong to you.']);
    }

    public function test_v1_remove_item_succeeds_for_owner(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'image' => null]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 9.99,
            'image' => null,
            'description' => 'Desc',
        ]);

        $user = User::factory()->create();
        $cartItem = CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/customer/cart/remove-item', [
                'cart_id' => $cartItem->id,
                'product_id' => $product->id,
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Cart item removed successfully.']);

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }
}

