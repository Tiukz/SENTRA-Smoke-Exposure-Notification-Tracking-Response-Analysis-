<?php

declare(strict_types=1);

namespace App\Contracts;

interface WeatherProvider
{
    /** @return array<string, mixed> */
    public function get(float $latitude, float $longitude): array;
}
