<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FirmsHotspotSelectionService;
use Tests\TestCase;

final class FirmsHotspotSelectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firms.area' => '110.70,-3.60,115.90,0.80',
            'services.firms.minimum_confidence' => 30,
            'services.firms.recent_hours' => 24,
            'services.firms.minimum_separation_km' => 5,
            'services.firms.max_representative_hotspots' => 15,
        ]);
    }

    public function test_more_than_fifteen_records_are_limited_to_fifteen(): void
    {
        $records = [];

        for ($index = 0; $index < 20; $index++) {
            $records[] = $this->hotspot(latitude: -3.5 + ($index * 0.06));
        }

        $result = $this->service()->select($records);

        $this->assertCount(15, $result['hotspots']);
        $this->assertSame(20, $result['metadata']['raw_record_count']);
        $this->assertSame(15, $result['metadata']['selected_hotspot_count']);
    }

    public function test_spatially_close_records_are_deduplicated(): void
    {
        $records = [
            $this->hotspot(latitude: -2.0, longitude: 113.0, acquiredAt: '2026-09-20T03:00:00+00:00'),
            $this->hotspot(latitude: -2.001, longitude: 113.001, acquiredAt: '2026-09-20T02:00:00+00:00', frp: 99),
            $this->hotspot(latitude: -2.1, longitude: 113.1, acquiredAt: '2026-09-20T01:00:00+00:00'),
        ];

        $result = $this->service()->select($records);

        $this->assertCount(2, $result['hotspots']);
        $this->assertSame(-2.0, $result['hotspots'][0]['latitude']);
        $this->assertSame(-2.1, $result['hotspots'][1]['latitude']);
    }

    public function test_selection_order_is_deterministic(): void
    {
        $records = [
            $this->hotspot(latitude: -2.3, longitude: 113.3),
            $this->hotspot(latitude: -2.1, longitude: 113.1),
            $this->hotspot(latitude: -2.2, longitude: 113.2),
        ];

        $first = $this->service()->select($records)['hotspots'];
        $second = $this->service()->select(array_reverse($records))['hotspots'];

        $this->assertSame($first, $second);
        $this->assertSame([-2.3, -2.2, -2.1], array_column($first, 'latitude'));
    }

    public function test_fewer_than_fifteen_valid_records_are_all_returned(): void
    {
        $records = [
            $this->hotspot(latitude: -2.0),
            $this->hotspot(latitude: -2.1),
            $this->hotspot(latitude: -2.2),
        ];

        $result = $this->service()->select($records);

        $this->assertCount(3, $result['hotspots']);
        $this->assertSame(3, $result['metadata']['filtered_record_count']);
    }

    public function test_records_outside_central_kalimantan_are_removed(): void
    {
        $result = $this->service()->select([
            $this->hotspot(latitude: -2.0, longitude: 113.0),
            $this->hotspot(latitude: -6.2, longitude: 106.8),
        ]);

        $this->assertCount(1, $result['hotspots']);
        $this->assertSame(2, $result['metadata']['raw_record_count']);
        $this->assertSame(1, $result['metadata']['inside_region_count']);
    }

    public function test_newer_then_higher_frp_then_higher_confidence_are_prioritized(): void
    {
        config(['services.firms.minimum_separation_km' => 0]);
        $records = [
            $this->hotspot(latitude: -2.1, acquiredAt: '2026-09-20T02:00:00+00:00', frp: 1, confidence: 30),
            $this->hotspot(latitude: -2.2, acquiredAt: '2026-09-20T01:00:00+00:00', frp: 80, confidence: 90),
            $this->hotspot(latitude: -2.3, acquiredAt: '2026-09-20T02:00:00+00:00', frp: 50, confidence: 50),
            $this->hotspot(latitude: -2.4, acquiredAt: '2026-09-20T02:00:00+00:00', frp: 50, confidence: 90),
        ];

        $selected = $this->service()->select($records)['hotspots'];

        $this->assertSame([-2.4, -2.3, -2.1, -2.2], array_column($selected, 'latitude'));
    }

    private function service(): FirmsHotspotSelectionService
    {
        return app(FirmsHotspotSelectionService::class);
    }

    /** @return array<string, mixed> */
    private function hotspot(
        float $latitude,
        float $longitude = 113.0,
        string $acquiredAt = '2026-09-20T02:00:00+00:00',
        float $frp = 10,
        int $confidence = 70,
    ): array {
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'confidence' => $confidence,
            'frp' => $frp,
            'satellite' => 'N21',
            'instrument' => 'VIIRS',
            'acquired_at' => $acquiredAt,
            'source' => 'NASA FIRMS VIIRS_NOAA21_NRT',
        ];
    }
}
