<?php

declare(strict_types=1);

namespace App\Services;

final class RouteRecommendationService
{
    /**
     * @param  list<array<string, mixed>>  $routes
     * @return array{routes: list<array<string, mixed>>, recommendation: array<string, mixed>}
     */
    public function recommend(array $routes): array
    {
        usort($routes, fn (array $left, array $right): int => [$left['duration_minutes'], $left['id']] <=> [$right['duration_minutes'], $right['id']]
        );

        $fastest = $routes[0];
        $minimumReduction = (float) config('services.route_exposure.minimum_overlap_reduction_points', 10);
        $maximumSlowerPercent = (float) config('services.route_exposure.maximum_duration_increase_percent', 35);
        $maximumDuration = $fastest['duration_minutes'] * (1 + ($maximumSlowerPercent / 100));
        $eligible = array_values(array_filter(
            array_slice($routes, 1),
            fn (array $route): bool => ($fastest['corridor_overlap_percent'] - $route['corridor_overlap_percent']) >= $minimumReduction
                && $route['duration_minutes'] <= $maximumDuration,
        ));

        usort($eligible, fn (array $left, array $right): int => [$left['corridor_overlap_percent'], $left['duration_minutes'], $left['id']]
            <=> [$right['corridor_overlap_percent'], $right['duration_minutes'], $right['id']]
        );

        $recommended = $eligible[0] ?? $fastest;
        $routes = array_map(fn (array $route): array => [
            ...$route,
            'is_fastest' => $route['id'] === $fastest['id'],
            'is_recommended' => $route['id'] === $recommended['id'],
        ], $routes);

        $extraMinutes = max(0.0, $recommended['duration_minutes'] - $fastest['duration_minutes']);
        $reduction = max(0.0, $fastest['corridor_overlap_percent'] - $recommended['corridor_overlap_percent']);

        return [
            'routes' => $routes,
            'recommendation' => [
                'route_id' => $recommended['id'],
                'label' => $recommended['id'] === $fastest['id'] ? 'Rute Tercepat' : 'Rute Paparan Lebih Rendah',
                'extra_minutes' => round($extraMinutes, 1),
                'exposure_reduction_percentage_points' => round($reduction, 1),
                'reason' => $this->reason($recommended, $fastest, $extraMinutes),
            ],
        ];
    }

    /** @param array<string, mixed> $recommended @param array<string, mixed> $fastest */
    private function reason(array $recommended, array $fastest, float $extraMinutes): string
    {
        $recommendedLabel = $this->routeLabel($recommended['id']);
        $fastestLabel = $this->routeLabel($fastest['id']);

        if ($recommended['id'] === $fastest['id']) {
            return sprintf(
                'Rute %s direkomendasikan sebagai rute tercepat karena tidak ada alternatif yang mengurangi overlap koridor sedikitnya %s poin persentase dengan tambahan durasi maksimal %s%%.',
                $recommendedLabel,
                $this->number((float) config('services.route_exposure.minimum_overlap_reduction_points', 10)),
                $this->number((float) config('services.route_exposure.maximum_duration_increase_percent', 35)),
            );
        }

        return sprintf(
            'Rute %s direkomendasikan sebagai Rute Paparan Lebih Rendah karena hanya %s%% lintasannya melewati koridor proyeksi asap dibandingkan %s%% pada rute tercepat (Rute %s), dengan tambahan waktu sekitar %s menit.',
            $recommendedLabel,
            $this->number((float) $recommended['corridor_overlap_percent']),
            $this->number((float) $fastest['corridor_overlap_percent']),
            $fastestLabel,
            $this->number($extraMinutes),
        );
    }

    private function routeLabel(string $id): string
    {
        $number = max(1, (int) str_replace('route_', '', $id));

        return chr(64 + min(26, $number));
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
