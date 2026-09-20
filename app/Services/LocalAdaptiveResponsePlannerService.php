<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Lapisan pendukung keputusan deterministik berbasis aturan dan knowledge base lokal.
 * Setiap rekomendasi dapat ditelusuri ke jenis fasilitas, urgensi, dan kondisi proyeksi.
 */
final class LocalAdaptiveResponsePlannerService
{
    /**
     * @param  array<string, mixed>  $facilityResult
     * @return array{summary: string, actions: list<string>, priority: string, deadline: mixed, source: string}
     */
    public function plan(array $facilityResult): array
    {
        $facilityName = (string) ($facilityResult['facility_name'] ?? 'Fasilitas tanpa nama');
        $facilityType = strtolower((string) ($facilityResult['facility_type'] ?? 'default'));
        $risk = strtoupper((string) ($facilityResult['risk_level'] ?? 'UNKNOWN'));
        $urgency = strtoupper((string) ($facilityResult['urgency_level'] ?? 'LOW'));
        $withinProjection = ($facilityResult['within_projection'] ?? false) === true;
        $deadline = $facilityResult['intervention_deadline'] ?? null;
        $deadlineLabel = $this->deadlineLabel($deadline);

        $plans = (array) config('response_plans.facilities', []);
        $facilityPlans = (array) ($plans[$facilityType] ?? $plans['default'] ?? []);
        $actions = $withinProjection
            ? (array) ($facilityPlans[$urgency] ?? $facilityPlans['LOW'] ?? [])
            : (array) config('response_plans.outside_projection', []);

        if ($withinProjection && $risk === 'LOW' && $urgency === 'HIGH') {
            array_unshift($actions, 'Pertahankan status risiko spasial RENDAH sambil meningkatkan kesiapan karena waktu kedatangan relatif dekat.');
        }

        if ($withinProjection && $deadlineLabel !== null) {
            $actions[] = "Selesaikan tindakan pencegahan sebelum {$deadlineLabel}.";
        }

        return [
            'summary' => $this->summary($facilityName, $risk, $urgency, $facilityResult, $withinProjection, $deadlineLabel),
            'actions' => array_values(array_unique($actions)),
            'priority' => $this->priority($risk, $urgency, $withinProjection),
            'deadline' => $deadline,
            'source' => 'local_response_engine',
        ];
    }

    /** @param array<string, mixed> $facilityResult */
    private function summary(
        string $facilityName,
        string $risk,
        string $urgency,
        array $facilityResult,
        bool $withinProjection,
        ?string $deadlineLabel,
    ): string {
        $riskLabel = ['HIGH' => 'TINGGI', 'MEDIUM' => 'SEDANG', 'LOW' => 'RENDAH'][$risk] ?? 'TIDAK DIKETAHUI';
        $urgencyLabel = ['CRITICAL' => 'KRITIS', 'HIGH' => 'TINGGI', 'MODERATE' => 'SEDANG', 'LOW' => 'RENDAH'][$urgency] ?? 'TIDAK DIKETAHUI';
        $summary = "{$facilityName} memiliki risiko paparan {$risk} ({$riskLabel}) dengan urgensi {$urgency} ({$urgencyLabel}).";

        if (! $withinProjection) {
            $summary .= ' Fasilitas berada di luar horizon proyeksi saat ini, sehingga respons difokuskan pada pemantauan.';
        } elseif ($risk === 'LOW' && $urgency === 'HIGH') {
            $summary .= ' Waktu kedatangan proyeksi relatif dekat, tetapi paparan spasial tetap rendah.';
        } elseif ($risk === 'HIGH' && $urgency === 'CRITICAL') {
            $summary .= ' Tindakan perlindungan perlu dimulai segera.';
        }

        $eta = $facilityResult['approximate_eta_hours'] ?? null;
        $summary .= is_numeric($eta) && (float) $eta >= 0
            ? " ETA perkiraan dari engine adalah {$eta} jam."
            : ' ETA belum tersedia dari engine.';

        if ($deadlineLabel !== null) {
            $summary .= " Tindakan pencegahan perlu diselesaikan sebelum {$deadlineLabel}.";
        }

        return $summary;
    }

    private function priority(string $risk, string $urgency, bool $withinProjection): string
    {
        if (! $withinProjection) {
            return 'PEMANTAUAN';
        }

        if ($risk === 'HIGH' && $urgency === 'CRITICAL') {
            return 'TINDAKAN SEGERA';
        }

        if ($risk === 'LOW' && $urgency === 'HIGH') {
            return 'SIAGA WAKTU';
        }

        return match ($urgency) {
            'CRITICAL' => 'SEGERA',
            'HIGH' => 'PRIORITAS TINGGI',
            'MODERATE' => 'PRIORITAS SEDANG',
            default => 'PEMANTAUAN RUTIN',
        };
    }

    private function deadlineLabel(mixed $deadline): ?string
    {
        if (! is_string($deadline) || trim($deadline) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($deadline)
                ->setTimezone('Asia/Makassar')
                ->format('H:i').' WITA';
        } catch (Throwable) {
            return null;
        }
    }
}
