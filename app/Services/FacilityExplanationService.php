<?php

declare(strict_types=1);

namespace App\Services;

final class FacilityExplanationService
{
    /** @param array<string, mixed> $facility */
    public function explain(array $facility, float $windDirectionDegrees, string $windDirectionLabel): string
    {
        $crossTrack = number_format((float) $facility['cross_track_distance_km'], 2, ',', '.');
        $eta = $facility['approximate_eta_hours'] === null
            ? 'tidak memiliki ETA dalam horizon proyeksi'
            : 'memiliki ETA sekitar '.(int) round($facility['approximate_eta_hours'] * 60).' menit';
        $projection = $facility['within_projection']
            ? 'berada dalam panjang proyeksi asap'
            : 'berada di luar panjang proyeksi asap';

        return sprintf(
            'Fasilitas %s, berjarak %s km dari lintasan asap, dan %s. Dengan arah angin %s (%.0f°), hasilnya adalah risiko %s dan urgensi %s.',
            $projection,
            $crossTrack,
            $eta,
            $windDirectionLabel,
            $windDirectionDegrees,
            $facility['risk_level'],
            $facility['urgency_level'],
        );
    }
}
