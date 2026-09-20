<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RouteProvider;
use App\Http\Requests\CalculateRouteRequest;
use App\Services\ActiveScenarioService;
use App\Services\RouteExposureService;
use App\Services\RouteRecommendationService;
use Illuminate\Http\JsonResponse;

final class RouteController extends Controller
{
    public function __construct(
        private readonly RouteProvider $routeProvider,
        private readonly RouteExposureService $routeExposureService,
        private readonly RouteRecommendationService $routeRecommendationService,
        private readonly ActiveScenarioService $activeScenarioService,
    ) {}

    public function calculate(CalculateRouteRequest $request): JsonResponse
    {
        $corridor = $this->activeScenarioService->current();

        if ($corridor === null) {
            return response()->json([
                'status' => 'scenario_required',
                'message' => 'Skenario aktif belum tersedia. Buka dashboard atau pilih mode data terlebih dahulu.',
            ], 409);
        }

        $validated = $request->validated();
        $origin = $this->coordinates($validated['origin']);
        $destination = $this->coordinates($validated['destination']);
        $providerResult = $this->routeProvider->routes($origin, $destination);

        if (! ($providerResult['available'] ?? false)) {
            return response()->json($providerResult, $this->failureStatus((string) ($providerResult['status'] ?? 'unavailable')));
        }

        $evaluatedRoutes = array_map(
            fn (array $route): array => $this->routeExposureService->evaluate($route, $corridor),
            $providerResult['routes'],
        );
        $recommendation = $this->routeRecommendationService->recommend($evaluatedRoutes);

        return response()->json([
            'status' => 'ok',
            'source' => $providerResult['source'],
            'mode' => $corridor['mode'],
            'scenario_fingerprint' => $corridor['fingerprint'],
            'scenario_version' => $corridor['version'],
            'calculated_at' => now('UTC')->toIso8601String(),
            'exposure_basis' => 'Kategori paparan hanya menunjukkan overlap geometris rute dengan koridor proyeksi asap SENTRA, bukan dosis paparan yang tervalidasi secara medis.',
            ...$recommendation,
        ]);
    }

    /** @param array<string, mixed> $coordinates */
    private function coordinates(array $coordinates): array
    {
        return [
            'latitude' => (float) $coordinates['latitude'],
            'longitude' => (float) $coordinates['longitude'],
        ];
    }

    private function failureStatus(string $status): int
    {
        return match ($status) {
            'no_route' => 404,
            'malformed_response' => 502,
            default => 503,
        };
    }
}
