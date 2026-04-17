<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function show($slug)
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->first();

        if (!$page) {
            return response()->json([
                'title' => ucfirst(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'content' => '<h1>' . ucfirst(str_replace('-', ' ', $slug)) . '</h1><p>This content is currently being updated. Please check back later.</p>'
            ]);
        }

        return response()->json($page);
    }

    public function termsAndConditions()
    {
        $page = Page::where('slug', 'terms-and-condition')->where('is_active', true)->first();

        if (!$page) {
            return response()->json([
                'title' => 'Terms & Conditions',
                'slug' => 'terms-and-condition',
                'content' => '<h1>Terms and Conditions</h1><p>Our terms and conditions are currently being updated by the legal team. Please check back shortly.</p>'
            ]);
        }

        return response()->json($page);
    }
}
