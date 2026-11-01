<?php

declare(strict_types=1);

namespace App\Services\Astrology;

use InvalidArgumentException;

/**
 * Generates a canonical, deterministic cache identity for Vedic Panchanga calculations.
 *
 * Prevents key collision and handles all parameters influencing astronomical
 * and electional outcomes: date, coordinates, timezone, elevation, ayanamsha,
 * sunrise convention, engine version, and calculation rule version.
 */
final class CanonicalPanchangCacheKey
{
    public const DEFAULT_AYANAMSHA = 'TRUE_CITRA_LAHIRI';
    public const DEFAULT_SUNRISE_CONVENTION = 'UPPER_LIMB_REFRACTION_50';
    public const DEFAULT_CALENDAR_TYPE = 'AMANTA';
    public const CURRENT_ENGINE_VERSION = 'JME_2.0.0_MOSHIER';
    public const CURRENT_RULESET_VERSION = 'PAN_RULES_V1';

    /**
     * Build the canonical normalized attributes array.
     *
     * @param string $localDate Format: YYYY-MM-DD
     * @param float $latitude Decimal degrees (-90.0 to +90.0)
     * @param float $longitude Decimal degrees (-180.0 to +180.0)
     * @param string $timezone Canonical IANA timezone identifier
     * @param float $elevation Elevation in meters above sea level
     * @param string $ayanamsha Ayanamsha identifier
     * @param string $sunriseConvention Solar disc refraction convention
     * @param string $calendarType Lunar month convention (AMANTA or PURNIMANTA)
     * @param string $engineVersion Native ephemeris engine version
     * @param string $rulesetVersion Panchang calculation rules version
     * @return array<string, mixed>
     */
    public static function canonicalAttributes(
        string $localDate,
        float $latitude,
        float $longitude,
        string $timezone,
        float $elevation = 0.0,
        string $ayanamsha = self::DEFAULT_AYANAMSHA,
        string $sunriseConvention = self::DEFAULT_SUNRISE_CONVENTION,
        string $calendarType = self::DEFAULT_CALENDAR_TYPE,
        string $engineVersion = self::CURRENT_ENGINE_VERSION,
        string $rulesetVersion = self::CURRENT_RULESET_VERSION
    ): array {
        // Validate date format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDate)) {
            throw new InvalidArgumentException("Invalid date format: {$localDate}. Expected YYYY-MM-DD.");
        }

        // Canonical normalization
        $attributes = [
            'ayanamsha' => strtoupper(trim($ayanamsha)),
            'calendar_type' => strtoupper(trim($calendarType)),
            'elevation_m' => (int) round($elevation),
            'engine_version' => trim($engineVersion),
            'latitude' => round($latitude, 4), // ~11 meters geographic precision
            'local_date' => $localDate,
            'longitude' => round($longitude, 4),
            'ruleset_version' => trim($rulesetVersion),
            'sunrise_convention' => strtoupper(trim($sunriseConvention)),
            'timezone' => trim($timezone),
        ];

        // Sort keys alphabetically to guarantee determinism
        ksort($attributes);

        return $attributes;
    }

    /**
     * Generate the deterministic 64-character SHA-256 hash.
     *
     * @param array<string, mixed> $attributes
     */
    public static function generateHash(array $attributes): string
    {
        $canonicalJson = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($canonicalJson === false) {
            throw new InvalidArgumentException('Failed to serialize canonical Panchang attributes to JSON.');
        }

        return hash('sha256', $canonicalJson);
    }

    /**
     * Calculate circular angular difference between two angles in degrees (0 - 360).
     *
     * Correctly handles 0° / 360° boundary wraparounds.
     * Result is always the minimum angular distance in [0, 180] degrees.
     */
    public static function circularAngularDifference(float $angle1, float $angle2): float
    {
        $diff = fmod(abs($angle1 - $angle2), 360.0);
        return $diff > 180.0 ? 360.0 - $diff : $diff;
    }
}
