<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FacilityProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class LiveOsmFacilityProvider implements FacilityProvider
{
    public function get(float $latitude, float $longitude): array
    {
        try {
            $cacheKey = sprintf('sentra:osm-facilities:%.3f:%.3f', $latitude, $longitude);
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return [
                    ...$cached,
                    'status' => 'cached',
                    'facilities' => array_map(static fn (array $facility): array => [
                        ...$facility,
                        'status' => 'cached',
                    ], $cached['facilities']),
                    'message' => 'Fasilitas OSM dimuat dari cache lokal untuk menghindari permintaan API berulang.',
                ];
            }

            $baseUrl = trim((string) config('services.overpass.base_url'));

            if ($baseUrl === '') {
                return $this->unavailable('missing_configuration', 'URL Overpass belum dikonfigurasi.');
            }

            $radius = (int) config('services.overpass.radius_metres');
            $limit = (int) config('services.overpass.max_facilities');
            $query = sprintf(
                '[out:json][timeout:25];nwr["amenity"~"^(school|hospital|clinic|community_centre)$"]["name"](around:%d,%.6f,%.6f);out center %d;',
                $radius,
                $latitude,
                $longitude,
                max($limit * 5, 50),
            );
            $response = Http::asForm()
                ->acceptJson()
                ->withUserAgent('SENTRA-Prototype/1.0 (+'.config('app.url').')')
                ->timeout(30)
                ->retry(2, 750)
                ->post($baseUrl, ['data' => $query])
                ->throw();
            $retrievedAt = now('UTC')->toIso8601String();
            $facilities = $this->normalizeElements($response->json('elements'), $retrievedAt, $limit);

            if ($facilities === []) {
                return [
                    'mode' => 'live',
                    'available' => true,
                    'status' => 'no_facilities',
                    'error_type' => null,
                    'source' => 'OpenStreetMap via Overpass API',
                    'retrieved_at' => $retrievedAt,
                    'facility_count' => 0,
                    'facilities' => [],
                    'metadata' => ['query_center' => ['latitude' => $latitude, 'longitude' => $longitude]],
                    'message' => 'OpenStreetMap tersedia, tetapi tidak ada fasilitas bernama dalam radius pencarian.',
                ];
            }

            $result = [
                'mode' => 'live',
                'available' => true,
                'status' => 'live',
                'error_type' => null,
                'source' => 'OpenStreetMap via Overpass API',
                'retrieved_at' => $retrievedAt,
                'facility_count' => count($facilities),
                'facilities' => $facilities,
                'metadata' => [
                    'dataset_type' => 'Live OpenStreetMap facilities',
                    'query_center' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'radius_metres' => $radius,
                    'license' => 'OpenStreetMap data (c) OpenStreetMap contributors, ODbL',
                ],
                'message' => 'Fasilitas live berasal dari OpenStreetMap melalui Overpass API.',
            ];

            Cache::put($cacheKey, $result, (int) config('services.overpass.cache_seconds'));

            return $result;
        } catch (ConnectionException $exception) {
            return $this->failed('network_failure', 'Jaringan Overpass tidak dapat dihubungi.', $exception);
        } catch (RequestException $exception) {
            return $this->failed('api_failure', 'Overpass mengembalikan kegagalan API.', $exception);
        } catch (Throwable $exception) {
            return $this->failed('malformed_response', 'Respons Overpass tidak valid.', $exception);
        }
    }

    /** @return list<array<string, mixed>> */
    private function normalizeElements(mixed $elements, string $retrievedAt, int $limit): array
    {
        if (! is_array($elements)) {
            throw new RuntimeException('Respons Overpass tidak valid.');
        }

        $facilities = [];

        foreach ($elements as $element) {
            $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
            $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
            $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;
            $amenity = (string) ($tags['amenity'] ?? '');
            $type = $amenity === 'community_centre' ? 'residential' : $amenity;

            if (! is_numeric($latitude) || ! is_numeric($longitude)
                || ! in_array($type, ['school', 'hospital', 'clinic', 'residential'], true)
                || trim((string) ($tags['name'] ?? '')) === '' || ! isset($element['type'], $element['id'])) {
                continue;
            }

            $id = $element['type'].'/'.$element['id'];
            $facilities[$id] = [
                'id' => $id,
                'name' => trim((string) $tags['name']),
                'type' => $type,
                'latitude' => round((float) $latitude, 7),
                'longitude' => round((float) $longitude, 7),
                'source' => 'OpenStreetMap',
                'retrieved_at' => $retrievedAt,
                'status' => 'live',
            ];
        }

        if ($elements !== [] && $facilities === []) {
            throw new RuntimeException('Elemen fasilitas Overpass tidak memiliki struktur yang valid.');
        }

        $priority = ['hospital' => 1, 'school' => 2, 'clinic' => 3, 'residential' => 4];
        $facilities = array_values($facilities);
        usort($facilities, static fn (array $left, array $right): int => [
            $priority[$left['type']],
            strtolower($left['name']),
        ] <=> [
            $priority[$right['type']],
            strtolower($right['name']),
        ]);

        return array_slice($facilities, 0, $limit);
    }

    /** @return array<string, mixed> */
    private function failed(string $errorType, string $message, Throwable $exception): array
    {
        Log::warning('Pengambilan fasilitas live OSM gagal.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return $this->unavailable($errorType, $message);
    }

    /** @return array<string, mixed> */
    private function unavailable(string $errorType, string $message): array
    {
        return [
            'mode' => 'live',
            'available' => false,
            'status' => 'unavailable',
            'error_type' => $errorType,
            'source' => 'OpenStreetMap via Overpass API',
            'retrieved_at' => null,
            'facility_count' => 0,
            'facilities' => [],
            'metadata' => null,
            'message' => $message.' SENTRA tidak menggantinya dengan fasilitas simulasi.',
        ];
    }
}
