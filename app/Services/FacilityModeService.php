<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class FacilityModeService
{
    public function __construct(
        private readonly SnapshotFacilityProvider $snapshotProvider,
        private readonly LiveOsmFacilityProvider $liveProvider,
        private readonly SimulationFacilityProvider $simulationProvider,
    ) {}

    /** @return array<string, mixed> */
    public function get(string $mode, float $latitude, float $longitude): array
    {
        return match ($mode) {
            'snapshot' => $this->snapshotProvider->get($latitude, $longitude),
            'live' => $this->liveProvider->get($latitude, $longitude),
            'simulation' => $this->simulationProvider->get($latitude, $longitude),
            default => throw new InvalidArgumentException('Mode fasilitas tidak dikenal.'),
        };
    }
}
