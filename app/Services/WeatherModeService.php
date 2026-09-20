<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class WeatherModeService
{
    public function __construct(
        private readonly SnapshotWeatherProvider $snapshotProvider,
        private readonly LiveWeatherProvider $liveProvider,
        private readonly SimulationWeatherProvider $simulationProvider,
    ) {}

    /** @return array<string, mixed> */
    public function get(string $mode, float $latitude, float $longitude): array
    {
        return match ($mode) {
            'snapshot' => $this->snapshotProvider->get($latitude, $longitude),
            'live' => $this->liveProvider->get($latitude, $longitude),
            'simulation' => $this->simulationProvider->get($latitude, $longitude),
            default => throw new InvalidArgumentException('Mode cuaca tidak dikenal.'),
        };
    }
}
