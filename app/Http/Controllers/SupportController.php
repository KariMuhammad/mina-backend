<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function info()
    {
        $settings = Setting::whereIn('key', [
            'support_phone',
            'support_email',
            'support_whatsapp',
            'live_chat_url'
        ])->pluck('value', 'key');

        return response()->json([
            'phone' => $settings['support_phone'] ?? '+1234567890',
            'email' => $settings['support_email'] ?? 'support@example.com',
            'whatsapp' => $settings['support_whatsapp'] ?? '+1234567890',
            'live_chat_url' => $settings['live_chat_url'] ?? 'https://chat.example.com',
        ]);
    }
}
