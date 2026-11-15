<?php

declare(strict_types=1);

namespace App\Contracts\Astrology;

use Carbon\CarbonImmutable;

/**
 * Stable interface for Kundli (Birth Chart) calculations.
 *
 * Enforces exact planetary positions, Lagna (Ascendant), Bhavas,
 * and Shodashvarga calculations without loss of precision.
 */
interface KundliCalculatorInterface
{
    /**
     * Compute or retrieve complete Kundli for exact birth inputs.
     *
     * @param CarbonImmutable $birthDateTime Exact birth timestamp
     * @param float $latitude Observer geographic latitude
     * @param float $longitude Observer geographic longitude
     * @param string $timezone Canonical IANA timezone
     * @param array<string, mixed> $options Calculation options (ayanamsha, house system)
     * @return array<string, mixed> Canonical Kundli data structure
     */
    public function calculate(
        CarbonImmutable $birthDateTime,
        float $latitude,
        float $longitude,
        string $timezone,
        array $options = []
    ): array;
}
