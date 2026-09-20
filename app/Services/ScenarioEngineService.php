<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class ScenarioEngineService
{
    public function __construct(
        private readonly SmokeProjectionService $smokeProjectionService,
        private readonly ExposureService $exposureService,
        private readonly InterventionWindowService $interventionWindowService,
        private readonly LocalAdaptiveResponsePlannerService $responsePlannerService,
        private readonly ResponsePriorityService $responsePriorityService,
        private readonly FacilityExplanationService $explanationService,
        private readonly ScenarioSummaryService $summaryService,
        private readonly HotspotImpactOverviewService $impactOverviewService,
    ) {}

    /**
     * @param  array{wind_direction?: float, wind_speed?: float, projection_horizon?: float}  $parameters
     * @param  array<string, mixed>  $hotspotData
     * @param  array<string, mixed>  $weatherData
     * @param  array<string, mixed>  $facilityData
     * @return array<string, mixed>
     */
    public function run(array $parameters, array $hotspotData, array $weatherData, array $facilityData): array
    {
        $modes = array_unique([
            $hotspotData['mode'] ?? null,
            $weatherData['mode'] ?? null,
            $facilityData['mode'] ?? null,
        ]);

        if (count($modes) !== 1 || $modes[0] === null) {
            throw new RuntimeException('Sumber hotspot, cuaca, dan fasilitas harus berasal dari mode yang sama.');
        }

        $primaryHotspot = $hotspotData['primary_hotspot'] ?? null;

        if (! is_array($primaryHotspot)) {
            throw new RuntimeException('Hotspot utama tidak tersedia untuk diproses.');
        }

        if (! ($weatherData['available'] ?? false)) {
            throw new RuntimeException('Data cuaca tidak tersedia untuk diproses.');
        }

        if (! ($facilityData['available'] ?? false) || ($facilityData['facilities'] ?? []) === []) {
            throw new RuntimeException('Data fasilitas tidak tersedia untuk diproses.');
        }

        $scenario = $this->loadScenario();
        $scenario['hotspot_cluster'] = [
            'latitude' => (float) $primaryHotspot['latitude'],
            'longitude' => (float) $primaryHotspot['longitude'],
            'confidence' => (int) $primaryHotspot['confidence'],
        ];
        $scenario['facilities'] = $facilityData['facilities'];
        $scenario['wind']['direction_degrees'] = $parameters['wind_direction'] ?? $weatherData['wind_direction_degrees'];
        $scenario['wind']['speed_kmh'] = $parameters['wind_speed'] ?? $weatherData['wind_speed_kmh'];
        $scenario['wind']['humidity'] = $weatherData['humidity_percent'];
        $scenario['wind']['label'] = $this->windDirectionLabel((float) $scenario['wind']['direction_degrees']);
        $scenario['projection_time_hours'] = $parameters['projection_horizon'] ?? $scenario['projection_time_hours'];
        $scenario['simulation_started_at'] = $primaryHotspot['acquired_at'];
        $scenario['scenario_name'] = $hotspotData['scenario_name'] ?? $scenario['scenario_name'];
        $scenario['disclaimer'] = trim(($hotspotData['disclaimer'] ?? $scenario['disclaimer']).' '.$weatherData['message'].' '.$facilityData['message']);

        $hotspot = $scenario['hotspot_cluster'];
        $wind = $scenario['wind'];
        $projection = $this->smokeProjectionService->project(
            (float) $hotspot['latitude'],
            (float) $hotspot['longitude'],
            (float) $wind['direction_degrees'],
            (float) $wind['speed_kmh'],
            (float) $scenario['projection_time_hours'],
        );
        $exposures = $this->exposureService->evaluate(
            $scenario['facilities'],
            (float) $hotspot['latitude'],
            (float) $hotspot['longitude'],
            (float) $wind['direction_degrees'],
            (float) $wind['speed_kmh'],
            $projection,
        );
        $scenarioStartedAt = CarbonImmutable::parse($scenario['simulation_started_at']);
        $facilityResults = $this->interventionWindowService->calculate($exposures, $scenarioStartedAt);
        $facilityResults = array_map(function (array $facilityResult) use ($wind): array {
            return [
                ...$facilityResult,
                'why_this_result' => $this->explanationService->explain(
                    $facilityResult,
                    (float) $wind['direction_degrees'],
                    (string) $wind['label'],
                ),
                'response_plan' => $this->responsePlannerService->plan($facilityResult),
            ];
        }, $facilityResults);
        $priorityQueue = $this->responsePriorityService->rank($facilityResults, $scenarioStartedAt);

        $hotspots = $hotspotData['hotspots'] ?? (isset($hotspotData['primary_hotspot']) ? [$hotspotData['primary_hotspot']] : []);
        $overview = $this->impactOverviewService->scan(
            $hotspots,
            $scenario['facilities'],
            (float) $wind['direction_degrees'],
            (float) $wind['speed_kmh'],
            (float) $scenario['projection_time_hours'],
            (string) $hotspotData['mode'],
            [
                'weather' => [
                    'source' => $weatherData['source'] ?? null,
                    'status' => $weatherData['status'] ?? null,
                    'observation_at' => $weatherData['observation_at'] ?? null,
                ],
                'facilities' => [
                    'source' => $facilityData['source'] ?? null,
                    'status' => $facilityData['status'] ?? null,
                    'retrieved_at' => $facilityData['retrieved_at'] ?? null,
                ],
            ],
        );

        $impactMap = [];
        foreach ($overview['items'] as $item) {
            $impactMap[$item['hotspot_id']] = $item;
        }

        if (isset($hotspotData['hotspots']) && is_array($hotspotData['hotspots'])) {
            $hotspotData['hotspots'] = array_map(static function (array $h) use ($impactMap): array {
                $id = $h['id'] ?? null;

                return [
                    ...$h,
                    'impact' => $id !== null && isset($impactMap[$id]) ? $impactMap[$id] : null,
                ];
            }, $hotspotData['hotspots']);
        }

        return [
            'application' => 'SENTRA - Smoke Exposure Notification, Tracking, Response & Analysis',
            'simulation_only' => ($hotspotData['mode'] ?? 'simulation') === 'simulation',
            'scenario_modified' => $parameters !== [],
            'data_mode' => $hotspotData,
            'hotspot_impact_overview' => $overview,
            'weather_data' => $weatherData,
            'facility_data' => $facilityData,
            'disclaimer' => $scenario['disclaimer'],
            'scenario' => $scenario,
            'projection' => $projection,
            'facility_results' => $facilityResults,
            'response_priority_queue' => $priorityQueue,
            'summary' => $this->summaryService->summarize($facilityResults, $priorityQueue),
        ];
    }

    private function windDirectionLabel(float $degrees): string
    {
        $directions = ['North', 'Northeast', 'East', 'Southeast', 'South', 'Southwest', 'West', 'Northwest'];

        return $directions[(int) floor(($degrees + 22.5) / 45) % 8];
    }

    /** @return array<string, mixed> */
    private function loadScenario(): array
    {
        $contents = file_get_contents(storage_path('app/demo-scenario.json'));

        if ($contents === false) {
            throw new RuntimeException('Skenario simulasi tidak dapat dimuat.');
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
