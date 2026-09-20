<?php

declare(strict_types=1);

namespace App\Services;

final class RouteExposureService
{
    private const EARTH_RADIUS_KM = 6371.0088;

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $corridor
     * @return array<string, mixed>
     */
    public function evaluate(array $route, array $corridor): array
    {
        $geometry = $route['geometry'];
        $sampleStepKm = max(0.01, (float) config('services.route_exposure.sample_step_meters', 250) / 1000);
        $geometryDistanceKm = 0.0;
        $overlapDistanceKm = 0.0;

        for ($index = 1; $index < count($geometry); $index++) {
            $start = $geometry[$index - 1];
            $end = $geometry[$index];
            $segmentDistanceKm = $this->distanceKm($start, $end);

            if ($segmentDistanceKm <= 0) {
                continue;
            }

            $geometryDistanceKm += $segmentDistanceKm;
            $parts = max(1, (int) ceil($segmentDistanceKm / $sampleStepKm));
            $partDistanceKm = $segmentDistanceKm / $parts;

            for ($part = 0; $part < $parts; $part++) {
                $fraction = ($part + 0.5) / $parts;
                $sample = [
                    'latitude' => $start['latitude'] + (($end['latitude'] - $start['latitude']) * $fraction),
                    'longitude' => $start['longitude'] + (($end['longitude'] - $start['longitude']) * $fraction),
                ];

                if ($this->isInsideCorridor($sample, $corridor)) {
                    $overlapDistanceKm += $partDistanceKm;
                }
            }
        }

        $overlapPercent = $geometryDistanceKm > 0
            ? min(100.0, ($overlapDistanceKm / $geometryDistanceKm) * 100)
            : 0.0;

        return [
            'id' => $route['id'],
            'distance_km' => round((float) $route['distance_meters'] / 1000, 2),
            'duration_minutes' => round((float) $route['duration_seconds'] / 60, 1),
            'corridor_overlap_km' => round($overlapDistanceKm, 2),
            'corridor_overlap_percent' => round($overlapPercent, 1),
            'intersects_corridor' => $overlapDistanceKm > 0,
            'exposure_level' => $this->exposureLevel($overlapPercent),
            'source' => $route['source'],
            'geometry' => $geometry,
        ];
    }

    /** @param array{latitude: float, longitude: float} $point */
    private function isInsideCorridor(array $point, array $corridor): bool
    {
        $originLatitude = (float) $corridor['hotspot_latitude'];
        $originLongitude = (float) $corridor['hotspot_longitude'];
        $projectionDistanceKm = (float) $corridor['projection_distance_km'];

        if ($projectionDistanceKm <= 0) {
            return false;
        }

        $averageLatitude = deg2rad(($originLatitude + $point['latitude']) / 2);
        $northKm = ($point['latitude'] - $originLatitude) * 111.32;
        $eastKm = ($point['longitude'] - $originLongitude) * 111.32 * cos($averageLatitude);
        $bearing = deg2rad((float) $corridor['wind_direction_degrees']);
        $alongTrackKm = ($eastKm * sin($bearing)) + ($northKm * cos($bearing));
        $crossTrackKm = abs(($eastKm * cos($bearing)) - ($northKm * sin($bearing)));

        if ($alongTrackKm < 0 || $alongTrackKm > $projectionDistanceKm) {
            return false;
        }

        $progress = $alongTrackKm / $projectionDistanceKm;
        $halfWidthKm = $this->corridorHalfWidthKm($progress);

        return $crossTrackKm <= $halfWidthKm;
    }

    private function corridorHalfWidthKm(float $progress): float
    {
        $profile = config('services.route_exposure.corridor_width_profile', [[0, 0], [1, 8]]);
        $previous = $profile[0];

        foreach (array_slice($profile, 1) as $point) {
            if ($progress <= $point[0]) {
                $span = max(0.000001, $point[0] - $previous[0]);
                $fraction = ($progress - $previous[0]) / $span;

                return max(0.0, $previous[1] + (($point[1] - $previous[1]) * $fraction));
            }

            $previous = $point;
        }

        return max(0.0, (float) $previous[1]);
    }

    private function exposureLevel(float $overlapPercent): string
    {
        if ($overlapPercent <= 10) {
            return 'LOW';
        }

        if ($overlapPercent <= 30) {
            return 'MEDIUM';
        }

        return 'HIGH';
    }

    /**
     * @param  array{latitude: float, longitude: float}  $start
     * @param  array{latitude: float, longitude: float}  $end
     */
    private function distanceKm(array $start, array $end): float
    {
        $latitudeDelta = deg2rad($end['latitude'] - $start['latitude']);
        $longitudeDelta = deg2rad($end['longitude'] - $start['longitude']);
        $startLatitude = deg2rad($start['latitude']);
        $endLatitude = deg2rad($end['latitude']);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($startLatitude) * cos($endLatitude) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
