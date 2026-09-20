<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WeatherProvider;
use Carbon\CarbonImmutable;
use RuntimeException;

final class SimulationWeatherProvider implements WeatherProvider
{
    public function get(float $latitude, float $longitude): array
    {
        $contents = file_get_contents(storage_path('app/demo-scenario.json'));

        if ($contents === false) {
            return $this->unavailable();
        }

        try {
            $scenario = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $wind = $scenario['wind'] ?? throw new RuntimeException('Data cuaca simulasi tidak tersedia.');

            return [
                'mode' => 'simulation',
                'available' => true,
                'status' => 'simulated',
                'error_type' => null,
                'source' => 'SENTRA Demo Scenario',
                'observation_at' => CarbonImmutable::parse($scenario['simulation_started_at'])->utc()->toIso8601String(),
                'wind_speed_kmh' => (float) $wind['speed_kmh'],
                'wind_direction_degrees' => (float) $wind['direction_degrees'],
                'humidity_percent' => (float) $wind['humidity'],
                'metadata' => [
                    'dataset_type' => 'Simulated weather values',
                    'coordinates' => ['latitude' => $latitude, 'longitude' => $longitude],
                ],
                'message' => 'Angin dan kelembapan merupakan data simulasi SENTRA.',
            ];
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /** @return array<string, mixed> */
    private function unavailable(): array
    {
        return [
            'mode' => 'simulation',
            'available' => false,
            'status' => 'unavailable',
            'error_type' => 'malformed_response',
            'source' => 'SENTRA Demo Scenario',
            'observation_at' => null,
            'wind_speed_kmh' => null,
            'wind_direction_degrees' => null,
            'humidity_percent' => null,
            'metadata' => null,
            'message' => 'Data cuaca simulasi tidak tersedia.',
        ];
    }
}
