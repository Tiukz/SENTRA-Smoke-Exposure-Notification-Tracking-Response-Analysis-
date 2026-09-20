<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HotspotObservationStore;
use App\Services\FirmsHotspotProvider;
use App\Services\FirmsHotspotService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FirmsLiveDataTest extends TestCase
{
    private InMemoryHotspotObservationStore $hotspotStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotspotStore = new InMemoryHotspotObservationStore;
        $this->app->instance(HotspotObservationStore::class, $this->hotspotStore);

        config([
            'services.firms.map_key' => 'test-map-key',
            'services.firms.base_url' => 'https://firms.example/api/area/csv',
            'services.firms.source' => 'VIIRS_NOAA21_NRT',
            'services.firms.area' => '110.70,-3.60,115.90,0.80',
            'services.firms.day_range' => 1,
            'services.firms.minimum_confidence' => 30,
            'services.firms.recent_hours' => 24,
            'services.firms.minimum_separation_km' => 5,
            'services.firms.max_representative_hotspots' => 15,
            'services.open_meteo.base_url' => 'https://weather.example/v1/forecast',
            'services.overpass.base_url' => 'https://overpass.example/api/interpreter',
            'services.overpass.radius_metres' => 30000,
            'services.overpass.max_facilities' => 12,
            'services.overpass.cache_seconds' => 21600,
        ]);
    }

    public function test_successful_firms_response_is_ingested(): void
    {
        Http::fake(['firms.example/*' => Http::response($this->validCsv(), 200)]);

        $hotspots = app(FirmsHotspotService::class)->fetchAndStore();

        $this->assertCount(1, $hotspots);
        $this->assertCount(1, $this->hotspotStore->records);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'test-map-key/VIIRS_NOAA21_NRT/110.70,-3.60,115.90,0.80/1'));
    }

    public function test_api_failure_returns_explicit_unavailable_state(): void
    {
        Http::fake(['firms.example/*' => Http::response('service unavailable', 503)]);

        $this->postJson('/sentra/data-mode', ['mode' => 'live'])
            ->assertServiceUnavailable()
            ->assertJsonPath('data_mode.mode', 'live')
            ->assertJsonPath('data_mode.available', false)
            ->assertJsonPath('data_mode.status', 'unavailable')
            ->assertJsonPath('data_mode.error_type', 'api_failure')
            ->assertJsonPath('data_mode.source', 'NASA FIRMS')
            ->assertJsonPath('data_mode.hotspot_count', 0);
    }

    public function test_network_failure_is_distinct_from_api_failure(): void
    {
        Http::fake(['firms.example/*' => Http::failedConnection('network down')]);

        $result = app(FirmsHotspotProvider::class)->get();

        $this->assertFalse($result['available']);
        $this->assertSame('network_failure', $result['error_type']);
    }

    public function test_invalid_firms_response_returns_explicit_unavailable_state(): void
    {
        Http::fake(['firms.example/*' => Http::response("not,the,expected,headers\n1,2,3,4", 200)]);

        $this->postJson('/sentra/data-mode', ['mode' => 'live'])
            ->assertServiceUnavailable()
            ->assertJsonPath('data_mode.available', false)
            ->assertJsonPath('data_mode.status', 'unavailable')
            ->assertJsonPath('data_mode.error_type', 'malformed_response');
    }

    public function test_firms_record_is_normalized(): void
    {
        Http::fake(['firms.example/*' => Http::response($this->validCsv(), 200)]);

        $hotspot = app(FirmsHotspotService::class)->fetchAndStore()[0];

        $this->assertSame(-2.250123, $hotspot['latitude']);
        $this->assertSame(113.901234, $hotspot['longitude']);
        $this->assertSame(90, $hotspot['confidence']);
        $this->assertSame(12.5, $hotspot['frp']);
        $this->assertSame('N21', $hotspot['satellite']);
        $this->assertSame('VIIRS', $hotspot['instrument']);
        $this->assertSame('2026-09-20T01:30:00+00:00', $hotspot['acquired_at']);
        $this->assertSame('NASA FIRMS VIIRS_NOAA21_NRT', $hotspot['source']);
    }

    public function test_duplicate_observation_is_not_inserted_twice(): void
    {
        Http::fake(['firms.example/*' => Http::response($this->validCsv(), 200)]);
        $service = app(FirmsHotspotService::class);

        $service->fetchAndStore();
        $service->fetchAndStore();

        $this->assertCount(1, $this->hotspotStore->records);
    }

    public function test_simulation_mode_remains_unchanged(): void
    {
        $this->postJson('/sentra/data-mode', ['mode' => 'simulation'])
            ->assertOk()
            ->assertJsonPath('simulation_only', true)
            ->assertJsonPath('data_mode.mode', 'simulation')
            ->assertJsonPath('scenario.hotspot_cluster.latitude', -2.36)
            ->assertJsonPath('scenario.hotspot_cluster.longitude', 113.78)
            ->assertJsonPath('projection.projected_travel_distance_km', 40)
            ->assertJsonPath('facility_results.0.risk_level', 'HIGH');
    }

    public function test_live_mode_identifies_source_and_recomputes_on_server(): void
    {
        Http::fake([
            'firms.example/*' => Http::response($this->validCsv(), 200),
            'weather.example/*' => Http::response([
                'current' => [
                    'time' => '2026-09-20T02:00',
                    'relative_humidity_2m' => 61,
                    'wind_speed_10m' => 14.4,
                    'wind_direction_10m' => 80,
                ],
            ]),
            'overpass.example/*' => Http::response([
                'elements' => [[
                    'type' => 'node',
                    'id' => 123,
                    'lat' => -2.24,
                    'lon' => 113.92,
                    'tags' => ['amenity' => 'hospital', 'name' => 'Rumah Sakit OSM'],
                ]],
            ]),
        ]);

        $response = $this->postJson('/sentra/data-mode', ['mode' => 'live'])
            ->assertOk()
            ->assertJsonPath('simulation_only', false)
            ->assertJsonPath('data_mode.mode', 'live')
            ->assertJsonPath('data_mode.available', true)
            ->assertJsonPath('data_mode.status', 'near_real_time')
            ->assertJsonPath('data_mode.source', 'NASA FIRMS VIIRS_NOAA21_NRT')
            ->assertJsonPath('data_mode.hotspot_count', 1)
            ->assertJsonPath('data_mode.metadata.raw_record_count', 1)
            ->assertJsonPath('data_mode.metadata.inside_region_count', 1)
            ->assertJsonPath('data_mode.metadata.filtered_record_count', 1)
            ->assertJsonPath('data_mode.metadata.selected_hotspot_count', 1)
            ->assertJsonPath('weather_data.source', 'Open-Meteo Weather API')
            ->assertJsonPath('weather_data.status', 'live')
            ->assertJsonPath('facility_data.source', 'OpenStreetMap via Overpass API')
            ->assertJsonPath('facility_data.status', 'live')
            ->assertJsonPath('scenario.hotspot_cluster.latitude', -2.250123)
            ->assertJsonPath('scenario.wind.speed_kmh', 14.4)
            ->assertJsonStructure([
                'projection' => ['projected_latitude', 'projected_longitude', 'projected_travel_distance_km'],
                'facility_results',
                'response_priority_queue',
            ]);

        $this->assertStringNotContainsString('test-map-key', $response->getContent());
    }

    public function test_empty_valid_response_reports_no_hotspots_without_fake_live_projection(): void
    {
        Http::fake(['firms.example/*' => Http::response($this->csvHeaders()."\n", 200)]);

        $this->postJson('/sentra/data-mode', ['mode' => 'live'])
            ->assertOk()
            ->assertJsonPath('data_mode.status', 'no_hotspots')
            ->assertJsonPath('data_mode.hotspot_count', 0)
            ->assertJsonMissingPath('scenario');
    }

    private function validCsv(): string
    {
        return $this->csvHeaders()."\n-2.250123,113.901234,2026-09-20,0130,N21,VIIRS,h,12.5";
    }

    private function csvHeaders(): string
    {
        return 'latitude,longitude,acq_date,acq_time,satellite,instrument,confidence,frp';
    }
}

final class InMemoryHotspotObservationStore implements HotspotObservationStore
{
    /** @var array<string, array<string, mixed>> */
    public array $records = [];

    public function save(array $hotspot): void
    {
        $key = implode('|', [
            $hotspot['latitude'],
            $hotspot['longitude'],
            $hotspot['acquired_at'],
            $hotspot['source'],
        ]);

        $this->records[$key] = $hotspot;
    }
}
