<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HotspotObservationStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class FirmsHotspotService
{
    public const ERROR_MISSING_CONFIGURATION = 1001;

    public const ERROR_NETWORK = 1002;

    public const ERROR_API = 1003;

    public const ERROR_MALFORMED_RESPONSE = 1004;

    public function __construct(
        private readonly HotspotObservationStore $hotspotStore,
        private readonly FirmsHotspotSelectionService $selectionService,
    ) {}

    /** @return list<array<string, mixed>> */
    public function fetchAndStore(): array
    {
        return $this->fetchAndStoreWithMetadata()['hotspots'];
    }

    /**
     * @return array{hotspots: list<array<string, mixed>>, metadata: array<string, int|float>}
     */
    public function fetchAndStoreWithMetadata(): array
    {
        $apiKey = trim((string) config('services.firms.map_key'));

        if ($apiKey === '') {
            throw new RuntimeException('Kunci NASA FIRMS belum dikonfigurasi.', self::ERROR_MISSING_CONFIGURATION);
        }

        $url = implode('/', [
            rtrim((string) config('services.firms.base_url'), '/'),
            rawurlencode($apiKey),
            rawurlencode((string) config('services.firms.source')),
            (string) config('services.firms.area'),
            (string) config('services.firms.day_range'),
        ]);

        try {
            $response = Http::accept('text/csv')
                ->timeout(20)
                ->retry(2, 300)
                ->get($url)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Jaringan NASA FIRMS tidak dapat dihubungi saat ini.', self::ERROR_NETWORK, $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException('NASA FIRMS mengembalikan kegagalan API.', self::ERROR_API, $exception);
        }

        try {
            $selection = $this->selectionService->select($this->parseCsv($response->body()));
        } catch (Throwable $exception) {
            throw new RuntimeException('Respons NASA FIRMS tidak valid.', self::ERROR_MALFORMED_RESPONSE, $exception);
        }

        foreach ($selection['hotspots'] as $hotspot) {
            $this->hotspotStore->save($hotspot);
        }

        return $selection;
    }

    /** @return list<array<string, mixed>> */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Respons NASA FIRMS tidak dapat diproses.');
        }

        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $requiredHeaders = ['latitude', 'longitude', 'acq_date', 'acq_time', 'confidence'];

        if (! is_array($headers) || array_diff($requiredHeaders, $headers) !== []) {
            fclose($stream);
            throw new RuntimeException('Format respons NASA FIRMS tidak valid.');
        }

        $hotspots = [];

        while (($values = fgetcsv($stream, escape: '')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            if (count($values) !== count($headers)) {
                fclose($stream);
                throw new RuntimeException('Baris data NASA FIRMS tidak valid.');
            }

            $hotspots[] = $this->normalize(array_combine($headers, $values));
        }

        fclose($stream);

        return $hotspots;
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>
     */
    private function normalize(array $record): array
    {
        $latitude = filter_var($record['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($record['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('Koordinat NASA FIRMS tidak valid.');
        }

        try {
            $acquiredAt = CarbonImmutable::createFromFormat(
                '!Y-m-d Hi',
                trim((string) ($record['acq_date'] ?? '')).' '.str_pad(trim((string) ($record['acq_time'] ?? '')), 4, '0', STR_PAD_LEFT),
                'UTC',
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('Waktu akuisisi NASA FIRMS tidak valid.', previous: $exception);
        }

        if ($acquiredAt === false) {
            throw new RuntimeException('Waktu akuisisi NASA FIRMS tidak valid.');
        }

        return [
            'latitude' => round((float) $latitude, 6),
            'longitude' => round((float) $longitude, 6),
            'confidence' => $this->normalizeConfidence($record['confidence'] ?? ''),
            'frp' => is_numeric($record['frp'] ?? null) ? round((float) $record['frp'], 2) : null,
            'satellite' => $this->nullableString($record['satellite'] ?? null),
            'instrument' => $this->nullableString($record['instrument'] ?? null),
            'acquired_at' => $acquiredAt->toIso8601String(),
            'source' => 'NASA FIRMS '.(string) config('services.firms.source'),
        ];
    }

    private function normalizeConfidence(string $confidence): int
    {
        if (is_numeric($confidence)) {
            return max(0, min(100, (int) round((float) $confidence)));
        }

        return match (strtolower(trim($confidence))) {
            'h', 'high' => 90,
            'n', 'nominal' => 70,
            'l', 'low' => 30,
            default => throw new RuntimeException('Nilai confidence NASA FIRMS tidak valid.'),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
