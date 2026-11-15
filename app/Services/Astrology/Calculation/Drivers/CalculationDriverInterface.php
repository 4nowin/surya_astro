<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Drivers;

interface CalculationDriverInterface
{
    /**
     * Compute planetary positions for a Julian Day or datetime.
     *
     * @param float $jd Julian Day UT
     * @return array<string, mixed>
     */
    public function calculatePlanetaryPositions(float $jd): array;

    /**
     * Compute Daily Panchang for a location and date.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function calculateDailyPanchang(array $payload): array;

    /**
     * Compute Core Kundli & Vimshottari Dasha.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function calculateKundli(array $payload): array;

    /**
     * Compute Complete Kundli including Navamsha stability analysis, Yogas, and Doshas.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function calculateKundliComplete(array $payload): array;

    /**
     * Check whether the calculation driver/backend is healthy.
     */
    public function isHealthy(): bool;
}
