<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ActiveScenarioService
{
    private const SESSION_KEY = 'sentra.active_smoke_corridor';

    /** @param array<string, mixed> $result */
    public function store(array $result): array
    {
        $scenario = $result['scenario'] ?? null;
        $projection = $result['projection'] ?? null;

        if (! is_array($scenario) || ! is_array($projection)) {
            throw new RuntimeException('Hasil skenario tidak memiliki koridor asap yang valid.');
        }

        $corridor = [
            'mode' => (string) ($result['data_mode']['mode'] ?? 'unknown'),
            'hotspot_latitude' => (float) $scenario['hotspot_cluster']['latitude'],
            'hotspot_longitude' => (float) $scenario['hotspot_cluster']['longitude'],
            'wind_direction_degrees' => (float) $scenario['wind']['direction_degrees'],
            'projection_distance_km' => (float) $projection['projected_travel_distance_km'],
            'projection_time_hours' => (float) $scenario['projection_time_hours'],
        ];
        $fingerprint = hash('sha256', json_encode($corridor, JSON_THROW_ON_ERROR));
        $previous = session(self::SESSION_KEY);
        $version = is_array($previous) && ($previous['fingerprint'] ?? null) === $fingerprint
            ? (int) $previous['version']
            : (int) ($previous['version'] ?? 0) + 1;
        $state = [...$corridor, 'fingerprint' => $fingerprint, 'version' => $version];

        session([self::SESSION_KEY => $state]);

        return $state;
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        $state = session(self::SESSION_KEY);

        return is_array($state) ? $state : null;
    }
}
