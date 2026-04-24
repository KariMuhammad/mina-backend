<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'name' => 'sometimes|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif,svg|max:20480',
        ];
    }
}
