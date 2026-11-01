<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Services\Astrology\CanonicalPanchangCacheKey;
use PHPUnit\Framework\TestCase;

class CanonicalPanchangCacheKeyTest extends TestCase
{
    public function test_deterministic_hash_generation(): void
    {
        $attrs1 = CanonicalPanchangCacheKey::canonicalAttributes('2026-11-01', 28.613912, 77.209021, 'Asia/Kolkata');
        $attrs2 = CanonicalPanchangCacheKey::canonicalAttributes('2026-11-01', 28.6139, 77.2090, 'Asia/Kolkata');

        // Attributes should round to 4 decimal places
        $this->assertSame(28.6139, $attrs1['latitude']);
        $this->assertSame(77.2090, $attrs1['longitude']);

        $hash1 = CanonicalPanchangCacheKey::generateHash($attrs1);
        $hash2 = CanonicalPanchangCacheKey::generateHash($attrs2);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));
    }

    public function test_hash_changes_when_conventions_or_versions_change(): void
    {
        $base = CanonicalPanchangCacheKey::canonicalAttributes('2026-11-01', 28.6139, 77.2090, 'Asia/Kolkata');
        $baseHash = CanonicalPanchangCacheKey::generateHash($base);

        // Different timezone
        $diffTz = CanonicalPanchangCacheKey::canonicalAttributes('2026-11-01', 28.6139, 77.2090, 'UTC');
        $this->assertNotSame($baseHash, CanonicalPanchangCacheKey::generateHash($diffTz));

        // Different ruleset version
        $diffRuleset = CanonicalPanchangCacheKey::canonicalAttributes(
            '2026-11-01', 28.6139, 77.2090, 'Asia/Kolkata', 0.0,
            CanonicalPanchangCacheKey::DEFAULT_AYANAMSHA,
            CanonicalPanchangCacheKey::DEFAULT_SUNRISE_CONVENTION,
            CanonicalPanchangCacheKey::DEFAULT_CALENDAR_TYPE,
            CanonicalPanchangCacheKey::CURRENT_ENGINE_VERSION,
            'PAN_RULES_V2'
        );
        $this->assertNotSame($baseHash, CanonicalPanchangCacheKey::generateHash($diffRuleset));

        // Different calendar type (Purnimanta vs Amanta)
        $diffCal = CanonicalPanchangCacheKey::canonicalAttributes(
            '2026-11-01', 28.6139, 77.2090, 'Asia/Kolkata', 0.0,
            CanonicalPanchangCacheKey::DEFAULT_AYANAMSHA,
            CanonicalPanchangCacheKey::DEFAULT_SUNRISE_CONVENTION,
            'PURNIMANTA'
        );
        $this->assertNotSame($baseHash, CanonicalPanchangCacheKey::generateHash($diffCal));
    }

    public function test_circular_angular_difference(): void
    {
        // Simple linear distance
        $this->assertEqualsWithDelta(15.0, CanonicalPanchangCacheKey::circularAngularDifference(10.0, 25.0), 1e-6);

        // Wrapping around 0° / 360° boundary
        $this->assertEqualsWithDelta(0.2, CanonicalPanchangCacheKey::circularAngularDifference(359.9, 0.1), 1e-6);
        $this->assertEqualsWithDelta(0.2, CanonicalPanchangCacheKey::circularAngularDifference(0.1, 359.9), 1e-6);
        $this->assertEqualsWithDelta(20.0, CanonicalPanchangCacheKey::circularAngularDifference(350.0, 10.0), 1e-6);

        // Exact antipodal points (180°)
        $this->assertEqualsWithDelta(180.0, CanonicalPanchangCacheKey::circularAngularDifference(0.0, 180.0), 1e-6);
        $this->assertEqualsWithDelta(180.0, CanonicalPanchangCacheKey::circularAngularDifference(90.0, 270.0), 1e-6);

        // Identical angles
        $this->assertEqualsWithDelta(0.0, CanonicalPanchangCacheKey::circularAngularDifference(123.456, 123.456), 1e-6);
    }
}
