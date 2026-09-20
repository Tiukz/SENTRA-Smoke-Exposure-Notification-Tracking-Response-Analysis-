<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HotspotObservationStore;
use App\Services\FirmsHotspotProvider;
use App\Services\SimulationHotspotProvider;
use App\Services\SnapshotHotspotProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SnapshotHotspotProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HotspotObservationStore::class, new SnapshotTestHotspotStore);
    }

    public function test_snapshot_loads_without_api_key(): void
    {
        config(['services.firms.map_key' => null]);

        $snapshot = app(SnapshotHotspotProvider::class)->get();

        $this->assertTrue($snapshot['available']);
        $this->assertSame('snapshot', $snapshot['mode']);
        $this->assertSame('archived_snapshot', $snapshot['status']);
        $this->assertNotEmpty($snapshot['hotspots']);
        $this->assertArrayHasKey('observation_age_minutes', $snapshot);
        $this->assertArrayHasKey('freshness_status', $snapshot);
        $this->assertArrayHasKey('newest_selected_observation', $snapshot['metadata']);
        $this->assertArrayHasKey('oldest_selected_observation', $snapshot['metadata']);
    }

    public function test_snapshot_loads_without_network_access(): void
    {
        Http::preventStrayRequests();

        $snapshot = app(SnapshotHotspotProvider::class)->get();

        $this->assertTrue($snapshot['available']);
        $this->assertSame(15, $snapshot['hotspot_count']);
        Http::assertNothingSent();
    }

    public function test_snapshot_metadata_is_exposed_on_dashboard_and_json(): void
    {
        $this->getJson('/sentra/demo')
            ->assertOk()
            ->assertJsonPath('data_mode.metadata.region', 'Kalimantan Tengah')
            ->assertJsonPath('data_mode.metadata.hotspot_source', 'NASA FIRMS VIIRS_NOAA21_NRT')
            ->assertJsonPath('data_mode.metadata.weather_source', 'Open-Meteo Weather API')
            ->assertJsonPath('data_mode.metadata.facility_source', 'OpenStreetMap via Overpass API');

        $this->get('/sentra')
            ->assertOk()
            ->assertSee('Snapshot Riil')
            ->assertSee('Snapshot lokal NASA FIRMS');
    }

    public function test_live_mode_remains_near_real_time(): void
    {
        $this->configureLiveFirms();
        Http::fake(['firms.example/*' => Http::response($this->validLiveCsv(), 200)]);

        $live = app(FirmsHotspotProvider::class)->get();

        $this->assertTrue($live['available']);
        $this->assertSame('live', $live['mode']);
        $this->assertSame('near_real_time', $live['status']);
        $this->assertSame('NASA FIRMS VIIRS_NOAA21_NRT', $live['source']);
    }

    public function test_simulation_mode_remains_unchanged(): void
    {
        $simulation = app(SimulationHotspotProvider::class)->get();

        $this->assertSame('simulation', $simulation['mode']);
        $this->assertSame('simulation', $simulation['status']);
        $this->assertSame(-2.36, $simulation['primary_hotspot']['latitude']);
        $this->assertSame(113.78, $simulation['primary_hotspot']['longitude']);
        $this->assertSame(92, $simulation['primary_hotspot']['confidence']);
    }

    public function test_snapshot_and_live_records_share_the_same_normalized_structure(): void
    {
        $this->configureLiveFirms();
        Http::fake(['firms.example/*' => Http::response($this->validLiveCsv(), 200)]);

        $snapshotRecord = app(SnapshotHotspotProvider::class)->get()['hotspots'][0];
        $liveRecord = app(FirmsHotspotProvider::class)->get()['hotspots'][0];

        $this->assertSame(array_keys($liveRecord), array_keys($snapshotRecord));
        $this->assertSame(
            ['latitude', 'longitude', 'confidence', 'frp', 'satellite', 'instrument', 'acquired_at', 'source', 'observation_age_minutes', 'freshness_status'],
            array_keys($snapshotRecord),
        );
    }

    public function test_missing_or_invalid_snapshot_is_explicitly_unavailable(): void
    {
        config(['services.real_snapshot.path' => storage_path('app/datasets/missing-snapshot.json')]);
        $missing = app(SnapshotHotspotProvider::class)->get();

        $invalidPath = tempnam(sys_get_temp_dir(), 'sentra-snapshot-');
        file_put_contents($invalidPath, '{invalid json');
        config(['services.real_snapshot.path' => $invalidPath]);
        $invalid = app(SnapshotHotspotProvider::class)->get();
        unlink($invalidPath);

        foreach ([$missing, $invalid] as $result) {
            $this->assertFalse($result['available']);
            $this->assertSame('snapshot', $result['mode']);
            $this->assertSame('unavailable', $result['status']);
            $this->assertContains($result['error_type'], ['missing_snapshot', 'malformed_response']);
            $this->assertNull($result['primary_hotspot']);
            $this->assertStringNotContainsString('live', strtolower($result['message']));
        }

        $this->postJson('/sentra/data-mode', ['mode' => 'snapshot'])
            ->assertServiceUnavailable()
            ->assertJsonPath('data_mode.status', 'unavailable')
            ->assertJsonPath('data_mode.available', false);
    }

    private function configureLiveFirms(): void
    {
        config([
            'services.firms.map_key' => 'test-map-key',
            'services.firms.base_url' => 'https://firms.example/api/area/csv',
            'services.firms.source' => 'VIIRS_NOAA21_NRT',
            'services.firms.area' => '110.70,-3.60,115.90,0.80',
            'services.firms.day_range' => 1,
        ]);
    }

    private function validLiveCsv(): string
    {
        return "latitude,longitude,acq_date,acq_time,satellite,instrument,confidence,frp\n-2.250123,113.901234,2026-09-20,0130,N21,VIIRS,h,12.5";
    }
}

final class SnapshotTestHotspotStore implements HotspotObservationStore
{
    public function save(array $hotspot): void {}
}
