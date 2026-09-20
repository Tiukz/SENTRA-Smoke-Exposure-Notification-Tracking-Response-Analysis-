<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ResponsePriorityService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResponsePriorityServiceTest extends TestCase
{
    private ResponsePriorityService $service;

    private CarbonImmutable $referenceTime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ResponsePriorityService::class);
        $this->referenceTime = CarbonImmutable::parse('2026-09-20T09:00:00+08:00');
    }

    #[Test]
    public function hospital_outranks_school_when_urgency_and_risk_are_equal(): void
    {
        $queue = $this->service->rank([
            $this->facility('School A', 'school'),
            $this->facility('Hospital B', 'hospital'),
        ], $this->referenceTime);

        $this->assertSame('Hospital B', $queue[0]['facility_name']);
        $this->assertSame(1, $queue[0]['rank']);
    }

    #[Test]
    public function critical_urgency_outranks_high_urgency(): void
    {
        $queue = $this->service->rank([
            $this->facility('Hospital B', 'hospital', urgency: 'HIGH'),
            $this->facility('School A', 'school', urgency: 'CRITICAL'),
        ], $this->referenceTime);

        $this->assertSame('School A', $queue[0]['facility_name']);
    }

    #[Test]
    public function high_risk_outranks_medium_risk_when_urgency_is_equal(): void
    {
        $queue = $this->service->rank([
            $this->facility('Hospital B', 'hospital', risk: 'MEDIUM'),
            $this->facility('School A', 'school', risk: 'HIGH'),
        ], $this->referenceTime);

        $this->assertSame('School A', $queue[0]['facility_name']);
    }

    #[Test]
    public function shorter_intervention_window_increases_priority_after_other_rules_tie(): void
    {
        $queue = $this->service->rank([
            $this->facility('Hospital Later', 'hospital', deadline: '2026-09-20T10:00:00+08:00'),
            $this->facility('Hospital Sooner', 'hospital', deadline: '2026-09-20T09:20:00+08:00'),
        ], $this->referenceTime);

        $this->assertSame('Hospital Sooner', $queue[0]['facility_name']);
        $this->assertSame(20, $queue[0]['remaining_intervention_minutes']);
    }

    #[Test]
    public function facility_outside_projection_ranks_last(): void
    {
        $queue = $this->service->rank([
            $this->facility('Outside Hospital', 'hospital', withinProjection: false),
            $this->facility('Inside Residential', 'residential', risk: 'LOW', urgency: 'LOW'),
        ], $this->referenceTime);

        $this->assertSame('Inside Residential', $queue[0]['facility_name']);
        $this->assertSame('Outside Hospital', $queue[1]['facility_name']);
        $this->assertStringContainsString('di luar proyeksi', $queue[1]['explanation']);
    }

    /** @return array<string, mixed> */
    private function facility(
        string $name,
        string $type,
        string $risk = 'HIGH',
        string $urgency = 'CRITICAL',
        string $deadline = '2026-09-20T09:30:00+08:00',
        bool $withinProjection = true,
    ): array {
        return [
            'facility_name' => $name,
            'facility_type' => $type,
            'risk_level' => $risk,
            'urgency_level' => $urgency,
            'approximate_eta_hours' => 1.0,
            'intervention_deadline' => $deadline,
            'within_projection' => $withinProjection,
        ];
    }
}
