<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class ExposureService
{
    private const KM_PER_LATITUDE_DEGREE = 111.32;

    /**
     * @param  array<int, array{name: string, type: string, latitude: float|int, longitude: float|int}>  $facilities
     * @param  array{projected_latitude: float, projected_longitude: float, projected_travel_distance_km: float}  $projection
     * @return array<int, array{name: string, type: string, risk_level: string, urgency_level: string, approximate_eta_hours: float|null, along_track_distance_km: float, cross_track_distance_km: float, within_projection: bool}>
     */
    public function evaluate(
        array $facilities,
        float $hotspotLatitude,
        float $hotspotLongitude,
        float $windDirectionDegrees,
        float $windSpeedKmh,
        array $projection,
    ): array {
        if ($windSpeedKmh < 0 || ! is_finite($windSpeedKmh) || ! is_finite($windDirectionDegrees)) {
            throw new InvalidArgumentException('Wind values are invalid.');
        }

        $this->validateCoordinates($hotspotLatitude, $hotspotLongitude);
        $bearingRadians = deg2rad(fmod($windDirectionDegrees + 360.0, 360.0));

        return array_map(function (array $facility) use (
            $hotspotLatitude,
            $hotspotLongitude,
            $bearingRadians,
            $windSpeedKmh,
            $projection,
        ): array {
            $latitude = (float) ($facility['latitude'] ?? NAN);
            $longitude = (float) ($facility['longitude'] ?? NAN);
            $this->validateCoordinates($latitude, $longitude);

            [$eastKm, $northKm] = $this->relativePositionKm(
                $hotspotLatitude,
                $hotspotLongitude,
                $latitude,
                $longitude,
            );

            $alongTrackDistanceKm = ($eastKm * sin($bearingRadians)) + ($northKm * cos($bearingRadians));
            $crossTrackDistanceKm = abs(($eastKm * cos($bearingRadians)) - ($northKm * sin($bearingRadians)));
            $withinProjection = $alongTrackDistanceKm >= 0
                && $alongTrackDistanceKm <= $projection['projected_travel_distance_km'];

            $etaHours = $windSpeedKmh > 0 && $withinProjection
                ? $alongTrackDistanceKm / $windSpeedKmh
                : null;

            return [
                'name' => (string) ($facility['name'] ?? 'Unknown facility'),
                'type' => strtolower((string) ($facility['type'] ?? 'unknown')),
                'risk_level' => $this->riskLevel($crossTrackDistanceKm, $withinProjection),
                'urgency_level' => $this->urgencyLevel($etaHours),
                'approximate_eta_hours' => $etaHours === null ? null : round($etaHours, 2),
                'along_track_distance_km' => round($alongTrackDistanceKm, 2),
                'cross_track_distance_km' => round($crossTrackDistanceKm, 2),
                'within_projection' => $withinProjection,
            ];
        }, $facilities);
    }

    /** @return array{float, float} East and north distances in kilometres. */
    private function relativePositionKm(
        float $originLatitude,
        float $originLongitude,
        float $latitude,
        float $longitude,
    ): array {
        $averageLatitudeRadians = deg2rad(($originLatitude + $latitude) / 2);

        return [
            ($longitude - $originLongitude) * self::KM_PER_LATITUDE_DEGREE * cos($averageLatitudeRadians),
            ($latitude - $originLatitude) * self::KM_PER_LATITUDE_DEGREE,
        ];
    }

    private function riskLevel(float $crossTrackDistanceKm, bool $withinProjection): string
    {
        if (! $withinProjection) {
            return 'LOW';
        }

        return match (true) {
            $crossTrackDistanceKm <= 5 => 'HIGH',
            $crossTrackDistanceKm <= 15 => 'MEDIUM',
            default => 'LOW',
        };
    }

    private function urgencyLevel(?float $etaHours): string
    {
        if ($etaHours === null) {
            return 'NONE';
        }

        return match (true) {
            $etaHours <= 1 => 'CRITICAL',
            $etaHours <= 3 => 'HIGH',
            $etaHours <= 6 => 'MODERATE',
            default => 'LOW',
        };
    }

    private function validateCoordinates(float $latitude, float $longitude): void
    {
        if (! is_finite($latitude) || ! is_finite($longitude)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Invalid coordinates.');
        }
    }
}
