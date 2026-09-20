<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HotspotObservationStore;
use App\Services\FacilityExplanationService;
use App\Services\FirmsHotspotProvider;
use App\Services\HotspotFreshnessService;
use App\Services\LiveOsmFacilityProvider;
use App\Services\LiveWeatherProvider;
use App\Services\RealSnapshotService;
use App\Services\ScenarioEngineService;
use App\Services\ScenarioSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class BackendFinalizationTest extends TestCase
{
    private string $snapshotPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotPath = tempnam(sys_get_temp_dir(), 'sentra-real-snapshot-');
        config([
            'services.real_snapshot.path' => $this->snapshotPath,
            'services.firms.map_key' => 'test-key',
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
            'services.overpass.radius_metres' => 75000,
            'services.overpass.max_facilities' => 12,
            'services.overpass.cache_seconds' => 21600,
        ]);
        Cache::flush();
        $this->app->instance(HotspotObservationStore::class, new FinalizationHotspotStore);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        if (is_file($this->snapshotPath)) {
            unlink($this->snapshotPath);
        }

        parent::tearDown();
    }

    public function test_freshness_boundaries_and_observation_range_are_deterministic(): void
    {
        CarbonImmutable::setTestNow('2026-09-20T12:00:00+00:00');
        $service = app(HotspotFreshnessService::class);
        $hotspots = $service->enrich([
            $this->hotspot('2026-09-20T09:01:00+00:00'),
            $this->hotspot('2026-09-20T09:00:00+00:00'),
            $this->hotspot('2026-09-20T06:00:00+00:00'),
            $this->hotspot('2026-09-19T23:59:00+00:00'),
        ]);

        $this->assertSame(['FRESH', 'RECENT', 'STALE', 'VERY_STALE'], array_column($hotspots, 'freshness_status'));
        $range = $service->observationRange($hotspots);
        $this->assertSame('2026-09-20T09:01:00+00:00', $range['newest_selected_observation']['acquired_at']);
        $this->assertSame('2026-09-19T23:59:00+00:00', $range['oldest_selected_observation']['acquired_at']);
    }

    public function test_scenario_engine_rejects_mixed_provider_modes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('harus berasal dari mode yang sama');

        app(ScenarioEngineService::class)->run(
            [],
            ['mode' => 'snapshot'],
            ['mode' => 'live'],
            ['mode' => 'snapshot'],
        );
    }

    public function test_snapshot_and_simulation_endpoints_do_not_mix_modes(): void
    {
        config(['services.real_snapshot.path' => storage_path('app/datasets/real-snapshot.json')]);

        $this->getJson('/sentra/demo')
            ->assertOk()
            ->assertJsonPath('data_mode.mode', 'snapshot')
            ->assertJsonPath('weather_data.mode', 'snapshot')
            ->assertJsonPath('facility_data.mode', 'snapshot')
            ->assertJsonStructure([
                'summary' => [
                    'facilities_total', 'high_risk_count', 'critical_urgency_count',
                    'nearest_eta_minutes', 'top_priority_facility', 'status',
                ],
                'facility_results' => ['*' => ['why_this_result']],
            ]);

        $this->postJson('/sentra/simulate', [
            'wind_direction' => 45,
            'wind_speed' => 20,
            'projection_horizon' => 2,
        ])->assertOk()
            ->assertJsonPath('data_mode.mode', 'simulation')
            ->assertJsonPath('weather_data.mode', 'simulation')
            ->assertJsonPath('facility_data.mode', 'simulation');
    }

    public function test_dashboard_contains_active_mode_simulator_synchronization(): void
    {
        config(['services.real_snapshot.path' => storage_path('app/datasets/real-snapshot.json')]);

        $this->get('/sentra')
            ->assertOk()
            ->assertSee('activeModeBaseline')
            ->assertSee('syncSimulatorInputs')
            ->assertSee('Nilai awal mode aktif dipulihkan.');
    }

    public function test_summary_status_and_facility_explanation_are_deterministic(): void
    {
        $facilities = [[
            'facility_name' => 'Rumah Sakit Uji',
            'risk_level' => 'HIGH',
            'urgency_level' => 'CRITICAL',
            'approximate_eta_hours' => 0.5,
            'cross_track_distance_km' => 2.5,
            'within_projection' => true,
        ]];
        $summary = app(ScenarioSummaryService::class)->summarize($facilities, [['facility_name' => 'Rumah Sakit Uji']]);
        $explanation = app(FacilityExplanationService::class)->explain($facilities[0], 45, 'Northeast');

        $this->assertSame([
            'facilities_total' => 1,
            'high_risk_count' => 1,
            'critical_urgency_count' => 1,
            'nearest_eta_minutes' => 30,
            'top_priority_facility' => 'Rumah Sakit Uji',
            'status' => 'CRITICAL',
        ], $summary);
        $this->assertSame(
            'Fasilitas berada dalam panjang proyeksi asap, berjarak 2,50 km dari lintasan asap, dan memiliki ETA sekitar 30 menit. Dengan arah angin Northeast (45°), hasilnya adalah risiko HIGH dan urgensi CRITICAL.',
            $explanation,
        );
    }

    public function test_summary_status_rules_are_explicit(): void
    {
        $service = app(ScenarioSummaryService::class);
        $facility = static fn (string $risk, string $urgency): array => [
            'risk_level' => $risk,
            'urgency_level' => $urgency,
            'approximate_eta_hours' => null,
        ];

        $this->assertSame('SAFE', $service->summarize([$facility('LOW', 'NONE')], [])['status']);
        $this->assertSame('WATCH', $service->summarize([$facility('MEDIUM', 'MODERATE')], [])['status']);
        $this->assertSame('ALERT', $service->summarize([$facility('HIGH', 'HIGH')], [])['status']);
        $this->assertSame('CRITICAL', $service->summarize([$facility('LOW', 'CRITICAL')], [])['status']);
    }

    public function test_snapshot_refresh_writes_all_sources_together_and_command_succeeds(): void
    {
        $this->fakeSuccessfulLiveProviders();

        $this->artisan('sentra:snapshot-refresh')
            ->expectsOutputToContain('Snapshot Riil berhasil diperbarui secara atomik.')
            ->assertSuccessful();

        $snapshot = json_decode(file_get_contents($this->snapshotPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Kalimantan Tengah', $snapshot['metadata']['region']);
        $this->assertCount(1, $snapshot['hotspots']);
        $this->assertSame(14.4, $snapshot['weather']['wind_speed_kmh']);
        $this->assertCount(1, $snapshot['facilities']);
    }

    public function test_failed_refresh_preserves_previous_snapshot_byte_for_byte(): void
    {
        $previous = "{\"previous\":true}\n";
        file_put_contents($this->snapshotPath, $previous);
        Http::fake(['firms.example/*' => Http::response('unavailable', 503)]);

        try {
            app(RealSnapshotService::class)->refresh();
            $this->fail('Refresh seharusnya gagal.');
        } catch (RuntimeException) {
            $this->assertSame($previous, file_get_contents($this->snapshotPath));
        }
    }

    public function test_provider_errors_and_valid_empty_results_are_distinct(): void
    {
        config(['services.firms.map_key' => null]);
        $missingKey = app(FirmsHotspotProvider::class)->get();
        $this->assertSame('missing_configuration', $missingKey['error_type']);

        Http::fake(['overpass.example/*' => Http::response(['elements' => []])]);
        $emptyFacilities = app(LiveOsmFacilityProvider::class)->get(-2.2, 113.9);
        $this->assertTrue($emptyFacilities['available']);
        $this->assertSame('no_facilities', $emptyFacilities['status']);
        $this->assertNull($emptyFacilities['error_type']);

        Http::fake(['weather.example/*' => Http::response(['current' => ['invalid' => true]])]);
        $malformedWeather = app(LiveWeatherProvider::class)->get(-2.2, 113.9);
        $this->assertSame('malformed_response', $malformedWeather['error_type']);
    }

    public function test_malformed_overpass_elements_are_not_reported_as_valid_empty_results(): void
    {
        Http::fake(['overpass.example/*' => Http::response(['elements' => [['invalid' => true]]])]);

        $result = app(LiveOsmFacilityProvider::class)->get(-2.2, 113.9);

        $this->assertFalse($result['available']);
        $this->assertSame('malformed_response', $result['error_type']);
    }

    /** @return array<string, mixed> */
    private function hotspot(string $acquiredAt): array
    {
        return [
            'latitude' => -2.25,
            'longitude' => 113.90,
            'confidence' => 90,
            'frp' => 12.5,
            'satellite' => 'N21',
            'instrument' => 'VIIRS',
            'acquired_at' => $acquiredAt,
            'source' => 'NASA FIRMS',
        ];
    }

    private function fakeSuccessfulLiveProviders(): void
    {
        Http::fake([
            'firms.example/*' => Http::response("latitude,longitude,acq_date,acq_time,satellite,instrument,confidence,frp\n-2.250123,113.901234,2026-09-20,0130,N21,VIIRS,h,12.5"),
            'weather.example/*' => Http::response(['current' => [
                'time' => '2026-09-20T02:00',
                'relative_humidity_2m' => 61,
                'wind_speed_10m' => 14.4,
                'wind_direction_10m' => 80,
            ]]),
            'overpass.example/*' => Http::response(['elements' => [[
                'type' => 'node',
                'id' => 10,
                'lat' => -2.24,
                'lon' => 113.92,
                'tags' => ['amenity' => 'hospital', 'name' => 'RS OSM'],
            ]]]),
        ]);
    }
}

final class FinalizationHotspotStore implements HotspotObservationStore
{
    public function save(array $hotspot): void {}
}
