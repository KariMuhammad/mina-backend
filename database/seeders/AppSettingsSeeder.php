<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    private const DEFAULTS = [
        [
            'key'   => 'support_phone',
            'value' => '+1234567890',
            'label' => 'Support Phone Number',
            'type'  => 'phone',
        ],
        [
            'key'   => 'whatsapp',
            'value' => '+1234567890',
            'label' => 'WhatsApp Number',
            'type'  => 'phone',
        ],
        [
            'key'   => 'contact_email',
            'value' => 'support@example.com',
            'label' => 'Contact Email',
            'type'  => 'email',
        ],
        [
            'key'   => 'terms_conditions',
            'value' => '<p>Terms &amp; Conditions content goes here.</p>',
            'label' => 'Terms & Conditions',
            'type'  => 'richtext',
        ],
        [
            'key'   => 'privacy_policy',
            'value' => '<p>Privacy Policy content goes here.</p>',
            'label' => 'Privacy Policy',
            'type'  => 'richtext',
        ],
        [
            'key'   => 'about_us',
            'value' => '<p>About Us content goes here.</p>',
            'label' => 'About Us',
            'type'  => 'richtext',
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $setting) {
            AppSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
