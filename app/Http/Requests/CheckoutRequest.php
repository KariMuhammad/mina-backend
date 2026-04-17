<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout validation rules. Used via CheckoutRequest::rulesFor($isGuest) from the controller
 * so an invalid Bearer token can return 401 before rules are evaluated as a guest checkout.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(bool $isGuest): array
    {
        if ($isGuest) {
            return [
                'guest_id' => ['required', 'string', 'max:64'],
                'coupon_code' => ['nullable', 'string', 'max:64'],
                'guest_name' => ['required', 'string', 'max:255'],
                'guest_email' => ['required', 'email'],
                'guest_phone' => ['required', 'string', 'max:32'],
                'shipping_address_line_1' => ['required', 'string', 'max:255'],
                'shipping_address_line_2' => ['nullable', 'string', 'max:255'],
                'shipping_city' => ['required', 'string', 'max:100'],
                'shipping_zip' => ['required', 'string', 'max:20'],
                'shipping_country' => ['nullable', 'string', 'max:100'],
            ];
        }

        return [
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where(fn ($q) => $q->where('user_id', auth()->id())),
            ],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function customMessages(): array
    {
        return [
            'guest_id.required' => 'guest_id is required for guest checkout (pass it as a query parameter or in the body).',
        ];
    }
}
