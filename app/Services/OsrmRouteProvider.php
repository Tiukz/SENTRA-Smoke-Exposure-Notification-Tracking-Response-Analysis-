<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RouteProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OsrmRouteProvider implements RouteProvider
{
    public function routes(array $origin, array $destination): array
    {
        $baseUrl = rtrim(trim((string) config('services.osrm.base_url')), '/');

        if ($baseUrl === '') {
            return $this->failure('unavailable', 'URL layanan OSRM belum dikonfigurasi.');
        }

        $coordinates = sprintf(
            '%.6f,%.6f;%.6f,%.6f',
            $origin['longitude'],
            $origin['latitude'],
            $destination['longitude'],
            $destination['latitude'],
        );

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.osrm.timeout_seconds'))
                ->retry(2, 250)
                ->get($baseUrl.'/'.$coordinates, [
                    'alternatives' => (int) config('services.osrm.max_routes'),
                    'steps' => 'false',
                    'overview' => 'full',
                    'geometries' => 'geojson',
                ])
                ->throw();
            $payload = $response->json();
        } catch (ConnectionException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'timed out') ? 'timeout' : 'unavailable';

            return $this->failure($status, $status === 'timeout' ? 'Permintaan OSRM melewati batas waktu.' : 'OSRM tidak dapat dihubungi.');
        } catch (RequestException) {
            return $this->failure('unavailable', 'Layanan OSRM sedang tidak tersedia.');
        } catch (Throwable) {
            return $this->failure('malformed_response', 'Respons OSRM tidak dapat diproses.');
        }

        if (! is_array($payload)) {
            return $this->failure('malformed_response', 'Respons OSRM bukan JSON yang valid.');
        }

        if (($payload['code'] ?? null) === 'NoRoute' || (($payload['code'] ?? null) === 'Ok' && ($payload['routes'] ?? []) === [])) {
            return $this->failure('no_route', 'OSRM tidak menemukan rute untuk titik tersebut.');
        }

        if (($payload['code'] ?? null) !== 'Ok' || ! is_array($payload['routes'] ?? null)) {
            return $this->failure('malformed_response', 'Struktur respons OSRM tidak valid.');
        }

        $routes = [];

        foreach (array_slice($payload['routes'], 0, min(3, (int) config('services.osrm.max_routes'))) as $route) {
            $normalized = $this->normalizeRoute($route, count($routes) + 1);

            if ($normalized !== null) {
                $routes[] = $normalized;
            }
        }

        if ($routes === []) {
            return $this->failure('malformed_response', 'OSRM tidak mengembalikan geometri rute yang valid.');
        }

        return [
            'available' => true,
            'status' => 'ok',
            'source' => 'OSRM',
            'routes' => $routes,
            'message' => count($routes).' alternatif rute valid diterima dari OSRM.',
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizeRoute(mixed $route, int $number): ?array
    {
        if (! is_array($route) || ! is_numeric($route['distance'] ?? null) || ! is_numeric($route['duration'] ?? null)
            || ($route['distance'] ?? 0) <= 0 || ($route['duration'] ?? 0) < 0
            || ($route['geometry']['type'] ?? null) !== 'LineString'
            || ! is_array($route['geometry']['coordinates'] ?? null)) {
            return null;
        }

        $geometry = [];

        foreach ($route['geometry']['coordinates'] as $coordinate) {
            if (! is_array($coordinate) || count($coordinate) < 2
                || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])
                || $coordinate[0] < -180 || $coordinate[0] > 180
                || $coordinate[1] < -90 || $coordinate[1] > 90) {
                return null;
            }

            $geometry[] = [
                'latitude' => (float) $coordinate[1],
                'longitude' => (float) $coordinate[0],
            ];
        }

        if (count($geometry) < 2) {
            return null;
        }

        return [
            'id' => 'route_'.$number,
            'distance_meters' => (float) $route['distance'],
            'duration_seconds' => (float) $route['duration'],
            'geometry' => $geometry,
            'source' => 'OSRM',
        ];
    }

    /** @return array<string, mixed> */
    private function failure(string $status, string $message): array
    {
        return [
            'available' => false,
            'status' => $status,
            'source' => 'OSRM',
            'routes' => [],
            'message' => $message,
        ];
    }
}
