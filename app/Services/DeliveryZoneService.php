<?php

namespace App\Services;

use App\Models\DeliveryZone;

class DeliveryZoneService
{
    public function isAddressAllowed(string $address): bool
    {
        if (empty(trim($address))) return false;

        $address = mb_strtolower($address);

        $zones = DeliveryZone::active()->get();

        foreach ($zones as $zone) {
            // Check Arabic name
            if (str_contains($address, mb_strtolower($zone->name))) {
                return true;
            }
            // Check English name if provided
            if ($zone->english_name &&
                str_contains($address, mb_strtolower($zone->english_name))) {
                return true;
            }
        }

        return false;
    }

    public function resolveZoneFromAddress(string $address): ?DeliveryZone
    {
        if (empty(trim($address))) return null;

        $normalized = mb_strtolower($address);

        $zones = DeliveryZone::active()->get();

        foreach ($zones as $zone) {
            if (str_contains($normalized, mb_strtolower($zone->name))) {
                return $zone;
            }
            if ($zone->english_name &&
                str_contains($normalized, mb_strtolower($zone->english_name))) {
                return $zone;
            }
        }

        return null;
    }
}
