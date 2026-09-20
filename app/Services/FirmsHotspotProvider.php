<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HotspotProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FirmsHotspotProvider implements HotspotProvider
{
    public function __construct(
        private readonly FirmsHotspotService $firmsHotspotService,
        private readonly HotspotFreshnessService $freshnessService,
    ) {}

    public function get(): array
    {
        try {
            $selection = $this->firmsHotspotService->fetchAndStoreWithMetadata();
            $hotspots = $this->freshnessService->enrich($selection['hotspots']);
        } catch (Throwable $exception) {
            Log::warning('Pengambilan hotspot live NASA FIRMS gagal.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
            ]);

            return [
                'mode' => 'live',
                'available' => false,
                'status' => 'unavailable',
                'source' => 'NASA FIRMS',
                'acquired_at' => null,
                'hotspot_count' => 0,
                'hotspots' => [],
                'primary_hotspot' => null,
                'observation_age_minutes' => null,
                'freshness_status' => null,
                'error_type' => $this->errorType($exception),
                'message' => 'Data NASA FIRMS tidak tersedia. Gunakan Mode Simulasi untuk melanjutkan.',
            ];
        }

        return [
            'mode' => 'live',
            'available' => true,
            'status' => $hotspots === [] ? 'no_hotspots' : 'near_real_time',
            'error_type' => null,
            'source' => 'NASA FIRMS '.(string) config('services.firms.source'),
            'acquired_at' => $hotspots[0]['acquired_at'] ?? null,
            'observation_age_minutes' => $hotspots[0]['observation_age_minutes'] ?? null,
            'freshness_status' => $hotspots[0]['freshness_status'] ?? null,
            'hotspot_count' => count($hotspots),
            'hotspots' => $hotspots,
            'primary_hotspot' => $hotspots[0] ?? null,
            'metadata' => [
                'source' => 'NASA FIRMS',
                'dataset_type' => 'Near-real-time active fire observations',
                'region' => 'Central Kalimantan, Indonesia',
                'sensor_source_dataset' => (string) config('services.firms.source'),
                'retrieved_at' => now('UTC')->toIso8601String(),
                'observation_date_range' => null,
                ...$selection['metadata'],
                ...$this->freshnessService->observationRange($hotspots),
            ],
            'scenario_name' => 'Hotspot NASA FIRMS dan cuaca terkini',
            'disclaimer' => 'Hotspot berasal dari NASA FIRMS near-real-time. Proyeksi asap dan hasil keputusan merupakan model prototipe, bukan prakiraan atmosfer atau peringatan resmi.',
            'message' => $hotspots === []
                ? 'NASA FIRMS tersedia, tetapi tidak ada hotspot pada area dan periode yang diminta.'
                : 'Hotspot berasal dari NASA FIRMS near-real-time.',
        ];
    }

    private function errorType(Throwable $exception): string
    {
        return match ($exception->getCode()) {
            FirmsHotspotService::ERROR_MISSING_CONFIGURATION => 'missing_configuration',
            FirmsHotspotService::ERROR_NETWORK => 'network_failure',
            FirmsHotspotService::ERROR_API => 'api_failure',
            default => 'malformed_response',
        };
    }
}
