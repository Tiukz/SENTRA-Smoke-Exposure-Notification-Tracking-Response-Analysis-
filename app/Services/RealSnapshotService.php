<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class RealSnapshotService
{
    public function __construct(
        private readonly FirmsHotspotProvider $hotspotProvider,
        private readonly LiveWeatherProvider $weatherProvider,
        private readonly LiveOsmFacilityProvider $facilityProvider,
        private readonly Filesystem $files,
    ) {}

    /** @return array<string, mixed> */
    public function refresh(): array
    {
        $hotspotData = $this->hotspotProvider->get();
        $primaryHotspot = $hotspotData['primary_hotspot'] ?? null;

        if (! ($hotspotData['available'] ?? false) || ! is_array($primaryHotspot)) {
            throw new RuntimeException('Snapshot dibatalkan: hotspot live tidak valid. '.$hotspotData['message']);
        }

        $latitude = (float) $primaryHotspot['latitude'];
        $longitude = (float) $primaryHotspot['longitude'];
        $weatherData = $this->weatherProvider->get($latitude, $longitude);

        if (! ($weatherData['available'] ?? false)) {
            throw new RuntimeException('Snapshot dibatalkan: cuaca live tidak valid. '.$weatherData['message']);
        }

        $facilityData = $this->facilityProvider->get($latitude, $longitude);

        if (! ($facilityData['available'] ?? false) || ($facilityData['facilities'] ?? []) === []) {
            throw new RuntimeException('Snapshot dibatalkan: fasilitas live tidak valid. '.$facilityData['message']);
        }

        $capturedAt = now('UTC')->toIso8601String();
        $snapshot = [
            'metadata' => [
                'captured_at' => $capturedAt,
                'region' => 'Kalimantan Tengah',
                'hotspot_source' => $hotspotData['source'],
                'weather_source' => $weatherData['source'],
                'facility_source' => $facilityData['source'],
                'hotspot_metadata' => $hotspotData['metadata'] ?? null,
                'weather_metadata' => $weatherData['metadata'] ?? null,
                'facility_metadata' => $facilityData['metadata'] ?? null,
            ],
            'hotspots' => $hotspotData['hotspots'],
            'weather' => [
                'source' => $weatherData['source'],
                'observation_at' => $weatherData['observation_at'],
                'wind_speed_kmh' => $weatherData['wind_speed_kmh'],
                'wind_direction_degrees' => $weatherData['wind_direction_degrees'],
                'humidity_percent' => $weatherData['humidity_percent'],
            ],
            'facilities' => $facilityData['facilities'],
        ];
        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $path = (string) config('services.real_snapshot.path');

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->replace($path, $json);

        return $snapshot;
    }
}
