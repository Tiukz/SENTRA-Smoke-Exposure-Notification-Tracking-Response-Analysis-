<?php

declare(strict_types=1);

namespace App\Services;

final class ActiveHotspotService
{
    public const SESSION_SELECTED_ID = 'sentra.selected_hotspot_id';

    public const SESSION_SELECTED_MODE = 'sentra.selected_hotspot_mode';

    public const SESSION_CURRENT_MODE = 'sentra.current_data_mode';

    public function __construct(private readonly HotspotModeService $hotspotModeService) {}

    /**
     * Generate a deterministic identifier for a normalized hotspot based on its source coordinates and timestamp.
     *
     * @param  array<string, mixed>  $hotspot
     */
    public function generateId(array $hotspot): string
    {
        if (isset($hotspot['id']) && is_string($hotspot['id']) && $hotspot['id'] !== '') {
            return $hotspot['id'];
        }

        $lat = (float) ($hotspot['latitude'] ?? 0.0);
        $lng = (float) ($hotspot['longitude'] ?? 0.0);
        $time = (string) ($hotspot['acquired_at'] ?? '');

        return substr(hash('sha256', sprintf('%.6f,%.6f,%s', $lat, $lng, $time)), 0, 16);
    }

    /**
     * Resolve hotspot data for the given mode, attaching deterministic IDs and respecting active session selection.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $mode): array
    {
        $hotspotData = $this->hotspotModeService->get($mode);

        return $this->enrich($hotspotData, $mode);
    }

    /**
     * Enrich hotspot payload with deterministic IDs and resolve active hotspot selection.
     *
     * @param  array<string, mixed>  $hotspotData
     * @return array<string, mixed>
     */
    public function enrich(array $hotspotData, ?string $mode = null): array
    {
        $mode = $mode ?? (string) ($hotspotData['mode'] ?? 'snapshot');
        $hotspots = $hotspotData['hotspots'] ?? [];

        if (! is_array($hotspots) || $hotspots === []) {
            $hotspotData['active_hotspot_id'] = null;

            return $hotspotData;
        }

        $enrichedHotspots = array_map(function (array $hotspot): array {
            return [
                ...$hotspot,
                'id' => $this->generateId($hotspot),
            ];
        }, $hotspots);

        $hotspotData['hotspots'] = $enrichedHotspots;

        $selectedId = session(self::SESSION_SELECTED_ID);
        $selectedMode = session(self::SESSION_SELECTED_MODE);

        $activeHotspot = null;
        if (is_string($selectedId) && $selectedMode === $mode) {
            foreach ($enrichedHotspots as $candidate) {
                if ($candidate['id'] === $selectedId) {
                    $activeHotspot = $candidate;
                    break;
                }
            }
        }

        if ($activeHotspot === null) {
            $activeHotspot = $enrichedHotspots[0] ?? null;
        }

        if (is_array($activeHotspot)) {
            $hotspotData['primary_hotspot'] = $activeHotspot;
            $hotspotData['active_hotspot_id'] = $activeHotspot['id'];
        }

        return $hotspotData;
    }

    /**
     * Select a specific hotspot by ID within the active data mode.
     * Returns the enriched hotspot data with the selected hotspot active, or null if invalid.
     *
     * @return array<string, mixed>|null
     */
    public function select(string $hotspotId, string $mode): ?array
    {
        $hotspotData = $this->hotspotModeService->get($mode);

        if (! ($hotspotData['available'] ?? false)) {
            return null;
        }

        $enriched = $this->enrich($hotspotData, $mode);
        $hotspots = $enriched['hotspots'] ?? [];

        $selectedHotspot = null;
        foreach ($hotspots as $hotspot) {
            if (($hotspot['id'] ?? null) === $hotspotId) {
                $selectedHotspot = $hotspot;
                break;
            }
        }

        if ($selectedHotspot === null) {
            return null;
        }

        session([
            self::SESSION_SELECTED_ID => $hotspotId,
            self::SESSION_SELECTED_MODE => $mode,
            self::SESSION_CURRENT_MODE => $mode,
        ]);

        $enriched['primary_hotspot'] = $selectedHotspot;
        $enriched['active_hotspot_id'] = $hotspotId;

        return $enriched;
    }

    /**
     * Clear temporary session selection.
     */
    public function clear(): void
    {
        session()->forget([
            self::SESSION_SELECTED_ID,
            self::SESSION_SELECTED_MODE,
        ]);
    }

    public function getSelectedId(): ?string
    {
        $id = session(self::SESSION_SELECTED_ID);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function getSelectedMode(): ?string
    {
        $mode = session(self::SESSION_SELECTED_MODE);

        return is_string($mode) && $mode !== '' ? $mode : null;
    }
}
