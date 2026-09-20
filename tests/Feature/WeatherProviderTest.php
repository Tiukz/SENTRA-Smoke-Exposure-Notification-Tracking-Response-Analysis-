<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LiveWeatherProvider;
use App\Services\SimulationWeatherProvider;
use App\Services\SnapshotWeatherProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WeatherProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.open_meteo.base_url' => 'https://weather.example/v1/forecast']);
    }

    public function test_real_hotspot_and_weather_snapshots_run_offline(): void
    {
        config(['services.firms.map_key' => null]);
        Http::preventStrayRequests();

        $this->getJson('/sentra/demo')
            ->assertOk()
            ->assertJsonPath('data_mode.mode', 'snapshot')
            ->assertJsonPath('data_mode.status', 'archived_snapshot')
            ->assertJsonPath('weather_data.mode', 'snapshot')
            ->assertJsonPath('weather_data.status', 'archived_snapshot')
            ->assertJsonPath('weather_data.source', 'Open-Meteo Weather API')
            ->assertJsonPath('scenario.wind.speed_kmh', 0.7)
            ->assertJsonPath('scenario.wind.direction_degrees', 166)
            ->assertJsonPath('scenario.wind.humidity', 95);

        Http::assertNothingSent();
    }

    public function test_live_weather_success_is_normalized(): void
    {
        Http::fake(['weather.example/*' => Http::response($this->liveResponse())]);

        $weather = app(LiveWeatherProvider::class)->get(-2.25, 113.90);

        $this->assertTrue($weather['available']);
        $this->assertSame('live', $weather['mode']);
        $this->assertSame('live', $weather['status']);
        $this->assertSame('Open-Meteo Weather API', $weather['source']);
        $this->assertSame('2026-09-20T02:00:00+00:00', $weather['observation_at']);
        $this->assertSame(14.4, $weather['wind_speed_kmh']);
        $this->assertSame(80.0, $weather['wind_direction_degrees']);
        $this->assertSame(61.0, $weather['humidity_percent']);
        Http::assertSent(fn ($request): bool => $request['latitude'] === -2.25
            && $request['longitude'] === 113.90
            && $request['timezone'] === 'UTC');
    }

    public function test_live_weather_failure_is_explicit_and_not_simulated(): void
    {
        Http::fake(['weather.example/*' => Http::response('unavailable', 503)]);

        $weather = app(LiveWeatherProvider::class)->get(-2.25, 113.90);

        $this->assertFalse($weather['available']);
        $this->assertSame('live', $weather['mode']);
        $this->assertSame('unavailable', $weather['status']);
        $this->assertSame('api_failure', $weather['error_type']);
        $this->assertNull($weather['wind_speed_kmh']);
        $this->assertStringContainsString('tidak menggantinya', $weather['message']);
    }

    public function test_live_weather_network_failure_is_identified(): void
    {
        Http::fake(['weather.example/*' => Http::failedConnection('network down')]);

        $weather = app(LiveWeatherProvider::class)->get(-2.25, 113.90);

        $this->assertFalse($weather['available']);
        $this->assertSame('network_failure', $weather['error_type']);
    }

    public function test_simulation_weather_is_unchanged(): void
    {
        $weather = app(SimulationWeatherProvider::class)->get(-2.36, 113.78);

        $this->assertTrue($weather['available']);
        $this->assertSame('simulation', $weather['mode']);
        $this->assertSame('simulated', $weather['status']);
        $this->assertSame('SENTRA Demo Scenario', $weather['source']);
        $this->assertSame(20.0, $weather['wind_speed_kmh']);
        $this->assertSame(45.0, $weather['wind_direction_degrees']);
        $this->assertSame(55.0, $weather['humidity_percent']);
    }

    public function test_all_weather_providers_use_the_same_normalized_structure(): void
    {
        Http::fake(['weather.example/*' => Http::response($this->liveResponse())]);

        $snapshot = app(SnapshotWeatherProvider::class)->get(-2.57, 110.85);
        $live = app(LiveWeatherProvider::class)->get(-2.57, 110.85);
        $simulation = app(SimulationWeatherProvider::class)->get(-2.57, 110.85);
        $expectedKeys = [
            'mode', 'available', 'status', 'error_type', 'source', 'observation_at', 'wind_speed_kmh',
            'wind_direction_degrees', 'humidity_percent', 'metadata', 'message',
        ];

        $this->assertSame($expectedKeys, array_keys($snapshot));
        $this->assertSame($expectedKeys, array_keys($live));
        $this->assertSame($expectedKeys, array_keys($simulation));
    }

    public function test_dashboard_clearly_exposes_hotspot_and_weather_sources(): void
    {
        $this->get('/sentra')
            ->assertOk()
            ->assertSee('Hotspot:')
            ->assertSee('Cuaca:')
            ->assertSee('Snapshot lokal NASA FIRMS')
            ->assertSee('Snapshot lokal Open-Meteo')
            ->assertSee('Angin arsip');
    }

    /** @return array<string, mixed> */
    private function liveResponse(): array
    {
        return [
            'current' => [
                'time' => '2026-09-20T02:00',
                'relative_humidity_2m' => 61,
                'wind_speed_10m' => 14.4,
                'wind_direction_10m' => 80,
            ],
        ];
    }
}
