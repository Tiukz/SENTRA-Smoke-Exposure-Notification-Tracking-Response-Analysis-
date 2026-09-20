<?php

declare(strict_types=1);

namespace App\Contracts;

interface HotspotProvider
{
    /** @return array<string, mixed> */
    public function get(): array;
}
