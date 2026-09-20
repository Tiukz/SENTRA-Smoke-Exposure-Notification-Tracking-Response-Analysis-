<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

final class InterventionWindowService
{
    private const SAFETY_BUFFERS_MINUTES = [
        'school' => 45,
        'hospital' => 60,
        'residential' => 60,
    ];

    /**
     * @param  array<int, array{name: string, type: string, risk_level: string, urgency_level: string, approximate_eta_hours: float|null, along_track_distance_km: float, cross_track_distance_km: float, within_projection: bool}>  $exposures
     * @return array<int, array<string, mixed>>
     */
    public function calculate(array $exposures, CarbonImmutable $simulationStartedAt): array
    {
        return array_map(function (array $exposure) use ($simulationStartedAt): array {
            $safetyBufferMinutes = self::SAFETY_BUFFERS_MINUTES[$exposure['type']] ?? 60;
            $etaHours = $exposure['approximate_eta_hours'];
            $exposureTime = $etaHours === null
                ? null
                : $simulationStartedAt->addSeconds((int) round($etaHours * 3600));

            return [
                'facility_name' => $exposure['name'],
                'facility_type' => $exposure['type'],
                'risk_level' => $exposure['risk_level'],
                'urgency_level' => $exposure['urgency_level'],
                'along_track_distance_km' => $exposure['along_track_distance_km'],
                'cross_track_distance_km' => $exposure['cross_track_distance_km'],
                'within_projection' => $exposure['within_projection'],
                'approximate_eta_hours' => $etaHours,
                'projected_exposure_time' => $exposureTime?->toIso8601String(),
                'intervention_deadline' => $exposureTime?->subMinutes($safetyBufferMinutes)->toIso8601String(),
                'safety_buffer_minutes' => $safetyBufferMinutes,
            ];
        }, $exposures);
    }
}
