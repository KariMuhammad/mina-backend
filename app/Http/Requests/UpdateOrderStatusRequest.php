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
            'status' => 'required|string|in:Pending,Processing,Completed,Cancelled,pending,processing,processed,completed,cancelled,canceled,shipped,delivered,done,new',
        ];
    }
}
