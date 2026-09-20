<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FacilityProvider;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final class SnapshotFacilityProvider implements FacilityProvider
{
    public function get(float $latitude, float $longitude): array
    {
        try {
            $contents = file_get_contents((string) config('services.real_snapshot.path'));

            if ($contents === false) {
                throw new RuntimeException('File snapshot fasilitas tidak dapat dibaca.');
            }

            $dataset = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $metadata = $this->validateMetadata($dataset['metadata'] ?? null);
            $facilities = array_map(
                fn (mixed $facility): array => $this->normalize($facility),
                $dataset['facilities'] ?? [],
            );

            if ($facilities === []) {
                throw new RuntimeException('Snapshot fasilitas kosong.');
            }

            return [
                'mode' => 'snapshot',
                'available' => true,
                'status' => 'archived_snapshot',
                'source' => $metadata['facility_source'],
                'retrieved_at' => CarbonImmutable::parse($metadata['captured_at'])->utc()->toIso8601String(),
                'facility_count' => count($facilities),
                'facilities' => $facilities,
                'metadata' => [
                    ...$metadata,
                    'source' => $metadata['facility_source'],
                    'retrieved_at' => $metadata['captured_at'],
                ],
                'message' => 'Fasilitas arsip OpenStreetMap dimuat dari repository tanpa jaringan.',
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
            throw new RuntimeException('Metadata snapshot fasilitas tidak valid.');
        }

        CarbonImmutable::parse($metadata['captured_at']);

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function normalize(mixed $facility): array
    {
        if (! is_array($facility)) {
            throw new RuntimeException('Data fasilitas tidak valid.');
        }

        $latitude = filter_var($facility['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($facility['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $type = strtolower(trim((string) ($facility['type'] ?? '')));
        $retrievedAt = trim((string) ($facility['retrieved_at'] ?? ''));
        $source = trim((string) ($facility['source'] ?? ''));

        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180
            || ! in_array($type, ['school', 'hospital', 'clinic', 'residential'], true)
            || trim((string) ($facility['id'] ?? '')) === '' || trim((string) ($facility['name'] ?? '')) === ''
            || $source === '' || $retrievedAt === '') {
            throw new RuntimeException('Nilai fasilitas tidak valid.');
        }

        $retrievedAt = CarbonImmutable::parse($retrievedAt)->utc()->toIso8601String();

        return [
            'id' => (string) $facility['id'],
            'name' => (string) $facility['name'],
            'type' => $type,
            'latitude' => round((float) $latitude, 7),
            'longitude' => round((float) $longitude, 7),
            'source' => $source,
            'retrieved_at' => $retrievedAt,
            'status' => 'archived_snapshot',
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $errorType): array
    {
        return [
            'mode' => 'snapshot',
            'available' => false,
            'status' => 'unavailable',
            'source' => 'OpenStreetMap snapshot',
            'retrieved_at' => null,
            'facility_count' => 0,
            'facilities' => [],
            'metadata' => null,
            'error_type' => $errorType,
            'message' => 'Snapshot fasilitas OSM tidak tersedia atau format file tidak valid.',
        ];
    }
}
