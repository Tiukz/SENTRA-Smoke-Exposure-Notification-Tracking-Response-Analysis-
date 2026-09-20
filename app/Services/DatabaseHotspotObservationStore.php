<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HotspotObservationStore;
use App\Models\HotspotObservation;

final class DatabaseHotspotObservationStore implements HotspotObservationStore
{
    public function save(array $hotspot): void
    {
        HotspotObservation::query()->upsert(
            [[
                'latitude' => $hotspot['latitude'],
                'longitude' => $hotspot['longitude'],
                'acquired_at' => $hotspot['acquired_at'],
                'source' => $hotspot['source'],
                'confidence' => $hotspot['confidence'],
                'frp' => $hotspot['frp'],
                'satellite' => $hotspot['satellite'],
                'instrument' => $hotspot['instrument'],
            ]],
            ['latitude', 'longitude', 'acquired_at', 'source'],
            ['confidence', 'frp', 'satellite', 'instrument'],
        );
    }
}
