<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate handled by 'admin' middleware on the route group
    }

    public function rules(): array
    {
        return [
            'payment_status' => [
                'sometimes',
                'nullable',
                'string',
                'in:unpaid,pending,paid',
            ],
            'payment_method' => [
                'sometimes',
                'nullable',
                'string',
                'in:cod,none,card,wallet,bank_transfer',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_status.in' => 'Payment status must be one of: unpaid, pending, paid.',
            'payment_method.in' => 'Payment method must be one of: cod, none, card, wallet, bank_transfer.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->has('payment_status') && ! $this->has('payment_method')) {
                $v->errors()->add('_', 'At least one of payment_status or payment_method must be provided.');
            }
        });
    }
}
