<?php

namespace App\Services;

use App\Models\DeliveryZone;

class DeliveryZoneService
{
    public function isAddressAllowed(string $address): bool
    {
        $zones = DeliveryZone::active()->pluck('name');

        if ($zones->isEmpty()) {
            return false;
        }

        $addressLower = mb_strtolower($address);

        foreach ($zones as $zone) {
            if (str_contains($addressLower, mb_strtolower($zone))) {
                return true;
            }
        }

        return false;
    }
}
