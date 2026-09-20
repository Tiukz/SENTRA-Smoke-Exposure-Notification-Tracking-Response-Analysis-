<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WeatherProvider;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final class SnapshotWeatherProvider implements WeatherProvider
{
    public function get(float $latitude, float $longitude): array
    {
        try {
            $contents = file_get_contents((string) config('services.real_snapshot.path'));

            if ($contents === false) {
                throw new RuntimeException('File cuaca arsip tidak dapat dibaca.');
            }

            $dataset = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $metadata = $this->validateMetadata($dataset['metadata'] ?? null);
            $weather = $this->normalize($dataset['weather'] ?? null);

            return [
                'mode' => 'snapshot',
                'available' => true,
                'status' => 'archived_snapshot',
                'error_type' => null,
                ...$weather,
                'metadata' => [
                    ...$metadata,
                    'source' => $metadata['weather_source'],
                    'retrieved_at' => $metadata['captured_at'],
                ],
                'message' => 'Cuaca arsip berasal dari reanalysis historis Open-Meteo dan tersedia tanpa jaringan.',
            ];
        } catch (Throwable) {
            return $this->unavailable(is_file((string) config('services.real_snapshot.path')) ? 'malformed_response' : 'missing_snapshot');
        }
    }

    /** @return array<string, mixed> */
    private function validateMetadata(mixed $metadata): array
    {
        $required = ['captured_at', 'region', 'hotspot_source', 'weather_source', 'facility_source'];

        if (! is_array($metadata) || array_diff($required, array_keys($metadata)) !== []) {
            throw new RuntimeException('Metadata cuaca arsip tidak valid.');
        }

        CarbonImmutable::parse($metadata['captured_at']);

        return $metadata;
    }

    /** @return array{source: string, observation_at: string, wind_speed_kmh: float, wind_direction_degrees: float, humidity_percent: float} */
    private function normalize(mixed $weather): array
    {
        if (! is_array($weather)) {
            throw new RuntimeException('Data cuaca arsip tidak valid.');
        }

        $windSpeed = filter_var($weather['wind_speed_kmh'] ?? null, FILTER_VALIDATE_FLOAT);
        $windDirection = filter_var($weather['wind_direction_degrees'] ?? null, FILTER_VALIDATE_FLOAT);
        $humidity = filter_var($weather['humidity_percent'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($windSpeed === false || $windSpeed < 0 || $windDirection === false
            || $windDirection < 0 || $windDirection > 360 || $humidity === false
            || $humidity < 0 || $humidity > 100 || ! is_string($weather['observation_at'] ?? null)) {
            throw new RuntimeException('Nilai cuaca arsip tidak valid.');
        }

        return [
            'source' => trim((string) ($weather['source'] ?? 'Open-Meteo Historical Weather API')),
            'observation_at' => CarbonImmutable::parse($weather['observation_at'])->utc()->toIso8601String(),
            'wind_speed_kmh' => round((float) $windSpeed, 2),
            'wind_direction_degrees' => round(fmod((float) $windDirection, 360.0), 2),
            'humidity_percent' => round((float) $humidity, 1),
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $errorType): array
    {
        return [
            'mode' => 'snapshot',
            'available' => false,
            'status' => 'unavailable',
            'error_type' => $errorType,
            'source' => 'Open-Meteo Historical Weather API',
            'observation_at' => null,
            'wind_speed_kmh' => null,
            'wind_direction_degrees' => null,
            'humidity_percent' => null,
            'metadata' => null,
            'message' => 'Snapshot cuaca riil tidak tersedia atau format file tidak valid.',
        ];
    }
}
