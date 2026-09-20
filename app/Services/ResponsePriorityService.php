<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Deterministic lexicographic ranking. Every comparison is explicit and auditable.
 */
final class ResponsePriorityService
{
    private const URGENCY_ORDER = ['CRITICAL' => 4, 'HIGH' => 3, 'MODERATE' => 2, 'LOW' => 1, 'NONE' => 0];

    private const RISK_ORDER = ['HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1];

    private const VULNERABILITY_ORDER = ['hospital' => 3, 'school' => 2, 'residential' => 1];

    /**
     * @param  array<int, array<string, mixed>>  $facilityResults
     * @return array<int, array<string, mixed>>
     */
    public function rank(array $facilityResults, CarbonImmutable $referenceTime): array
    {
        $ranked = array_map(fn (array $facility): array => [
            'facility' => $facility,
            'remaining_minutes' => $this->remainingMinutes($facility['intervention_deadline'] ?? null, $referenceTime),
        ], $facilityResults);

        usort($ranked, function (array $left, array $right): int {
            $leftFacility = $left['facility'];
            $rightFacility = $right['facility'];
            $descendingComparisons = [
                [(bool) ($leftFacility['within_projection'] ?? false), (bool) ($rightFacility['within_projection'] ?? false)],
                [$this->urgencyWeight($leftFacility), $this->urgencyWeight($rightFacility)],
                [$this->riskWeight($leftFacility), $this->riskWeight($rightFacility)],
                [$this->vulnerabilityWeight($leftFacility), $this->vulnerabilityWeight($rightFacility)],
            ];

            foreach ($descendingComparisons as [$leftValue, $rightValue]) {
                if ($leftValue !== $rightValue) {
                    return $rightValue <=> $leftValue;
                }
            }

            $leftRemaining = $left['remaining_minutes'] ?? PHP_INT_MAX;
            $rightRemaining = $right['remaining_minutes'] ?? PHP_INT_MAX;

            return $leftRemaining !== $rightRemaining
                ? $leftRemaining <=> $rightRemaining
                : strcasecmp((string) ($leftFacility['facility_name'] ?? ''), (string) ($rightFacility['facility_name'] ?? ''));
        });

        return array_map(function (array $item, int $index): array {
            $facility = $item['facility'];

            return [
                'rank' => $index + 1,
                'facility_name' => $facility['facility_name'] ?? 'Fasilitas tanpa nama',
                'facility_type' => $facility['facility_type'] ?? 'unknown',
                'risk_level' => $facility['risk_level'] ?? 'LOW',
                'urgency_level' => $facility['urgency_level'] ?? 'NONE',
                'approximate_eta_hours' => $facility['approximate_eta_hours'] ?? null,
                'intervention_deadline' => $facility['intervention_deadline'] ?? null,
                'remaining_intervention_minutes' => $item['remaining_minutes'],
                'within_projection' => (bool) ($facility['within_projection'] ?? false),
                'explanation' => $this->explanation($facility, $item['remaining_minutes']),
            ];
        }, $ranked, array_keys($ranked));
    }

    /** @param array<string, mixed> $facility */
    private function urgencyWeight(array $facility): int
    {
        return self::URGENCY_ORDER[(string) ($facility['urgency_level'] ?? 'NONE')] ?? 0;
    }

    /** @param array<string, mixed> $facility */
    private function riskWeight(array $facility): int
    {
        return self::RISK_ORDER[(string) ($facility['risk_level'] ?? 'LOW')] ?? 0;
    }

    /** @param array<string, mixed> $facility */
    private function vulnerabilityWeight(array $facility): int
    {
        return self::VULNERABILITY_ORDER[(string) ($facility['facility_type'] ?? 'unknown')] ?? 0;
    }

    private function remainingMinutes(mixed $deadline, CarbonImmutable $referenceTime): ?int
    {
        if (! is_string($deadline) || trim($deadline) === '') {
            return null;
        }

        try {
            return (int) floor((CarbonImmutable::parse($deadline)->timestamp - $referenceTime->timestamp) / 60);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $facility */
    private function explanation(array $facility, ?int $remainingMinutes): string
    {
        $name = (string) ($facility['facility_name'] ?? 'Fasilitas tanpa nama');
        $risk = (string) ($facility['risk_level'] ?? 'LOW');
        $urgency = (string) ($facility['urgency_level'] ?? 'NONE');
        $type = (string) ($facility['facility_type'] ?? 'unknown');

        if (! ($facility['within_projection'] ?? false)) {
            return "{$name} ditempatkan setelah fasilitas dalam horizon karena berada di luar proyeksi saat ini.";
        }

        $typeLabel = match ($type) {
            'hospital' => 'rumah sakit dengan kerentanan tertinggi',
            'school' => 'sekolah dengan kerentanan tinggi',
            'residential' => 'permukiman dengan kerentanan umum',
            default => 'fasilitas dengan kerentanan standar',
        };
        $riskLabel = ['HIGH' => 'TINGGI', 'MEDIUM' => 'SEDANG', 'LOW' => 'RENDAH'][$risk] ?? 'TIDAK DIKETAHUI';
        $urgencyLabel = ['CRITICAL' => 'KRITIS', 'HIGH' => 'TINGGI', 'MODERATE' => 'SEDANG', 'LOW' => 'RENDAH', 'NONE' => 'TIDAK ADA'][$urgency] ?? 'TIDAK DIKETAHUI';
        $explanation = "{$name} diprioritaskan karena memiliki urgensi {$urgency} ({$urgencyLabel}), risiko paparan {$risk} ({$riskLabel}), dan merupakan {$typeLabel}.";

        return $explanation.' '.$this->remainingTimeExplanation($remainingMinutes);
    }

    private function remainingTimeExplanation(?int $remainingMinutes): string
    {
        return match (true) {
            $remainingMinutes === null => 'Tidak ada tenggat intervensi dalam horizon saat ini.',
            $remainingMinutes < 0 => 'Tenggat intervensi telah terlewati '.abs($remainingMinutes).' menit.',
            $remainingMinutes === 0 => 'Tenggat intervensi jatuh sekarang.',
            default => "Tersisa {$remainingMinutes} menit sebelum tenggat intervensi.",
        };
    }
}
