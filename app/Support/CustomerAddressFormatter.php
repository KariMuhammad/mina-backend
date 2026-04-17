<?php

namespace App\Support;

use App\Models\Address;

class CustomerAddressFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Address $a): array
    {
        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'label' => $a->name,
            'name' => $a->name,
            'phone' => $a->phone,
            'address_line' => $a->address_line_1,
            'address_line_1' => $a->address_line_1,
            'address_line_2' => $a->address_line_2,
            'city' => $a->city,
            'postal_code' => $a->zip,
            'zip' => $a->zip,
            'country' => $a->country,
            'is_default' => (bool) $a->is_default,
            'created_at' => $a->created_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }
}
