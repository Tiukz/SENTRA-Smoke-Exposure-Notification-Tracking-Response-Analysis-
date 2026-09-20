<?php

declare(strict_types=1);

namespace App\Contracts;

interface HotspotObservationStore
{
    /** @param array<string, mixed> $hotspot */
    public function save(array $hotspot): void;
}
