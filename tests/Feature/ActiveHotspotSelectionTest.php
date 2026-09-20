<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ActiveHotspotSelectionTest extends TestCase
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

    public function test_1_selecting_a_valid_hotspot(): void
    {
        $initial = $this->getJson('/sentra/demo')->assertOk();
        $hotspots = $initial->json('data_mode.hotspots');
        $this->assertIsArray($hotspots);
        $this->assertGreaterThan(1, count($hotspots));

        $secondHotspot = $hotspots[1];
        $this->assertNotNull($secondHotspot['id']);

        $response = $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $secondHotspot['id'],
        ])->assertOk();

        $response->assertJsonPath('data_mode.active_hotspot_id', $secondHotspot['id'])
            ->assertJsonPath('data_mode.primary_hotspot.id', $secondHotspot['id'])
            ->assertJsonPath('scenario.hotspot_cluster.latitude', (float) $secondHotspot['latitude'])
            ->assertJsonPath('scenario.hotspot_cluster.longitude', (float) $secondHotspot['longitude']);
    }

    public function test_2_invalid_hotspot_id_is_rejected(): void
    {
        $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => 'non-existent-hotspot-id',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'hotspot_not_found')
            ->assertJsonPath('message', 'Hotspot tidak ditemukan pada mode data aktif.');
    }

    public function test_3_hotspot_from_another_mode_is_rejected(): void
    {
        $this->withSession(['sentra.current_data_mode' => 'snapshot'])
            ->postJson('/sentra/select-hotspot', [
                'hotspot_id' => 'sample-id',
                'mode' => 'simulation',
            ])->assertStatus(422)
            ->assertJsonPath('status', 'mode_mismatch')
            ->assertJsonPath('message', 'Hotspot berasal dari mode data yang berbeda dari mode aktif saat ini.');

        // Verify that a simulation hotspot ID requested in snapshot mode is rejected
        $sim = $this->postJson('/sentra/data-mode', ['mode' => 'simulation'])->assertOk();
        $simHotspotId = $sim->json('data_mode.hotspots.0.id');

        $this->postJson('/sentra/data-mode', ['mode' => 'snapshot'])->assertOk();
        $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $simHotspotId,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'hotspot_not_found');
    }

    public function test_4_active_hotspot_changes_projection_source(): void
    {
        $default = $this->getJson('/sentra/demo')->assertOk();
        $secondHotspot = $default->json('data_mode.hotspots.1');

        $selected = $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $secondHotspot['id'],
        ])->assertOk();

        $this->assertNotEquals(
            $default->json('scenario.hotspot_cluster.latitude'),
            $selected->json('scenario.hotspot_cluster.latitude')
        );
        $this->assertNotEquals(
            $default->json('projection.projected_latitude'),
            $selected->json('projection.projected_latitude')
        );
        $this->assertSame(
            (float) $secondHotspot['latitude'],
            $selected->json('scenario.hotspot_cluster.latitude')
        );
    }

    public function test_5_facility_analysis_is_recalculated(): void
    {
        $default = $this->getJson('/sentra/demo')->assertOk();
        $secondHotspot = $default->json('data_mode.hotspots.1');

        $selected = $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $secondHotspot['id'],
        ])->assertOk();

        $defaultDistances = array_column($default->json('facility_results'), 'along_track_distance_km');
        $selectedDistances = array_column($selected->json('facility_results'), 'along_track_distance_km');

        $this->assertNotEmpty($defaultDistances);
        $this->assertNotEmpty($selectedDistances);
        $this->assertNotEquals($defaultDistances, $selectedDistances);
    }

    public function test_6_summary_is_recalculated(): void
    {
        $default = $this->getJson('/sentra/demo')->assertOk();
        $secondHotspot = $default->json('data_mode.hotspots.1');

        $selected = $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $secondHotspot['id'],
        ])->assertOk();

        $this->assertNotNull($selected->json('summary'));
        $this->assertNotNull($selected->json('response_priority_queue'));
    }

    public function test_7_route_exposure_uses_the_new_active_corridor(): void
    {
        Http::fake(['https://osrm.example/*' => Http::response($this->osrmPayload())]);

        $this->get('/sentra')->assertOk();

        $firstRoute = $this->postJson('/sentra/route', [
            'origin' => ['latitude' => -2.2, 'longitude' => 113.9],
            'destination' => ['latitude' => -2.1, 'longitude' => 114.0],
        ])->assertOk();

        $snapshot = $this->getJson('/sentra/demo')->assertOk();
        $secondHotspot = $snapshot->json('data_mode.hotspots.1');

        $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $secondHotspot['id'],
        ])->assertOk();

        $secondRoute = $this->postJson('/sentra/route', [
            'origin' => ['latitude' => -2.2, 'longitude' => 113.9],
            'destination' => ['latitude' => -2.1, 'longitude' => 114.0],
        ])->assertOk();

        $this->assertNotSame(
            $firstRoute->json('scenario_fingerprint'),
            $secondRoute->json('scenario_fingerprint')
        );
        $this->assertGreaterThan(
            $firstRoute->json('scenario_version'),
            $secondRoute->json('scenario_version')
        );
    }

    public function test_8_mode_switch_resets_hotspot_selection_safely(): void
    {
        $snapshot = $this->getJson('/sentra/demo')->assertOk();
        $secondHotspot = $snapshot->json('data_mode.hotspots.1');

        $this->postJson('/sentra/select-hotspot', [
            'hotspot_id' => $secondHotspot['id'],
        ])->assertOk();

        $this->assertSame($secondHotspot['id'], session('sentra.selected_hotspot_id'));

        // Switch to simulation
        $sim = $this->postJson('/sentra/data-mode', ['mode' => 'simulation'])->assertOk();
        $this->assertNull(session('sentra.selected_hotspot_id'));
        $this->assertSame(
            $sim->json('data_mode.hotspots.0.id'),
            $sim->json('data_mode.active_hotspot_id')
        );

        // Switch back to snapshot
        $backToSnapshot = $this->postJson('/sentra/data-mode', ['mode' => 'snapshot'])->assertOk();
        $this->assertNull(session('sentra.selected_hotspot_id'));
        $this->assertSame(
            $snapshot->json('data_mode.hotspots.0.id'),
            $backToSnapshot->json('data_mode.active_hotspot_id')
        );
    }

    public function test_9_default_hotspot_behavior_remains_unchanged_when_no_selection(): void
    {
        $demo = $this->getJson('/sentra/demo')->assertOk();
        $this->assertSame(
            $demo->json('data_mode.hotspots.0.id'),
            $demo->json('data_mode.active_hotspot_id')
        );
        $this->assertSame(
            $demo->json('data_mode.hotspots.0.latitude'),
            $demo->json('scenario.hotspot_cluster.latitude')
        );
        $this->assertSame(
            $demo->json('data_mode.hotspots.0.longitude'),
            $demo->json('scenario.hotspot_cluster.longitude')
        );
    }

    /** @return array<string, mixed> */
    private function osrmPayload(): array
    {
        return [
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 12400,
                    'duration' => 1080,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[113.9, -2.2], [114.0, -2.1]],
                    ],
                ],
            ],
        ];
    }
}
