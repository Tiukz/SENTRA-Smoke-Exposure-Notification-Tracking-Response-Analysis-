<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\HotspotImpactOverviewService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class HotspotImpactOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_aggregate_summary_contains_required_values(): void
    {
        $overview = $this->scan(
            [$this->hotspot('critical', 0, 0), $this->hotspot('high', 0, -0.2)],
            [$this->facility('facility-1', 0, 0.1)],
            horizon: 3,
        );

        $this->assertSame(2, $overview['summary']['hotspots_total']);
        $this->assertSame(2, $overview['summary']['hotspots_with_impact']);
        $this->assertSame(2, $overview['summary']['hotspots_requiring_attention']);
        $this->assertSame(1, $overview['summary']['facilities_potentially_affected']);
        $this->assertSame(34, $overview['summary']['nearest_eta_minutes']);
        $this->assertSame('critical', $overview['summary']['highest_priority_hotspot_id']);
    }

    public function test_ordering_is_deterministic_and_follows_urgency_before_other_fields(): void
    {
        $hotspots = [$this->hotspot('high', 0, -0.2), $this->hotspot('critical', 0, 0)];
        $facilities = [$this->facility('facility-1', 0, 0.1)];

        $first = $this->scan($hotspots, $facilities, horizon: 3);
        Cache::flush();
        $second = $this->scan(array_reverse($hotspots), $facilities, horizon: 3);

        $this->assertSame(['critical', 'high'], array_column($first['items'], 'hotspot_id'));
        $this->assertSame(array_column($first['items'], 'hotspot_id'), array_column($second['items'], 'hotspot_id'));
        $this->assertSame('CRITICAL', $first['items'][0]['highest_urgency']);
        $this->assertSame('HIGH', $first['items'][1]['highest_urgency']);
        $this->assertSame([
            'critical_urgency', 'high_urgency', 'high_risk',
            'facilities_affected', 'nearest_eta', 'hotspot_id',
        ], $first['ordering']);
    }

    public function test_same_facility_is_not_double_counted_across_hotspots(): void
    {
        $overview = $this->scan(
            [$this->hotspot('one', 0, 0), $this->hotspot('two', 0, 0.02)],
            [$this->facility('same-facility', 0, 0.1)],
        );

        $this->assertSame(2, array_sum(array_column($overview['items'], 'facilities_affected')));
        $this->assertSame(1, $overview['summary']['facilities_potentially_affected']);
    }

    public function test_changed_projection_input_invalidates_cached_result(): void
    {
        $hotspots = [$this->hotspot('one', 0, 0)];
        $facilities = [$this->facility('facility-1', 0, 0.3)];

        $shortHorizon = $this->scan($hotspots, $facilities, horizon: 1);
        $longHorizon = $this->scan($hotspots, $facilities, horizon: 2);

        $this->assertSame(0, $shortHorizon['summary']['hotspots_with_impact']);
        $this->assertSame(1, $longHorizon['summary']['hotspots_with_impact']);
    }

    public function test_scan_is_mode_agnostic_for_all_sentra_modes(): void
    {
        foreach (['snapshot', 'live', 'simulation'] as $mode) {
            $overview = $this->scan(
                [$this->hotspot('one', 0, 0)],
                [$this->facility('facility-1', 0, 0.1)],
                mode: $mode,
            );

            $this->assertSame($mode, $overview['mode']);
            $this->assertSame(1, $overview['summary']['hotspots_total']);
            $this->assertCount(1, $overview['items']);
        }
    }

    public function test_selecting_hotspot_from_summary_reuses_existing_selection_endpoint(): void
    {
        $initial = $this->getJson('/sentra/demo')->assertOk();
        $hotspotId = $initial->json('hotspot_impact_overview.items.1.hotspot_id');

        $this->postJson('/sentra/select-hotspot', ['hotspot_id' => $hotspotId])
            ->assertOk()
            ->assertJsonPath('data_mode.active_hotspot_id', $hotspotId)
            ->assertJsonPath('hotspot_impact_overview.summary.hotspots_total', 15);

        $this->get('/sentra')
            ->assertOk()
            ->assertSee('Ringkasan Kondisi')
            ->assertSee('Hotspot yang Perlu Diperhatikan')
            ->assertSee('Lihat analisis')
            ->assertSee('focusActiveHotspot');
    }

    private function scan(
        array $hotspots,
        array $facilities,
        float $horizon = 2,
        string $mode = 'snapshot',
    ): array {
        return app(HotspotImpactOverviewService::class)->scan(
            $hotspots,
            $facilities,
            90,
            20,
            $horizon,
            $mode,
            ['dataset_version' => 'test-v1'],
        );
    }

    private function hotspot(string $id, float $latitude, float $longitude): array
    {
        return [
            'id' => $id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'confidence' => 90,
            'acquired_at' => '2026-09-20T06:00:00+00:00',
        ];
    }

    private function facility(string $id, float $latitude, float $longitude): array
    {
        return [
            'id' => $id,
            'name' => 'Fasilitas '.$id,
            'type' => 'school',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'Test',
        ];
    }
}
