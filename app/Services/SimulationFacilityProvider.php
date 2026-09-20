<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FacilityProvider;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final class SimulationFacilityProvider implements FacilityProvider
{
    public function get(float $latitude, float $longitude): array
    {
        try {
            $contents = file_get_contents(storage_path('app/demo-scenario.json'));

            if ($contents === false) {
                throw new RuntimeException('Skenario simulasi tidak dapat dibaca.');
            }

            $scenario = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $retrievedAt = CarbonImmutable::parse($scenario['simulation_started_at'])->utc()->toIso8601String();
            $facilities = array_map(static fn (array $facility, int $index): array => [
                'id' => 'simulation-'.($index + 1),
                'name' => (string) $facility['name'],
                'type' => (string) $facility['type'],
                'latitude' => (float) $facility['latitude'],
                'longitude' => (float) $facility['longitude'],
                'source' => 'SENTRA Demo Scenario',
                'retrieved_at' => $retrievedAt,
                'status' => 'simulated',
            ], $scenario['facilities'], array_keys($scenario['facilities']));

            return $this->result(true, 'simulated', $retrievedAt, $facilities, 'Fasilitas merupakan data simulasi SENTRA.');
        } catch (Throwable) {
            return $this->result(false, 'unavailable', null, [], 'Data fasilitas simulasi tidak tersedia.');
        }
    }

    /** @param list<array<string, mixed>> $facilities */
    private function result(bool $available, string $status, ?string $retrievedAt, array $facilities, string $message): array
    {
        return [
            'mode' => 'simulation',
            'available' => $available,
            'status' => $status,
            'source' => 'SENTRA Demo Scenario',
            'retrieved_at' => $retrievedAt,
            'facility_count' => count($facilities),
            'facilities' => $facilities,
            'metadata' => ['dataset_type' => 'Simulated facilities'],
            'message' => $message,
        ];
    }
}
