<?php

declare(strict_types=1);

namespace App\Services;

final class ScenarioSummaryService
{
    /**
     * @param  list<array<string, mixed>>  $facilities
     * @param  list<array<string, mixed>>  $priorityQueue
     */
    public function summarize(array $facilities, array $priorityQueue): array
    {
        $highRiskCount = count(array_filter($facilities, static fn (array $facility): bool => $facility['risk_level'] === 'HIGH'));
        $criticalUrgencyCount = count(array_filter($facilities, static fn (array $facility): bool => $facility['urgency_level'] === 'CRITICAL'));
        $highUrgencyCount = count(array_filter($facilities, static fn (array $facility): bool => $facility['urgency_level'] === 'HIGH'));
        $watchCount = count(array_filter($facilities, static fn (array $facility): bool => $facility['risk_level'] === 'MEDIUM' || $facility['urgency_level'] === 'MODERATE'));
        $etas = array_values(array_filter(array_column($facilities, 'approximate_eta_hours'), 'is_numeric'));

        return [
            'facilities_total' => count($facilities),
            'high_risk_count' => $highRiskCount,
            'critical_urgency_count' => $criticalUrgencyCount,
            'nearest_eta_minutes' => $etas === [] ? null : (int) round(min($etas) * 60),
            'top_priority_facility' => $priorityQueue[0]['facility_name'] ?? null,
            'status' => match (true) {
                $criticalUrgencyCount > 0 => 'CRITICAL',
                $highRiskCount > 0 || $highUrgencyCount > 0 => 'ALERT',
                $watchCount > 0 => 'WATCH',
                default => 'SAFE',
            },
        ];
    }
}
