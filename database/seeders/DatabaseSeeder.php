<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed default user
        if (User::where('email', 'test@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '1234567890',
                'password' => bcrypt('password'),
                'role' => 'user',
            ]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'phone' => 'admin0000000001',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );
        if ($admin->role !== 'admin') {
            $admin->role = 'admin';
            $admin->save();
        }

        // Seed Categories
        $categories = ['Burger', 'Pizza', 'Drinks', 'Desserts'];
        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat]
            );
        }

        // Seed Banners
        $banners = [
            ['title' => 'Big Sale 50% Off', 'image_url' => 'https://placehold.co/600x400/png?text=Big+Sale'],
            ['title' => 'New Arrivals', 'image_url' => 'https://placehold.co/600x400/png?text=New+Arrivals'],
            ['title' => 'Free Shipping', 'image_url' => 'https://placehold.co/600x400/png?text=Free+Shipping'],
        ];

        foreach ($banners as $banner) {
            Banner::firstOrCreate(
                ['title' => $banner['title']],
                ['image_url' => $banner['image_url'], 'is_active' => true]
            );
        }

        // Seed Products
        $firstCategory = Category::first();
        if ($firstCategory) {
            $products = [
                ['name' => 'Classic Burger', 'price' => 50, 'discount_percent' => 10, 'is_popular' => true, 'image' => 'https://placehold.co/300x300/png?text=Burger'],
                ['name' => 'Pepperoni Pizza', 'price' => 120, 'discount_percent' => 0, 'is_popular' => true, 'image' => 'https://placehold.co/300x300/png?text=Pizza'],
                ['name' => 'Coca Cola', 'price' => 15, 'discount_percent' => 0, 'is_popular' => true, 'image' => 'https://placehold.co/300x300/png?text=Cola'],
                ['name' => 'Chocolate Cake', 'price' => 45, 'discount_percent' => 5, 'is_popular' => true, 'image' => 'https://placehold.co/300x300/png?text=Cake'],
                ['name' => 'Fries', 'price' => 30, 'discount_percent' => 0, 'is_popular' => false, 'image' => 'https://placehold.co/300x300/png?text=Fries'],
                ['name' => 'Ice Cream', 'price' => 40, 'discount_percent' => 0, 'is_popular' => true, 'image' => 'https://placehold.co/300x300/png?text=Ice+Cream'],
            ];

            foreach ($products as $prod) {
                Product::firstOrCreate(
                    ['name' => $prod['name']],
                    [
                        'category_id' => $firstCategory->id,
                        'price' => $prod['price'],
                        'quantity' => 100,
                        'discount_percent' => $prod['discount_percent'],
                        'is_popular' => $prod['is_popular'],
                        'image' => $prod['image'],
                        'description' => 'A delicious ' . $prod['name'],
                        'rating' => 4.5,
                    ]
                );
            }
        }
        
        $this->call([
            PageSeeder::class,
        ]);
    }
}
