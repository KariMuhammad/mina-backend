<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:Pending,Processing,Preparing,Out_for_Delivery,Completed,Cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: Pending, Processing, Preparing, Out_for_Delivery, Completed, Cancelled.',
        ];
    }
}
