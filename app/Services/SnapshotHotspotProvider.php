<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HotspotProvider;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final class SnapshotHotspotProvider implements HotspotProvider
{
    public function __construct(private readonly HotspotFreshnessService $freshnessService) {}

    public function get(): array
    {
        try {
            $dataset = json_decode(
                file_get_contents((string) config('services.real_snapshot.path')) ?: throw new RuntimeException('File snapshot tidak dapat dibaca.'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $metadata = $this->validateMetadata($dataset['metadata'] ?? null);
            $hotspots = $this->freshnessService->enrich(array_map(
                fn (mixed $hotspot): array => $this->normalize($hotspot),
                $dataset['hotspots'] ?? [],
            ));

            if ($hotspots === []) {
                throw new RuntimeException('Snapshot tidak memiliki observasi hotspot.');
            }
        } catch (Throwable) {
            return $this->unavailable(is_file((string) config('services.real_snapshot.path')) ? 'malformed_response' : 'missing_snapshot');
        }

        usort($hotspots, static fn (array $left, array $right): int => [
            $right['acquired_at'],
            $right['frp'] ?? -1,
            $right['confidence'],
        ] <=> [
            $left['acquired_at'],
            $left['frp'] ?? -1,
            $left['confidence'],
        ]);

        return [
            'mode' => 'snapshot',
            'available' => true,
            'status' => 'archived_snapshot',
            'source' => $metadata['hotspot_source'],
            'acquired_at' => $hotspots[0]['acquired_at'],
            'observation_age_minutes' => $hotspots[0]['observation_age_minutes'],
            'freshness_status' => $hotspots[0]['freshness_status'],
            'hotspot_count' => count($hotspots),
            'hotspots' => $hotspots,
            'primary_hotspot' => $hotspots[0],
            'metadata' => [
                ...$metadata,
                ...$this->freshnessService->observationRange($hotspots),
            ],
            'scenario_name' => 'Snapshot riil hotspot dan cuaca arsip Kalimantan Tengah',
            'disclaimer' => 'Hotspot adalah observasi arsip nyata NASA FIRMS, bukan data live atau near-real-time. Proyeksi asap dan hasil keputusan merupakan model prototipe, bukan peringatan resmi.',
            'message' => 'Snapshot riil NASA FIRMS dimuat dari arsip lokal tanpa koneksi jaringan.',
        ];
    }

    /** @return array<string, mixed> */
    private function validateMetadata(mixed $metadata): array
    {
        $required = ['captured_at', 'region', 'hotspot_source', 'weather_source', 'facility_source'];

        if (! is_array($metadata) || array_diff($required, array_keys($metadata)) !== []) {
            throw new RuntimeException('Metadata snapshot tidak valid.');
        }

        CarbonImmutable::parse($metadata['captured_at']);

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function normalize(mixed $hotspot): array
    {
        if (! is_array($hotspot)) {
            throw new RuntimeException('Observasi snapshot tidak valid.');
        }

        $latitude = filter_var($hotspot['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($hotspot['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $confidence = filter_var($hotspot['confidence'] ?? null, FILTER_VALIDATE_INT);

        if ($latitude === false || $longitude === false || $confidence === false
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
            || $confidence < 0 || $confidence > 100 || ! is_string($hotspot['acquired_at'] ?? null)
            || trim($hotspot['acquired_at']) === '') {
            throw new RuntimeException('Nilai observasi snapshot tidak valid.');
        }

        return [
            'latitude' => round((float) $latitude, 6),
            'longitude' => round((float) $longitude, 6),
            'confidence' => $confidence,
            'frp' => is_numeric($hotspot['frp'] ?? null) ? round((float) $hotspot['frp'], 2) : null,
            'satellite' => $this->nullableString($hotspot['satellite'] ?? null),
            'instrument' => $this->nullableString($hotspot['instrument'] ?? null),
            'acquired_at' => CarbonImmutable::parse($hotspot['acquired_at'] ?? null)->utc()->toIso8601String(),
            'source' => trim((string) ($hotspot['source'] ?? 'NASA FIRMS Archive')),
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $errorType): array
    {
        return [
            'mode' => 'snapshot',
            'available' => false,
            'status' => 'unavailable',
            'source' => 'NASA FIRMS Archive',
            'acquired_at' => null,
            'observation_age_minutes' => null,
            'freshness_status' => null,
            'hotspot_count' => 0,
            'hotspots' => [],
            'primary_hotspot' => null,
            'metadata' => null,
            'error_type' => $errorType,
            'message' => 'Snapshot riil tidak tersedia atau format file tidak valid. Pilih Simulasi atau Data Aktual untuk melanjutkan.',
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
