<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\MapCache;

use Nordkirche\Ndk\Domain\Model\Address;

/**
 * Coordinates of NAPI addresses for map markers
 */
final class GeoPosition
{
    /**
     * @return array{0: float, 1: float}|null Latitude and longitude, null without (complete) coordinates
     */
    public static function fromAddress(mixed $address): ?array
    {
        if (!$address instanceof Address) {
            return null;
        }
        try {
            // The getters of the NDK are not nullable, but addresses may have no coordinates
            $latitude = $address->getLatitude();
            $longitude = $address->getLongitude();
        } catch (\TypeError) {
            return null;
        }
        return $latitude && $longitude ? [$latitude, $longitude] : null;
    }
}
