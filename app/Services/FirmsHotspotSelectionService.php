<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class FirmsHotspotSelectionService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * @param  list<array<string, mixed>>  $hotspots
     * @return array{hotspots: list<array<string, mixed>>, metadata: array<string, int|float>}
     */
    public function select(array $hotspots): array
    {
        [$west, $south, $east, $north] = $this->boundingBox();
        $insideRegion = array_values(array_filter($hotspots, static fn (array $hotspot): bool => $hotspot['longitude'] >= $west && $hotspot['longitude'] <= $east
            && $hotspot['latitude'] >= $south && $hotspot['latitude'] <= $north));

        $minimumConfidence = max(0, min(100, (int) config('services.firms.minimum_confidence')));
        $recentHours = max(1, (int) config('services.firms.recent_hours'));
        $latestAcquisition = $insideRegion === []
            ? null
            : max(array_column($insideRegion, 'acquired_at'));
        $cutoff = $latestAcquisition === null
            ? null
            : CarbonImmutable::parse($latestAcquisition)->subHours($recentHours);
        $filtered = array_values(array_filter($insideRegion, static fn (array $hotspot): bool => $hotspot['confidence'] >= $minimumConfidence
            && ($cutoff === null || CarbonImmutable::parse($hotspot['acquired_at'])->greaterThanOrEqualTo($cutoff))));

        usort($filtered, [$this, 'compare']);

        $maximum = min(15, max(1, (int) config('services.firms.max_representative_hotspots')));
        $minimumSeparationKm = max(0.0, (float) config('services.firms.minimum_separation_km'));
        $selected = [];

        foreach ($filtered as $hotspot) {
            $isSeparated = true;

            foreach ($selected as $existing) {
                if ($this->distanceKm($hotspot, $existing) < $minimumSeparationKm) {
                    $isSeparated = false;
                    break;
                }
            }

            if ($isSeparated) {
                $selected[] = $hotspot;
            }

            if (count($selected) === $maximum) {
                break;
            }
        }

        return [
            'hotspots' => $selected,
            'metadata' => [
                'raw_record_count' => count($hotspots),
                'inside_region_count' => count($insideRegion),
                'filtered_record_count' => count($filtered),
                'selected_hotspot_count' => count($selected),
                'minimum_separation_km' => $minimumSeparationKm,
            ],
        ];
    }

    private function compare(array $left, array $right): int
    {
        return strcmp($right['acquired_at'], $left['acquired_at'])
            ?: (($right['frp'] ?? -1) <=> ($left['frp'] ?? -1))
            ?: ($right['confidence'] <=> $left['confidence'])
            ?: ($left['latitude'] <=> $right['latitude'])
            ?: ($left['longitude'] <=> $right['longitude'])
            ?: strcmp((string) ($left['satellite'] ?? ''), (string) ($right['satellite'] ?? ''));
    }

    /** @return array{float, float, float, float} */
    private function boundingBox(): array
    {
        $bounds = array_map('trim', explode(',', (string) config('services.firms.area')));

        if (count($bounds) !== 4 || count(array_filter($bounds, 'is_numeric')) !== 4) {
            throw new RuntimeException('Bounding box NASA FIRMS tidak valid.');
        }

        return array_map('floatval', $bounds);
    }

    private function distanceKm(array $first, array $second): float
    {
        $latitudeDelta = deg2rad($second['latitude'] - $first['latitude']);
        $longitudeDelta = deg2rad($second['longitude'] - $first['longitude']);
        $firstLatitude = deg2rad($first['latitude']);
        $secondLatitude = deg2rad($second['latitude']);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($firstLatitude) * cos($secondLatitude) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
