<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\RouteProvider;
use App\Services\OsrmRouteProvider;
use App\Services\RouteExposureService;
use App\Services\RouteRecommendationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RoutePlanningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.osrm.base_url' => 'https://osrm.example/route/v1/driving',
            'services.osrm.timeout_seconds' => 5,
            'services.osrm.max_routes' => 3,
            'services.route_exposure.sample_step_meters' => 100,
            'services.route_exposure.corridor_width_profile' => [[0, 5], [1, 5]],
            'services.route_exposure.minimum_overlap_reduction_points' => 10,
            'services.route_exposure.maximum_duration_increase_percent' => 35,
        ]);
    }

    public function test_route_fully_outside_corridor_has_low_exposure(): void
    {
        $result = app(RouteExposureService::class)->evaluate(
            $this->route([[0.1, 0], [0.1, 0.1]]),
            $this->corridor(),
        );

        $this->assertFalse($result['intersects_corridor']);
        $this->assertSame(0.0, $result['corridor_overlap_percent']);
        $this->assertSame('LOW', $result['exposure_level']);
    }

    public function test_route_with_partial_overlap_has_medium_exposure(): void
    {
        $result = app(RouteExposureService::class)->evaluate(
            $this->route([[0, 0], [0, 0.02], [0.1, 0.02], [0.1, 0.15]]),
            $this->corridor(),
        );

        $this->assertTrue($result['intersects_corridor']);
        $this->assertGreaterThan(10, $result['corridor_overlap_percent']);
        $this->assertLessThanOrEqual(30, $result['corridor_overlap_percent']);
        $this->assertSame('MEDIUM', $result['exposure_level']);
    }

    public function test_route_mostly_inside_corridor_has_high_exposure(): void
    {
        $result = app(RouteExposureService::class)->evaluate(
            $this->route([[0, 0], [0, 0.1]]),
            $this->corridor(),
        );

        $this->assertTrue($result['intersects_corridor']);
        $this->assertGreaterThan(30, $result['corridor_overlap_percent']);
        $this->assertSame('HIGH', $result['exposure_level']);
    }

    public function test_slightly_slower_lower_exposure_route_is_recommended(): void
    {
        $result = app(RouteRecommendationService::class)->recommend([
            $this->evaluatedRoute('route_1', 18, 42),
            $this->evaluatedRoute('route_2', 23, 8),
        ]);

        $this->assertSame('route_2', $result['recommendation']['route_id']);
        $this->assertSame('Rute Paparan Lebih Rendah', $result['recommendation']['label']);
        $this->assertSame(34.0, $result['recommendation']['exposure_reduction_percentage_points']);
        $this->assertStringContainsString('tambahan waktu sekitar 5 menit', $result['recommendation']['reason']);
    }

    public function test_extreme_detour_is_rejected(): void
    {
        $result = app(RouteRecommendationService::class)->recommend([
            $this->evaluatedRoute('route_1', 20, 60),
            $this->evaluatedRoute('route_2', 28, 5),
        ]);

        $this->assertSame('route_1', $result['recommendation']['route_id']);
        $this->assertSame('Rute Tercepat', $result['recommendation']['label']);
    }

    public function test_equal_exposure_chooses_fastest_route(): void
    {
        $result = app(RouteRecommendationService::class)->recommend([
            $this->evaluatedRoute('route_1', 24, 20),
            $this->evaluatedRoute('route_2', 17, 20),
        ]);

        $this->assertSame('route_2', $result['recommendation']['route_id']);
        $this->assertTrue(collect($result['routes'])->firstWhere('id', 'route_2')['is_fastest']);
    }

    public function test_route_endpoint_rejects_invalid_coordinates(): void
    {
        $this->postJson('/sentra/route', [
            'origin' => ['latitude' => -91, 'longitude' => 113.9],
            'destination' => ['latitude' => -2.1, 'longitude' => 181],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['origin.latitude', 'destination.longitude']);
    }

    public function test_route_endpoint_reports_provider_failure_explicitly(): void
    {
        $this->app->instance(RouteProvider::class, new class implements RouteProvider
        {
            public function routes(array $origin, array $destination): array
            {
                return [
                    'available' => false,
                    'status' => 'timeout',
                    'source' => 'OSRM',
                    'routes' => [],
                    'message' => 'Permintaan OSRM melewati batas waktu.',
                ];
            }
        });

        $this->withSession(['sentra.active_smoke_corridor' => $this->activeState('snapshot')])
            ->postJson('/sentra/route', $this->coordinates())
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'timeout')
            ->assertJsonPath('routes', []);
    }

    public function test_malformed_osrm_response_is_not_turned_into_a_route(): void
    {
        Http::fake(['https://osrm.example/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 1000]]])]);

        $result = app(OsrmRouteProvider::class)->routes(
            ['latitude' => -2.2, 'longitude' => 113.9],
            ['latitude' => -2.1, 'longitude' => 114.0],
        );

        $this->assertFalse($result['available']);
        $this->assertSame('malformed_response', $result['status']);
        $this->assertSame([], $result['routes']);
    }

    public function test_scenario_change_invalidates_previous_route_exposure_version(): void
    {
        Http::fake(['https://osrm.example/*' => Http::response($this->osrmPayload())]);

        $first = $this->withSession(['sentra.active_smoke_corridor' => $this->activeState('simulation')])
            ->postJson('/sentra/route', $this->coordinates())
            ->assertOk();

        $this->postJson('/sentra/simulate', [
            'wind_direction' => 80,
            'wind_speed' => 30,
            'projection_horizon' => 3,
        ])->assertOk();

        $second = $this->postJson('/sentra/route', $this->coordinates())->assertOk();

        $this->assertNotSame($first->json('scenario_fingerprint'), $second->json('scenario_fingerprint'));
        $this->assertGreaterThan($first->json('scenario_version'), $second->json('scenario_version'));
    }

    public function test_route_endpoint_works_with_all_three_sentra_modes(): void
    {
        Http::fake(['https://osrm.example/*' => Http::response($this->osrmPayload())]);

        foreach (['snapshot', 'live', 'simulation'] as $mode) {
            $this->withSession(['sentra.active_smoke_corridor' => $this->activeState($mode)])
                ->postJson('/sentra/route', $this->coordinates())
                ->assertOk()
                ->assertJsonPath('mode', $mode)
                ->assertJsonPath('source', 'OSRM')
                ->assertJsonCount(2, 'routes');
        }
    }

    public function test_osrm_provider_keeps_single_route_without_fabricating_alternatives(): void
    {
        $payload = $this->osrmPayload();
        $payload['routes'] = [$payload['routes'][0]];
        Http::fake(['https://osrm.example/*' => Http::response($payload)]);

        $result = app(OsrmRouteProvider::class)->routes(
            ['latitude' => -2.2, 'longitude' => 113.9],
            ['latitude' => -2.1, 'longitude' => 114.0],
        );

        $this->assertTrue($result['available']);
        $this->assertCount(1, $result['routes']);
        $this->assertSame('route_1', $result['routes'][0]['id']);
    }

    /** @param list<array{0: float|int, 1: float|int}> $points */
    private function route(array $points): array
    {
        return [
            'id' => 'route_1',
            'distance_meters' => 10000,
            'duration_seconds' => 1200,
            'geometry' => array_map(fn (array $point): array => [
                'latitude' => (float) $point[0],
                'longitude' => (float) $point[1],
            ], $points),
            'source' => 'OSRM',
        ];
    }

    private function corridor(): array
    {
        return [
            'hotspot_latitude' => 0.0,
            'hotspot_longitude' => 0.0,
            'wind_direction_degrees' => 90.0,
            'projection_distance_km' => 20.0,
        ];
    }

    private function evaluatedRoute(string $id, float $durationMinutes, float $overlapPercent): array
    {
        return [
            'id' => $id,
            'duration_minutes' => $durationMinutes,
            'corridor_overlap_percent' => $overlapPercent,
        ];
    }

    private function activeState(string $mode): array
    {
        return [
            'mode' => $mode,
            'hotspot_latitude' => -2.36,
            'hotspot_longitude' => 113.78,
            'wind_direction_degrees' => 45.0,
            'projection_distance_km' => 40.0,
            'projection_time_hours' => 2.0,
            'fingerprint' => hash('sha256', $mode),
            'version' => 1,
        ];
    }

    private function coordinates(): array
    {
        return [
            'origin' => ['latitude' => -2.2, 'longitude' => 113.9],
            'destination' => ['latitude' => -2.1, 'longitude' => 114.0],
        ];
    }

    private function osrmPayload(): array
    {
        return [
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 12400,
                    'duration' => 1080,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[113.9, -2.2], [114.0, -2.1]]],
                ],
                [
                    'distance' => 14100,
                    'duration' => 1380,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[113.9, -2.2], [113.85, -2.05], [114.0, -2.1]]],
                ],
            ],
        ];
    }
}
