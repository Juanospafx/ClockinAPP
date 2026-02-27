<?php
declare(strict_types=1);

/**
 * GeofenceService — validates whether a point is within a circular geofence.
 *
 * Uses the Haversine formula for accurate great-circle distance on Earth.
 * No external API required for the distance check itself.
 */
class GeofenceService
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Calculate the distance in meters between two lat/lng points using the Haversine formula.
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Check if a user position is within the geofence of a project.
     *
     * @param float $userLat     User latitude
     * @param float $userLng     User longitude
     * @param float $projectLat  Project center latitude
     * @param float $projectLng  Project center longitude
     * @param int   $radiusM     Allowed radius in meters
     *
     * @return array{inside: bool, distance: float, radius: int}
     */
    public static function checkPosition(
        float $userLat,
        float $userLng,
        float $projectLat,
        float $projectLng,
        int $radiusM
    ): array {
        $distance = self::distanceMeters($userLat, $userLng, $projectLat, $projectLng);

        return [
            'inside'   => $distance <= $radiusM,
            'distance' => round($distance, 1),
            'radius'   => $radiusM,
        ];
    }

    /**
     * Parse a "lat,lng" location string into [latitude, longitude] floats.
     * Returns null if the string is not a valid coordinate pair.
     */
    public static function parseLocation(string $location): ?array
    {
        $parts = explode(',', $location);
        if (count($parts) !== 2) {
            return null;
        }

        $lat = trim($parts[0]);
        $lng = trim($parts[1]);

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $latF = (float)$lat;
        $lngF = (float)$lng;

        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            return null;
        }

        return [$latF, $lngF];
    }
}
