<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class HotspotModeService
{
    public function __construct(
        private readonly SimulationHotspotProvider $simulationProvider,
        private readonly FirmsHotspotProvider $firmsProvider,
        private readonly SnapshotHotspotProvider $snapshotProvider,
    ) {}

    /** @return array<string, mixed> */
    public function get(string $mode): array
    {
        return match ($mode) {
            'simulation' => $this->simulationProvider->get(),
            'live' => $this->firmsProvider->get(),
            'snapshot' => $this->snapshotProvider->get(),
            default => throw new InvalidArgumentException('Mode data tidak dikenal.'),
        };
    }
}
