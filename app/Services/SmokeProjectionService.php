<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class SmokeProjectionService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Simplified straight-line propagation for a prototype.
     * This is not a scientific atmospheric dispersion model.
     *
     * @return array{projected_latitude: float, projected_longitude: float, projected_travel_distance_km: float}
     */
    public function project(
        float $hotspotLatitude,
        float $hotspotLongitude,
        float $windDirectionDegrees,
        float $windSpeedKmh,
        float $projectionTimeHours,
    ): array {
        $this->validateCoordinates($hotspotLatitude, $hotspotLongitude);

        if (! is_finite($windDirectionDegrees) || ! is_finite($windSpeedKmh) || ! is_finite($projectionTimeHours)) {
            throw new InvalidArgumentException('Projection values must be finite numbers.');
        }

        if ($windSpeedKmh < 0 || $projectionTimeHours < 0) {
            throw new InvalidArgumentException('Wind speed and projection time cannot be negative.');
        }

        $travelDistanceKm = $windSpeedKmh * $projectionTimeHours;
        $bearingRadians = deg2rad(fmod($windDirectionDegrees + 360.0, 360.0));
        $latitudeRadians = deg2rad($hotspotLatitude);
        $longitudeRadians = deg2rad($hotspotLongitude);
        $angularDistance = $travelDistanceKm / self::EARTH_RADIUS_KM;

        $projectedLatitudeRadians = asin(
            sin($latitudeRadians) * cos($angularDistance)
            + cos($latitudeRadians) * sin($angularDistance) * cos($bearingRadians)
        );

        $projectedLongitudeRadians = $longitudeRadians + atan2(
            sin($bearingRadians) * sin($angularDistance) * cos($latitudeRadians),
            cos($angularDistance) - sin($latitudeRadians) * sin($projectedLatitudeRadians)
        );

        return [
            'projected_latitude' => round(rad2deg($projectedLatitudeRadians), 6),
            'projected_longitude' => round(rad2deg($projectedLongitudeRadians), 6),
            'projected_travel_distance_km' => round($travelDistanceKm, 2),
        ];
    }

    private function validateCoordinates(float $latitude, float $longitude): void
    {
        if (! is_finite($latitude) || ! is_finite($longitude)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Invalid hotspot coordinates.');
        }
    }
}
