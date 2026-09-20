<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SelectHotspotRequest;
use App\Http\Requests\SimulateScenarioRequest;
use App\Http\Requests\SwitchDataModeRequest;
use App\Services\ActiveHotspotService;
use App\Services\ActiveScenarioService;
use App\Services\FacilityModeService;
use App\Services\HotspotModeService;
use App\Services\ScenarioEngineService;
use App\Services\WeatherModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;

final class HazeController extends Controller
{
    public function __construct(
        private readonly ScenarioEngineService $scenarioEngineService,
        private readonly HotspotModeService $hotspotModeService,
        private readonly WeatherModeService $weatherModeService,
        private readonly FacilityModeService $facilityModeService,
        private readonly ActiveScenarioService $activeScenarioService,
        private readonly ActiveHotspotService $activeHotspotService,
    ) {}

    public function index(): View
    {
        $this->activeHotspotService->clear();
        session(['sentra.current_data_mode' => 'snapshot']);

        $result = $this->defaultResult();
        $this->activeScenarioService->store($result);

        return view('dashboard', [
            'simulation' => $result,
        ]);
    }

    public function demo(): JsonResponse
    {
        try {
            $this->activeHotspotService->clear();
            session(['sentra.current_data_mode' => 'snapshot']);

            $result = $this->defaultResult();
            $this->activeScenarioService->store($result);

            return response()->json($result);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Skenario demo tidak dapat dimuat.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function simulate(SimulateScenarioRequest $request): JsonResponse
    {
        $parameters = $request->validated();
        session(['sentra.current_data_mode' => 'simulation']);

        $hotspotData = $this->activeHotspotService->resolve('simulation');
        $weatherData = $this->weatherFor('simulation', $hotspotData);
        $facilityData = $this->facilitiesFor('simulation', $hotspotData);

        $result = $this->scenarioEngineService->run([
            'wind_direction' => (float) $parameters['wind_direction'],
            'wind_speed' => (float) $parameters['wind_speed'],
            'projection_horizon' => (float) $parameters['projection_horizon'],
        ], $hotspotData, $weatherData, $facilityData);
        $this->activeScenarioService->store($result);

        return response()->json($result);
    }

    public function switchDataMode(SwitchDataModeRequest $request): JsonResponse
    {
        $mode = $request->validated('mode');
        $this->activeHotspotService->clear();
        session(['sentra.current_data_mode' => $mode]);

        $hotspotData = $this->activeHotspotService->resolve($mode);

        if (! $hotspotData['available']) {
            return response()->json(['data_mode' => $hotspotData], 503);
        }

        if ($hotspotData['primary_hotspot'] === null) {
            return response()->json(['data_mode' => $hotspotData]);
        }

        $weatherData = $this->weatherFor($mode, $hotspotData);

        if (! $weatherData['available']) {
            return response()->json([
                'data_mode' => $hotspotData,
                'weather_data' => $weatherData,
            ], 503);
        }

        $facilityData = $this->facilitiesFor($mode, $hotspotData);

        if (! $facilityData['available'] || ($facilityData['facilities'] ?? []) === []) {
            return response()->json([
                'data_mode' => $hotspotData,
                'weather_data' => $weatherData,
                'facility_data' => $facilityData,
            ], 503);
        }

        $result = $this->scenarioEngineService->run([], $hotspotData, $weatherData, $facilityData);
        $this->activeScenarioService->store($result);

        return response()->json($result);
    }

    public function selectHotspot(SelectHotspotRequest $request): JsonResponse
    {
        $activeMode = (string) session('sentra.current_data_mode', 'snapshot');
        $requestedMode = $request->validated('mode');

        if ($requestedMode !== null && $requestedMode !== $activeMode) {
            return response()->json([
                'status' => 'mode_mismatch',
                'message' => 'Hotspot berasal dari mode data yang berbeda dari mode aktif saat ini.',
            ], 422);
        }

        $hotspotId = (string) $request->validated('hotspot_id');
        $hotspotData = $this->activeHotspotService->select($hotspotId, $activeMode);

        if ($hotspotData === null) {
            return response()->json([
                'status' => 'hotspot_not_found',
                'message' => 'Hotspot tidak ditemukan pada mode data aktif.',
            ], 422);
        }

        $weatherData = $this->weatherFor($activeMode, $hotspotData);

        if (! ($weatherData['available'] ?? false)) {
            return response()->json([
                'data_mode' => $hotspotData,
                'weather_data' => $weatherData,
            ], 503);
        }

        $facilityData = $this->facilitiesFor($activeMode, $hotspotData);

        if (! ($facilityData['available'] ?? false) || ($facilityData['facilities'] ?? []) === []) {
            return response()->json([
                'data_mode' => $hotspotData,
                'weather_data' => $weatherData,
                'facility_data' => $facilityData,
            ], 503);
        }

        $result = $this->scenarioEngineService->run([], $hotspotData, $weatherData, $facilityData);
        $this->activeScenarioService->store($result);

        return response()->json($result);
    }

    public function responsePlan(int $facility): JsonResponse
    {
        $facilityResult = $this->simulationResult()['facility_results'][$facility] ?? null;

        if (! is_array($facilityResult)) {
            return response()->json(['message' => 'Fasilitas simulasi tidak ditemukan.'], 404);
        }

        $plan = $facilityResult['response_plan'];
        unset($facilityResult['response_plan']);

        return response()->json([
            'source' => 'local_response_engine',
            'engine_output' => $facilityResult,
            'plan' => $plan,
        ]);
    }

    /** @return array<string, mixed> */
    private function simulationResult(): array
    {
        $hotspotData = $this->activeHotspotService->resolve('simulation');

        return $this->scenarioEngineService->run(
            [],
            $hotspotData,
            $this->weatherFor('simulation', $hotspotData),
            $this->facilitiesFor('simulation', $hotspotData),
        );
    }

    /** @return array<string, mixed> */
    private function defaultResult(): array
    {
        $hotspotData = $this->activeHotspotService->resolve('snapshot');

        if (! $hotspotData['available']) {
            throw new \RuntimeException($hotspotData['message']);
        }

        $weatherData = $this->weatherFor('snapshot', $hotspotData);

        if (! $weatherData['available']) {
            throw new \RuntimeException($weatherData['message']);
        }

        $facilityData = $this->facilitiesFor('snapshot', $hotspotData);

        if (! $facilityData['available']) {
            throw new \RuntimeException($facilityData['message']);
        }

        return $this->scenarioEngineService->run([], $hotspotData, $weatherData, $facilityData);
    }

    /** @param array<string, mixed> $hotspotData */
    private function weatherFor(string $mode, array $hotspotData): array
    {
        $hotspot = $hotspotData['primary_hotspot'];

        return $this->weatherModeService->get(
            $mode,
            (float) $hotspot['latitude'],
            (float) $hotspot['longitude'],
        );
    }

    /** @param array<string, mixed> $hotspotData */
    private function facilitiesFor(string $mode, array $hotspotData): array
    {
        $hotspot = $hotspotData['primary_hotspot'];

        return $this->facilityModeService->get(
            $mode,
            (float) $hotspot['latitude'],
            (float) $hotspot['longitude'],
        );
    }
}
