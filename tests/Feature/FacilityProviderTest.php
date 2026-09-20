<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HotspotObservationStore;
use App\Services\LiveOsmFacilityProvider;
use App\Services\SimulationFacilityProvider;
use App\Services\SnapshotFacilityProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FacilityProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->app->instance(HotspotObservationStore::class, new FacilityTestHotspotStore);
        config([
            'services.overpass.base_url' => 'https://overpass.example/api/interpreter',
            'services.overpass.radius_metres' => 30000,
            'services.overpass.max_facilities' => 12,
            'services.overpass.cache_seconds' => 21600,
        ]);
    }

    public function test_snapshot_facilities_run_offline(): void
    {
        Http::preventStrayRequests();

        $result = app(SnapshotFacilityProvider::class)->get(-2.57, 110.85);

        $this->assertTrue($result['available']);
        $this->assertSame('snapshot', $result['mode']);
        $this->assertSame('archived_snapshot', $result['status']);
        $this->assertSame('OpenStreetMap via Overpass API', $result['source']);
        $this->assertSame(12, $result['facility_count']);
        $this->assertNotEmpty($result['facilities'][0]['name']);
        Http::assertNothingSent();
    }

    public function test_snapshot_dashboard_shows_real_facility_names_and_provenance(): void
    {
        Http::preventStrayRequests();

        $this->get('/sentra')
            ->assertOk()
            ->assertSee('OpenStreetMap via Overpass API')
            ->assertSee('Fasilitas:');

        Http::assertNothingSent();
    }

    public function test_live_osm_success_normalizes_prioritized_facilities(): void
    {
        Http::fake(['overpass.example/*' => Http::response($this->overpassResponse())]);

        $result = app(LiveOsmFacilityProvider::class)->get(-2.21, 113.92);

        $this->assertTrue($result['available']);
        $this->assertSame('live', $result['status']);
        $this->assertSame(4, $result['facility_count']);
        $this->assertSame(['hospital', 'school', 'clinic', 'residential'], array_column($result['facilities'], 'type'));
        $this->assertSame('way/20', $result['facilities'][1]['id']);
        Http::assertSent(fn ($request): bool => str_contains((string) $request['data'], 'community_centre')
            && str_contains((string) $request['data'], 'around:30000,-2.210000,113.920000'));
    }

    public function test_live_osm_failure_is_explicit(): void
    {
        Http::fake(['overpass.example/*' => Http::response('unavailable', 503)]);

        $result = app(LiveOsmFacilityProvider::class)->get(-2.21, 113.92);

        $this->assertFalse($result['available']);
        $this->assertSame('live', $result['mode']);
        $this->assertSame('unavailable', $result['status']);
        $this->assertSame('api_failure', $result['error_type']);
        $this->assertSame([], $result['facilities']);
        $this->assertStringContainsString('tidak menggantinya', $result['message']);
    }

    public function test_live_osm_retries_once_after_temporary_failure(): void
    {
        Http::fake([
            'overpass.example/*' => Http::sequence()
                ->push('temporary failure', 503)
                ->push($this->overpassResponse()),
        ]);

        $result = app(LiveOsmFacilityProvider::class)->get(-2.21, 113.92);

        $this->assertTrue($result['available']);
        $this->assertSame('live', $result['status']);
        Http::assertSentCount(2);
    }

    public function test_simulation_facilities_are_unchanged(): void
    {
        $result = app(SimulationFacilityProvider::class)->get(-2.36, 113.78);

        $this->assertTrue($result['available']);
        $this->assertSame('simulated', $result['status']);
        $this->assertSame(3, $result['facility_count']);
        $this->assertSame(['School A', 'Hospital B', 'Residential Area C'], array_column($result['facilities'], 'name'));
        $this->assertSame(['school', 'hospital', 'residential'], array_column($result['facilities'], 'type'));
    }

    public function test_all_facility_providers_use_the_same_normalized_structure(): void
    {
        Http::fake(['overpass.example/*' => Http::response($this->overpassResponse())]);
        $expectedKeys = ['id', 'name', 'type', 'latitude', 'longitude', 'source', 'retrieved_at', 'status'];

        $snapshot = app(SnapshotFacilityProvider::class)->get(-2.21, 113.92);
        $live = app(LiveOsmFacilityProvider::class)->get(-2.21, 113.92);
        $simulation = app(SimulationFacilityProvider::class)->get(-2.21, 113.92);

        $this->assertSame($expectedKeys, array_keys($snapshot['facilities'][0]));
        $this->assertSame($expectedKeys, array_keys($live['facilities'][0]));
        $this->assertSame($expectedKeys, array_keys($simulation['facilities'][0]));
    }

    public function test_live_mode_does_not_fallback_when_osm_is_unavailable(): void
    {
        config([
            'services.firms.map_key' => 'test-key',
            'services.firms.base_url' => 'https://firms.example/api/area/csv',
            'services.firms.source' => 'VIIRS_NOAA21_NRT',
            'services.firms.area' => '110.70,-3.60,115.90,0.80',
            'services.firms.day_range' => 1,
            'services.open_meteo.base_url' => 'https://weather.example/v1/forecast',
        ]);
        Http::fake([
            'firms.example/*' => Http::response("latitude,longitude,acq_date,acq_time,satellite,instrument,confidence,frp\n-2.25,113.90,2026-09-20,0130,N21,VIIRS,h,12.5"),
            'weather.example/*' => Http::response(['current' => [
                'time' => '2026-09-20T02:00',
                'relative_humidity_2m' => 61,
                'wind_speed_10m' => 14.4,
                'wind_direction_10m' => 80,
            ]]),
            'overpass.example/*' => Http::response('unavailable', 503),
        ]);

        $this->postJson('/sentra/data-mode', ['mode' => 'live'])
            ->assertServiceUnavailable()
            ->assertJsonPath('data_mode.status', 'near_real_time')
            ->assertJsonPath('weather_data.status', 'live')
            ->assertJsonPath('facility_data.status', 'unavailable')
            ->assertJsonPath('facility_data.facility_count', 0)
            ->assertJsonMissingPath('scenario');
    }

    public function test_live_facilities_are_cached_between_requests(): void
    {
        Http::fake(['overpass.example/*' => Http::response($this->overpassResponse())]);
        $provider = app(LiveOsmFacilityProvider::class);

        $first = $provider->get(-2.21, 113.92);
        $second = $provider->get(-2.21, 113.92);

        $this->assertSame('live', $first['status']);
        $this->assertSame('cached', $second['status']);
        $this->assertSame('cached', $second['facilities'][0]['status']);
        $this->assertSame($first['facility_count'], $second['facility_count']);
        Http::assertSentCount(1);
    }

    /** @return array<string, mixed> */
    private function overpassResponse(): array
    {
        return [
            'elements' => [
                ['type' => 'node', 'id' => 40, 'lat' => -2.24, 'lon' => 113.93, 'tags' => ['amenity' => 'community_centre', 'name' => 'Balai Warga']],
                ['type' => 'node', 'id' => 10, 'lat' => -2.21, 'lon' => 113.92, 'tags' => ['amenity' => 'hospital', 'name' => 'RS OSM']],
                ['type' => 'way', 'id' => 20, 'center' => ['lat' => -2.22, 'lon' => 113.91], 'tags' => ['amenity' => 'school', 'name' => 'Sekolah OSM']],
                ['type' => 'node', 'id' => 30, 'lat' => -2.23, 'lon' => 113.94, 'tags' => ['amenity' => 'clinic', 'name' => 'Klinik OSM']],
            ],
        ];
    }
}

final class FacilityTestHotspotStore implements HotspotObservationStore
{
    public function save(array $hotspot): void {}
}
