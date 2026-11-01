<?php

declare(strict_types=1);

namespace App\Contracts\Astrology;

use Carbon\CarbonImmutable;

/**
 * Stable interface for Panchanga calculations.
 *
 * Keeps controllers, services, and the Flutter API independent of
 * whether calculations are executed via local FFI, background CLI queue,
 * or an external microservice.
 */
interface PanchangCalculatorInterface
{
    /**
     * Compute or retrieve core daily Panchanga.
     *
     * @param CarbonImmutable $date Target calendar date
     * @param float $latitude Observer geographic latitude
     * @param float $longitude Observer geographic longitude
     * @param string $timezone Canonical IANA timezone
     * @param array<string, mixed> $options Calculation options (e.g. skip_festivals)
     * @return array<string, mixed> Canonical Panchanga data structure
     */
    public function calculate(
        CarbonImmutable $date,
        float $latitude,
        float $longitude,
        string $timezone,
        array $options = []
    ): array;
}
