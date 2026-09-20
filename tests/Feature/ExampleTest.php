<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_sentra_demo_returns_processed_default_snapshot(): void
    {
        $this->getJson('/sentra/demo')
            ->assertOk()
            ->assertJsonPath('simulation_only', false)
            ->assertJsonPath('data_mode.mode', 'snapshot')
            ->assertJsonPath('data_mode.status', 'archived_snapshot')
            ->assertJsonPath('data_mode.hotspot_count', 15)
            ->assertJsonCount(12, 'facility_results')
            ->assertJsonPath('facility_results.0.safety_buffer_minutes', 60)
            ->assertJsonPath('facility_results.1.safety_buffer_minutes', 60)
            ->assertJsonPath('facility_results.2.safety_buffer_minutes', 60)
            ->assertJsonStructure([
                'facility_results' => [
                    '*' => ['along_track_distance_km', 'cross_track_distance_km'],
                ],
            ]);
    }

    public function test_sentra_dashboard_renders_simulation_data(): void
    {
        $this->get('/sentra')
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee('SENTRA')
            ->assertSee('Snapshot Riil')
            ->assertSee('Data Aktual')
            ->assertSee('Simulasi')
            ->assertSee('Koridor visual · bukan batas ilmiah')
            ->assertSee('Prototipe sederhana perambatan asap')
            ->assertSee('Proyeksi terdepan asap')
            ->assertSee('Arah angin:')
            ->assertSee('Rencana Respons Adaptif')
            ->assertSee('Simulator skenario')
            ->assertSee('Jalankan simulasi')
            ->assertSee('Reset skenario')
            ->assertSee('Prioritas Respons')
            ->assertSee('Dihitung di server')
            ->assertSee('simulateUrl')
            ->assertSee('csrf-token')
            ->assertSee('X-CSRF-TOKEN')
            ->assertSee('hotspot relevan ditampilkan')
            ->assertSee('Detail teknis')
            ->assertSee('Tidak dimaksudkan sebagai prakiraan dispersi atmosfer resmi.')
            ->assertSee('Waktu mulai simulasi:')
            ->assertSee('sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=', false)
            ->assertSee('OpenStreetMap');
    }

    public function test_response_plan_endpoint_uses_deterministic_engine_output_without_api_key(): void
    {
        $this->getJson('/sentra/response-plan/0')
            ->assertOk()
            ->assertJsonPath('source', 'local_response_engine')
            ->assertJsonPath('engine_output.facility_name', 'School A')
            ->assertJsonPath('engine_output.risk_level', 'HIGH')
            ->assertJsonPath('engine_output.urgency_level', 'CRITICAL')
            ->assertJsonPath('engine_output.approximate_eta_hours', 0.5)
            ->assertJsonPath('plan.priority', 'TINDAKAN SEGERA')
            ->assertJsonPath('plan.deadline', '2026-09-20T00:45:00+00:00')
            ->assertJsonPath('plan.source', 'local_response_engine')
            ->assertJsonCount(5, 'plan.actions');
    }

    public function test_response_plan_rejects_unknown_facility(): void
    {
        $this->getJson('/sentra/response-plan/99')
            ->assertNotFound()
            ->assertJsonPath('message', 'Fasilitas simulasi tidak ditemukan.');
    }

    public function test_simulator_accepts_modified_wind_direction(): void
    {
        $defaultLongitude = $this->getJson('/sentra/demo')->json('projection.projected_longitude');

        $this->postJson('/sentra/simulate', $this->scenario(['wind_direction' => 80]))
            ->assertOk()
            ->assertJsonPath('scenario_modified', true)
            ->assertJsonPath('scenario.wind.direction_degrees', 80)
            ->assertJsonPath('scenario.wind.label', 'East')
            ->assertJsonMissing(['projected_longitude' => $defaultLongitude]);
    }

    public function test_simulator_recomputes_modified_wind_speed(): void
    {
        $this->postJson('/sentra/simulate', $this->scenario(['wind_speed' => 30]))
            ->assertOk()
            ->assertJsonPath('scenario.wind.speed_kmh', 30)
            ->assertJsonPath('projection.projected_travel_distance_km', 60);
    }

    public function test_simulator_recomputes_modified_projection_horizon(): void
    {
        $this->postJson('/sentra/simulate', $this->scenario(['projection_horizon' => 3]))
            ->assertOk()
            ->assertJsonPath('scenario.projection_time_hours', 3)
            ->assertJsonPath('projection.projected_travel_distance_km', 60);
    }

    public function test_simulator_rejects_invalid_wind_direction(): void
    {
        $this->postJson('/sentra/simulate', $this->scenario(['wind_direction' => 360]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('wind_direction');
    }

    public function test_simulator_rejects_zero_and_negative_wind_speed(): void
    {
        foreach ([0, -10] as $windSpeed) {
            $this->postJson('/sentra/simulate', $this->scenario(['wind_speed' => $windSpeed]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('wind_speed');
        }
    }

    public function test_simulator_rejects_invalid_projection_horizon(): void
    {
        foreach ([0, 25] as $horizon) {
            $this->postJson('/sentra/simulate', $this->scenario(['projection_horizon' => $horizon]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('projection_horizon');
        }
    }

    public function test_modified_request_does_not_change_default_scenario(): void
    {
        $defaultScenario = $this->getJson('/sentra/demo')->assertOk()->json();

        $this->postJson('/sentra/simulate', $this->scenario([
            'wind_direction' => 180,
            'wind_speed' => 30,
            'projection_horizon' => 3,
        ]))->assertOk();

        $this->assertSame($defaultScenario, $this->getJson('/sentra/demo')->assertOk()->json());
    }

    public function test_simulation_endpoint_recomputes_engine_and_local_plans_server_side(): void
    {
        $this->postJson('/sentra/simulate', $this->scenario([
            'wind_direction' => 180,
            'wind_speed' => 30,
            'projection_horizon' => 3,
        ]))
            ->assertOk()
            ->assertJsonPath('projection.projected_travel_distance_km', 90)
            ->assertJsonPath('facility_results.0.risk_level', 'LOW')
            ->assertJsonPath('facility_results.0.urgency_level', 'NONE')
            ->assertJsonPath('facility_results.0.within_projection', false)
            ->assertJsonPath('facility_results.0.response_plan.priority', 'PEMANTAUAN')
            ->assertJsonPath('facility_results.0.response_plan.source', 'local_response_engine')
            ->assertJsonStructure([
                'projection' => ['projected_latitude', 'projected_longitude', 'projected_travel_distance_km'],
                'facility_results' => [
                    '*' => [
                        'risk_level', 'urgency_level', 'approximate_eta_hours',
                        'projected_exposure_time', 'intervention_deadline', 'response_plan',
                    ],
                ],
            ]);
    }

    public function test_simulator_updates_response_priority_queue(): void
    {
        $this->getJson('/sentra/demo')
            ->assertOk()
            ->assertJsonCount(12, 'response_priority_queue');

        $this->postJson('/sentra/simulate', $this->scenario(['wind_direction' => 90]))
            ->assertOk()
            ->assertJsonPath('response_priority_queue.0.facility_name', 'Hospital B')
            ->assertJsonPath('response_priority_queue.0.rank', 1)
            ->assertJsonPath('response_priority_queue.1.facility_name', 'School A')
            ->assertJsonStructure([
                'response_priority_queue' => [
                    '*' => [
                        'rank', 'facility_name', 'facility_type', 'risk_level', 'urgency_level',
                        'approximate_eta_hours', 'intervention_deadline',
                        'remaining_intervention_minutes', 'explanation',
                    ],
                ],
            ]);
    }

    /**
     * @param  array<string, int|float>  $overrides
     * @return array<string, int|float>
     */
    private function scenario(array $overrides = []): array
    {
        return array_replace([
            'wind_direction' => 45,
            'wind_speed' => 20,
            'projection_horizon' => 2,
        ], $overrides);
    }
}
