<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Banner;

class NormalizeImagePaths extends Command
{
    protected $signature = 'images:normalize';
    protected $description = 'Normalize legacy absolute image URLs to relative paths on the public disk';

    public function handle()
    {
        $this->info('Normalizing User avatars...');
        User::whereNotNull('avatar')->chunk(100, function ($users) {
            foreach ($users as $user) {
                if (!str_starts_with($user->avatar, 'http') && empty($user->avatar_path)) {
                    $user->avatar_path = 'avatars/' . basename($user->avatar);
                    $user->save();
                }
            }
        });

        $this->info('Normalizing Product images...');
        Product::whereNotNull('image')->chunk(100, function ($products) {
            foreach ($products as $product) {
                if (!str_starts_with($product->image, 'http') && empty($product->image_path)) {
                    $product->image_path = 'products/' . basename($product->image);
                    $product->save();
                }
            }
        });

        $this->info('Normalizing Banner images...');
        Banner::whereNotNull('image_url')->chunk(100, function ($banners) {
            foreach ($banners as $banner) {
                if (!str_starts_with($banner->image_url, 'http') && empty($banner->image_path)) {
                    $banner->image_path = 'banners/' . basename($banner->image_url);
                    $banner->save();
                }
            }
        });

        $this->info('Image paths normalized successfully.');
    }
}