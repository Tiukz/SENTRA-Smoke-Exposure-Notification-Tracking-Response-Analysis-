<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WeatherProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class LiveWeatherProvider implements WeatherProvider
{
    public function get(float $latitude, float $longitude): array
    {
        try {
            $baseUrl = trim((string) config('services.open_meteo.base_url'));

            if ($baseUrl === '') {
                return $this->unavailable('missing_configuration', 'URL Open-Meteo belum dikonfigurasi.');
            }

            $response = Http::acceptJson()
                ->timeout(15)
                ->retry(2, 300)
                ->get($baseUrl, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'relative_humidity_2m,wind_speed_10m,wind_direction_10m',
                    'wind_speed_unit' => 'kmh',
                    'timezone' => 'UTC',
                ])
                ->throw();

            $weather = $this->normalize($response->json('current'));

            return [
                'mode' => 'live',
                'available' => true,
                'status' => 'live',
                'error_type' => null,
                'source' => 'Open-Meteo Weather API',
                ...$weather,
                'metadata' => [
                    'dataset_type' => 'Current model weather conditions',
                    'requested_coordinates' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'retrieved_at' => now('UTC')->toIso8601String(),
                ],
                'message' => 'Cuaca terkini berasal dari Open-Meteo untuk koordinat hotspot utama.',
            ];
        } catch (ConnectionException) {
            return $this->unavailable('network_failure', 'Jaringan cuaca live tidak dapat dihubungi.');
        } catch (RequestException) {
            return $this->unavailable('api_failure', 'Open-Meteo mengembalikan kegagalan API.');
        } catch (Throwable) {
            return $this->unavailable('malformed_response', 'Respons cuaca live tidak valid.');
        }
    }

    /** @return array{observation_at: string, wind_speed_kmh: float, wind_direction_degrees: float, humidity_percent: float} */
    private function normalize(mixed $current): array
    {
        if (! is_array($current)) {
            throw new RuntimeException('Respons cuaca live tidak valid.');
        }

        $windSpeed = filter_var($current['wind_speed_10m'] ?? null, FILTER_VALIDATE_FLOAT);
        $windDirection = filter_var($current['wind_direction_10m'] ?? null, FILTER_VALIDATE_FLOAT);
        $humidity = filter_var($current['relative_humidity_2m'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($windSpeed === false || $windSpeed < 0 || $windDirection === false
            || $windDirection < 0 || $windDirection > 360 || $humidity === false
            || $humidity < 0 || $humidity > 100 || ! is_string($current['time'] ?? null)) {
            throw new RuntimeException('Nilai cuaca live tidak valid.');
        }

        return [
            'observation_at' => CarbonImmutable::parse($current['time'], 'UTC')->utc()->toIso8601String(),
            'wind_speed_kmh' => round((float) $windSpeed, 2),
            'wind_direction_degrees' => round(fmod((float) $windDirection, 360.0), 2),
            'humidity_percent' => round((float) $humidity, 1),
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $errorType, string $reason): array
    {
        return [
            'mode' => 'live',
            'available' => false,
            'status' => 'unavailable',
            'error_type' => $errorType,
            'source' => 'Open-Meteo Weather API',
            'observation_at' => null,
            'wind_speed_kmh' => null,
            'wind_direction_degrees' => null,
            'humidity_percent' => null,
            'metadata' => null,
            'message' => $reason.' SENTRA tidak menggantinya dengan cuaca simulasi.',
        ];
    }
}
