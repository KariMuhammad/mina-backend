<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('shipping_address_id') && $this->filled('address_id')) {
            $this->merge(['shipping_address_id' => $this->input('address_id')]);
        }

        $addr = $this->input('shipping_address');
        if (is_array($addr) && $addr === []) {
            $this->merge(['shipping_address' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_address_id' => ['nullable', 'integer'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.label' => ['nullable', 'string', 'max:255'],
            'shipping_address.name' => ['nullable', 'string', 'max:255'],
            'shipping_address.phone' => ['nullable', 'string', 'max:32'],
            'shipping_address.address_line' => ['nullable', 'string', 'max:255'],
            'shipping_address.address_line_1' => ['nullable', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_address.zip' => ['nullable', 'string', 'max:20'],
            'shipping_address.country' => ['nullable', 'string', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['required', 'string', Rule::in(['cod', 'none'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $id = $this->input('shipping_address_id');
            $inline = $this->input('shipping_address');

            $hasId = $id !== null && $id !== '';
            $hasInline = is_array($inline) && (
                ! empty($inline['address_line'] ?? null)
                || ! empty($inline['address_line_1'] ?? null)
            );

            if (! $hasId && ! $hasInline) {
                $v->errors()->add(
                    'shipping_address_id',
                    'Either shipping_address_id or a shipping_address with address_line is required.'
                );
            }

            if ($hasInline) {
                $city = (string) ($inline['city'] ?? '');
                $zip = (string) ($inline['postal_code'] ?? $inline['zip'] ?? '');
                if ($city === '') {
                    $v->errors()->add('shipping_address.city', 'City is required when using shipping_address.');
                }
                if ($zip === '') {
                    $v->errors()->add('shipping_address.postal_code', 'Postal code is required when using shipping_address.');
                }
            }
        });
    }
}
