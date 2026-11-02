<?php

declare(strict_types=1);

namespace App\Services\Geo;

interface GeocodingServiceInterface
{
    /**
     * Search cities by name prefix or query string.
     *
     * @param string $query
     * @param int $limit
     * @return array<int, array{
     *   name: string,
     *   state: ?string,
     *   country: string,
     *   latitude: float,
     *   longitude: float,
     *   timezone: string
     * }>
     */
    public function searchCities(string $query, int $limit = 10): array;
}
