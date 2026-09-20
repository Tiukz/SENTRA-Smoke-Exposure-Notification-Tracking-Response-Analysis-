<?php

declare(strict_types=1);

namespace App\Contracts;

interface RouteProvider
{
    /**
     * @param  array{latitude: float, longitude: float}  $origin
     * @param  array{latitude: float, longitude: float}  $destination
     * @return array<string, mixed>
     */
    public function routes(array $origin, array $destination): array;
}
