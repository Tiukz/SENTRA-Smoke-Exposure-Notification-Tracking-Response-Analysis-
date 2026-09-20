<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class HotspotImpactOverviewService
{
    private const RISK_ORDER = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];

    private const URGENCY_ORDER = ['NONE' => 0, 'LOW' => 1, 'MODERATE' => 2, 'HIGH' => 3, 'CRITICAL' => 4];

    public function __construct(
        private readonly SmokeProjectionService $smokeProjectionService,
        private readonly ExposureService $exposureService,
        private readonly InterventionWindowService $interventionWindowService,
        private readonly ActiveHotspotService $activeHotspotService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $hotspots
     * @param  list<array<string, mixed>>  $facilities
     * @param  array<string, mixed>  $cacheContext
     * @return array<string, mixed>
     */
    public function scan(
        array $hotspots,
        array $facilities,
        float $windDirectionDegrees,
        float $windSpeedKmh,
        float $projectionTimeHours,
        string $mode,
        array $cacheContext = [],
    ): array {
        $cacheKey = 'sentra:hotspot-situation:'.hash('sha256', json_encode([
            'mode' => $mode,
            'hotspots' => $hotspots,
            'facilities' => $facilities,
            'wind_direction' => $windDirectionDegrees,
            'wind_speed' => $windSpeedKmh,
            'projection_horizon' => $projectionTimeHours,
            'context' => $cacheContext,
        ], JSON_THROW_ON_ERROR));

        return Cache::remember(
            $cacheKey,
            (int) config('services.situation_summary.cache_seconds', 300),
            fn (): array => $this->calculate(
                $hotspots,
                $facilities,
                $windDirectionDegrees,
                $windSpeedKmh,
                $projectionTimeHours,
                $mode,
            ),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $hotspots
     * @param  list<array<string, mixed>>  $facilities
     * @return array<string, mixed>
     */
    private function calculate(
        array $hotspots,
        array $facilities,
        float $windDirectionDegrees,
        float $windSpeedKmh,
        float $projectionTimeHours,
        string $mode,
    ): array {
        $items = [];
        $affectedFacilityIds = [];

        foreach ($hotspots as $hotspot) {
            $projection = $this->smokeProjectionService->project(
                (float) $hotspot['latitude'],
                (float) $hotspot['longitude'],
                $windDirectionDegrees,
                $windSpeedKmh,
                $projectionTimeHours,
            );
            $exposures = $this->exposureService->evaluate(
                $facilities,
                (float) $hotspot['latitude'],
                (float) $hotspot['longitude'],
                $windDirectionDegrees,
                $windSpeedKmh,
                $projection,
            );
            $observedAt = CarbonImmutable::parse((string) ($hotspot['acquired_at'] ?? now('UTC')->toIso8601String()));
            $results = $this->interventionWindowService->calculate($exposures, $observedAt);
            $affected = [];

            foreach ($results as $index => $result) {
                if (($result['risk_level'] ?? 'LOW') === 'LOW') {
                    continue;
                }

                $affected[] = $result;
                $facility = $facilities[$index] ?? [];
                $affectedFacilityIds[$this->facilityIdentifier($facility)] = true;
            }

            $items[] = $this->summarizeHotspot($hotspot, $affected);
        }

        usort($items, fn (array $left, array $right): int => $this->compare($left, $right));
        $nearestEtas = array_values(array_filter(
            array_column($items, 'nearest_eta_minutes'),
            static fn (mixed $eta): bool => is_int($eta),
        ));

        return [
            'mode' => $mode,
            'ordering' => [
                'critical_urgency',
                'high_urgency',
                'high_risk',
                'facilities_affected',
                'nearest_eta',
                'hotspot_id',
            ],
            'summary' => [
                'hotspots_total' => count($hotspots),
                'hotspots_with_impact' => count(array_filter($items, static fn (array $item): bool => $item['facilities_affected'] > 0)),
                'hotspots_requiring_attention' => count(array_filter($items, static fn (array $item): bool => $item['requires_attention'])),
                'facilities_potentially_affected' => count($affectedFacilityIds),
                'nearest_eta_minutes' => $nearestEtas === [] ? null : min($nearestEtas),
                'highest_priority_hotspot_id' => $items[0]['hotspot_id'] ?? null,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $hotspot
     * @param  list<array<string, mixed>>  $affected
     * @return array<string, mixed>
     */
    private function summarizeHotspot(array $hotspot, array $affected): array
    {
        $riskLevels = array_column($affected, 'risk_level');
        $urgencyLevels = array_column($affected, 'urgency_level');
        $etas = array_values(array_filter(array_map(
            static fn (array $facility): ?int => isset($facility['approximate_eta_hours'])
                ? (int) round((float) $facility['approximate_eta_hours'] * 60)
                : null,
            $affected,
        ), static fn (?int $eta): bool => $eta !== null));
        $criticalCount = count(array_filter($urgencyLevels, static fn (string $level): bool => $level === 'CRITICAL'));
        $highUrgencyCount = count(array_filter($urgencyLevels, static fn (string $level): bool => $level === 'HIGH'));
        $highRiskCount = count(array_filter($riskLevels, static fn (string $level): bool => $level === 'HIGH'));

        return [
            'hotspot_id' => $this->activeHotspotService->generateId($hotspot),
            'latitude' => (float) $hotspot['latitude'],
            'longitude' => (float) $hotspot['longitude'],
            'facilities_affected' => count($affected),
            'high_risk_count' => $highRiskCount,
            'critical_urgency_count' => $criticalCount,
            'high_urgency_count' => $highUrgencyCount,
            'nearest_eta_minutes' => $etas === [] ? null : min($etas),
            'highest_risk' => $this->highest($riskLevels, self::RISK_ORDER, 'LOW'),
            'highest_urgency' => $this->highest($urgencyLevels, self::URGENCY_ORDER, 'NONE'),
            'requires_attention' => $criticalCount > 0 || $highUrgencyCount > 0 || $highRiskCount > 0,
        ];
    }

    /** @param list<string> $levels @param array<string, int> $order */
    private function highest(array $levels, array $order, string $fallback): string
    {
        usort($levels, static fn (string $left, string $right): int => ($order[$right] ?? 0) <=> ($order[$left] ?? 0));

        return $levels[0] ?? $fallback;
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function compare(array $left, array $right): int
    {
        foreach (['critical_urgency_count', 'high_urgency_count', 'high_risk_count'] as $field) {
            $comparison = ((int) $right[$field] > 0) <=> ((int) $left[$field] > 0);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        if ($left['facilities_affected'] !== $right['facilities_affected']) {
            return $right['facilities_affected'] <=> $left['facilities_affected'];
        }

        $leftEta = $left['nearest_eta_minutes'] ?? PHP_INT_MAX;
        $rightEta = $right['nearest_eta_minutes'] ?? PHP_INT_MAX;

        return $leftEta !== $rightEta
            ? $leftEta <=> $rightEta
            : strcmp($left['hotspot_id'], $right['hotspot_id']);
    }

    /** @param array<string, mixed> $facility */
    private function facilityIdentifier(array $facility): string
    {
        if (isset($facility['id']) && trim((string) $facility['id']) !== '') {
            return (string) $facility['id'];
        }

        return hash('sha256', json_encode([
            $facility['name'] ?? null,
            $facility['type'] ?? null,
            $facility['latitude'] ?? null,
            $facility['longitude'] ?? null,
            $facility['source'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }
}
