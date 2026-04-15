<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Page::updateOrCreate(
            ['slug' => 'terms-and-condition'],
            [
                'title' => 'Terms and Conditions',
                'content' => '<h1>Terms and Conditions</h1><p>Welcome to our application. By using our services, you agree to these terms.</p>',
                'is_active' => true,
            ]
        );

        Page::updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'content' => '<h1>Privacy Policy</h1><p>Your privacy is important to us. We do not sell your personal data.</p>',
                'is_active' => true,
            ]
        );

        Page::updateOrCreate(
            ['slug' => 'about-us'],
            [
                'title' => 'About Us',
                'content' => '<h1>About Us</h1><p>We are a leading e-commerce platform dedicated to providing the best products.</p>',
                'is_active' => true,
            ]
        );
    }
}
