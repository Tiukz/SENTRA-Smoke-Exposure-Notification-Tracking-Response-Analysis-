<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LocalAdaptiveResponsePlannerService;
use Tests\TestCase;

final class LocalAdaptiveResponsePlannerServiceTest extends TestCase
{
    private LocalAdaptiveResponsePlannerService $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = $this->app->make(LocalAdaptiveResponsePlannerService::class);
    }

    public function test_school_high_risk_and_critical_urgency_gets_immediate_actions(): void
    {
        $plan = $this->planner->plan($this->facility());

        $this->assertSame('TINDAKAN SEGERA', $plan['priority']);
        $this->assertContains('Hentikan seluruh kegiatan luar ruang segera.', $plan['actions']);
        $this->assertStringContainsString('HIGH (TINGGI)', $plan['summary']);
        $this->assertStringContainsString('CRITICAL (KRITIS)', $plan['summary']);
    }

    public function test_hospital_high_risk_and_high_urgency_uses_hospital_actions(): void
    {
        $plan = $this->planner->plan($this->facility([
            'facility_type' => 'hospital',
            'risk_level' => 'HIGH',
            'urgency_level' => 'HIGH',
        ]));

        $this->assertSame('PRIORITAS TINGGI', $plan['priority']);
        $this->assertContains('Siagakan perlindungan bagi pasien rentan terhadap paparan asap.', $plan['actions']);
    }

    public function test_residential_medium_risk_and_moderate_urgency_uses_residential_actions(): void
    {
        $plan = $this->planner->plan($this->facility([
            'facility_type' => 'residential',
            'risk_level' => 'MEDIUM',
            'urgency_level' => 'MODERATE',
        ]));

        $this->assertSame('PRIORITAS SEDANG', $plan['priority']);
        $this->assertContains('Pantau arah perkembangan asap dan informasi proyeksi terbaru.', $plan['actions']);
    }

    public function test_low_risk_and_high_urgency_preserves_both_facts(): void
    {
        $plan = $this->planner->plan($this->facility([
            'risk_level' => 'LOW',
            'urgency_level' => 'HIGH',
        ]));

        $this->assertSame('SIAGA WAKTU', $plan['priority']);
        $this->assertStringContainsString('LOW (RENDAH)', $plan['summary']);
        $this->assertStringContainsString('HIGH (TINGGI)', $plan['summary']);
        $this->assertStringContainsString('paparan spasial tetap rendah', $plan['summary']);
    }

    public function test_facility_outside_projection_gets_monitoring_response(): void
    {
        $plan = $this->planner->plan($this->facility([
            'within_projection' => false,
            'risk_level' => 'HIGH',
            'urgency_level' => 'CRITICAL',
            'intervention_deadline' => null,
        ]));

        $this->assertSame('PEMANTAUAN', $plan['priority']);
        $this->assertContains('Pantau pembaruan proyeksi tanpa mengaktifkan tindakan darurat.', $plan['actions']);
        $this->assertStringContainsString('di luar horizon proyeksi', $plan['summary']);
    }

    public function test_missing_eta_is_reported_without_inventing_a_value(): void
    {
        $plan = $this->planner->plan($this->facility(['approximate_eta_hours' => null]));

        $this->assertStringContainsString('ETA belum tersedia dari engine.', $plan['summary']);
    }

    public function test_unknown_facility_type_uses_safe_default_actions(): void
    {
        $plan = $this->planner->plan($this->facility(['facility_type' => 'warehouse']));

        $this->assertNotEmpty($plan['actions']);
        $this->assertContains('Aktifkan prosedur perlindungan paparan asap yang berlaku di fasilitas.', $plan['actions']);
        $this->assertSame('local_response_engine', $plan['source']);
    }

    public function test_intervention_deadline_is_preserved_exactly(): void
    {
        $deadline = '2026-09-20T08:45:00+08:00';
        $plan = $this->planner->plan($this->facility(['intervention_deadline' => $deadline]));

        $this->assertSame($deadline, $plan['deadline']);
        $this->assertStringContainsString('08:45 WITA', $plan['summary']);
    }

    public function test_planner_does_not_modify_engine_risk_or_urgency(): void
    {
        $facility = $this->facility(['risk_level' => 'LOW', 'urgency_level' => 'HIGH']);
        $original = $facility;

        $this->planner->plan($facility);

        $this->assertSame($original, $facility);
        $this->assertSame('LOW', $facility['risk_level']);
        $this->assertSame('HIGH', $facility['urgency_level']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function facility(array $overrides = []): array
    {
        return array_replace([
            'facility_name' => 'School A',
            'facility_type' => 'school',
            'risk_level' => 'HIGH',
            'urgency_level' => 'CRITICAL',
            'approximate_eta_hours' => 0.5,
            'projected_exposure_time' => '2026-09-20T09:30:00+08:00',
            'intervention_deadline' => '2026-09-20T08:45:00+08:00',
            'safety_buffer_minutes' => 45,
            'within_projection' => true,
            'along_track_distance_km' => 10.0,
            'cross_track_distance_km' => 2.99,
        ], $overrides);
    }
}
