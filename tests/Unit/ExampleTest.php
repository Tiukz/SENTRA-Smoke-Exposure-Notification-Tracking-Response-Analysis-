<?php

namespace Tests\Unit;

use App\Services\ExposureService;
use App\Services\SmokeProjectionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_smoke_projection_uses_wind_speed_and_time(): void
    {
        $projection = (new SmokeProjectionService)->project(-2.36, 113.78, 45, 20, 2);

        $this->assertSame(40.0, $projection['projected_travel_distance_km']);
        $this->assertGreaterThan(-2.36, $projection['projected_latitude']);
        $this->assertGreaterThan(113.78, $projection['projected_longitude']);
    }

    public function test_smoke_projection_rejects_negative_speed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SmokeProjectionService)->project(-2.36, 113.78, 45, -1, 2);
    }

    public function test_exposure_rejects_facilities_behind_or_beyond_projection(): void
    {
        $projection = [
            'projected_latitude' => 0.0,
            'projected_longitude' => 0.18,
            'projected_travel_distance_km' => 20.0,
        ];
        $facilities = [
            ['name' => 'Ahead', 'type' => 'school', 'latitude' => 0.0, 'longitude' => 0.09],
            ['name' => 'Behind', 'type' => 'school', 'latitude' => 0.0, 'longitude' => -0.01],
            ['name' => 'Beyond', 'type' => 'school', 'latitude' => 0.0, 'longitude' => 0.27],
            ['name' => 'Far From Plume', 'type' => 'school', 'latitude' => 0.3593, 'longitude' => 0.1347],
        ];

        $results = (new ExposureService)->evaluate($facilities, 0.0, 0.0, 90.0, 10.0, $projection);

        $this->assertTrue($results[0]['within_projection']);
        $this->assertSame('HIGH', $results[0]['risk_level']);
        $this->assertFalse($results[1]['within_projection']);
        $this->assertNull($results[1]['approximate_eta_hours']);
        $this->assertSame('LOW', $results[1]['risk_level']);
        $this->assertFalse($results[2]['within_projection']);
        $this->assertNull($results[2]['approximate_eta_hours']);
        $this->assertSame('LOW', $results[2]['risk_level']);
        $this->assertTrue($results[3]['within_projection']);
        $this->assertSame(1.5, $results[3]['approximate_eta_hours']);
        $this->assertSame(40.0, $results[3]['cross_track_distance_km']);
        $this->assertSame('LOW', $results[3]['risk_level']);
        $this->assertSame('HIGH', $results[3]['urgency_level']);
    }
}
