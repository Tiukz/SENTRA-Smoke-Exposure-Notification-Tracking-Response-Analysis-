<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

final class HotspotFreshnessService
{
    /** @param list<array<string, mixed>> $hotspots */
    public function enrich(array $hotspots): array
    {
        $now = CarbonImmutable::now('UTC');

        return array_map(function (array $hotspot) use ($now): array {
            $observedAt = CarbonImmutable::parse($hotspot['acquired_at'])->utc();
            $ageMinutes = max(0, (int) floor(($now->getTimestamp() - $observedAt->getTimestamp()) / 60));

            return [
                ...$hotspot,
                'acquired_at' => $observedAt->toIso8601String(),
                'observation_age_minutes' => $ageMinutes,
                'freshness_status' => $this->status($ageMinutes),
            ];
        }, $hotspots);
    }

    /** @param list<array<string, mixed>> $hotspots */
    public function observationRange(array $hotspots): array
    {
        if ($hotspots === []) {
            return ['newest_selected_observation' => null, 'oldest_selected_observation' => null];
        }

        $sorted = $hotspots;
        usort($sorted, static fn (array $left, array $right): int => strcmp($right['acquired_at'], $left['acquired_at']));

        return [
            'newest_selected_observation' => $this->observationMetadata($sorted[0]),
            'oldest_selected_observation' => $this->observationMetadata($sorted[array_key_last($sorted)]),
        ];
    }

    private function status(int $ageMinutes): string
    {
        return match (true) {
            $ageMinutes < 180 => 'FRESH',
            $ageMinutes < 360 => 'RECENT',
            $ageMinutes <= 720 => 'STALE',
            default => 'VERY_STALE',
        };
    }

    private function observationMetadata(array $hotspot): array
    {
        return [
            'acquired_at' => $hotspot['acquired_at'],
            'observation_age_minutes' => $hotspot['observation_age_minutes'],
            'freshness_status' => $hotspot['freshness_status'],
        ];
    }
}
