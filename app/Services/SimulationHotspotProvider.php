<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HotspotProvider;
use Carbon\CarbonImmutable;
use RuntimeException;

final class SimulationHotspotProvider implements HotspotProvider
{
    public function __construct(private readonly HotspotFreshnessService $freshnessService) {}

    public function get(): array
    {
        $contents = file_get_contents(storage_path('app/demo-scenario.json'));

        if ($contents === false) {
            throw new RuntimeException('Skenario simulasi tidak dapat dimuat.');
        }

        $scenario = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $acquiredAt = CarbonImmutable::parse($scenario['simulation_started_at'])->utc()->toIso8601String();
        $hotspot = $this->freshnessService->enrich([[
            ...$scenario['hotspot_cluster'],
            'frp' => null,
            'satellite' => null,
            'instrument' => null,
            'acquired_at' => $acquiredAt,
            'source' => 'SENTRA Demo Scenario',
        ]])[0];

        return [
            'mode' => 'simulation',
            'available' => true,
            'status' => 'simulation',
            'source' => 'SENTRA Demo Scenario',
            'acquired_at' => $acquiredAt,
            'observation_age_minutes' => $hotspot['observation_age_minutes'],
            'freshness_status' => $hotspot['freshness_status'],
            'hotspot_count' => 1,
            'hotspots' => [$hotspot],
            'primary_hotspot' => $hotspot,
            'metadata' => $this->freshnessService->observationRange([$hotspot]),
            'message' => 'Koordinat hotspot, angin, dan fasilitas merupakan data simulasi.',
        ];
    }
}
