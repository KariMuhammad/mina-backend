<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate handled by 'admin' middleware on the route group
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'nullable',
                'string',
                'in:Pending,pending,Processing,processing,processed,shipped,Completed,completed,delivered,done,Cancelled,cancelled,canceled,new',
            ],
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
            'tracking_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:128',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'         => 'Status must be one of: Pending, Processing, Completed, Cancelled.',
            'payment_status.in' => 'Payment status must be one of: unpaid, pending, paid.',
            'payment_method.in' => 'Payment method must be one of: cod, none, card, wallet, bank_transfer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure at least one actionable field is present
    }

    /**
     * After validation — ensure at least one meaningful field was sent.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $fields = ['status', 'payment_status', 'payment_method', 'tracking_number', 'notes'];
            $hasAny = false;
            foreach ($fields as $f) {
                if ($this->has($f)) {
                    $hasAny = true;
                    break;
                }
            }
            if (! $hasAny) {
                $v->errors()->add('_', 'At least one field (status, payment_status, payment_method, tracking_number, notes) must be provided.');
            }
        });
    }
}
