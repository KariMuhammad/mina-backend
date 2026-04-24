<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'title' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif,svg|max:20480',
        ];
    }
}
