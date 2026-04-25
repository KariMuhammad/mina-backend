<?php

namespace App\Rules;

use App\Models\DeliveryZone;
use Illuminate\Contracts\Validation\Rule;

class ValidCityRule implements Rule
{
    public function passes($attribute, $value)
    {
        $normalized = mb_strtolower(trim($value));

        return DeliveryZone::where('is_active', true)
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(name) = ?', [$normalized])
                      ->orWhereRaw('LOWER(english_name) = ?', [$normalized]);
            })
            ->exists();
    }

    public function message()
    {
        return 'المدينة المدخلة غير متاحة للتوصيل. يرجى اختيار مدينة من القائمة المتاحة.';
    }
}
