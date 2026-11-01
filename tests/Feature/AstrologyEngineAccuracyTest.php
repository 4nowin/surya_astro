<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Astrology\CanonicalPanchangCacheKey;
use Carbon\CarbonImmutable;
use Composer\Autoload\ClassLoader;
use FFI;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use Tests\TestCase;

/**
 * Validates the accuracy of Astro Setu's native ephemeris and Vedic astrology engine
 * against authoritative ground-truth astronomical fixtures (NASA JPL Horizons,
 * Positional Astronomy Centre / Indian Ephemeris, and Drik Panchang).
 */
class AstrologyEngineAccuracyTest extends TestCase
{
    protected ?JmeEphFFI $jme = null;
    protected string $activeLibraryPath = '';
    protected string $activeLibraryTier = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('FFI') || !class_exists('FFI')) {
            $this->markTestSkipped('PHP FFI extension is not loaded in this environment. (Confirmed active on Hostinger CLI PHP 8.4.27).');
        }

        // Register autoloader for isolated packages if not already loaded
        $jmeSrc = base_path('packages/astrology/jpl-moshier-ephemeris-php/src/');
        $panchangSrc = base_path('packages/astrology/panchang-core/src/');

        if (!class_exists(JmeEphFFI::class)) {
            $loader = new ClassLoader();
            $loader->addPsr4('JmeEph\\', $jmeSrc);
            $loader->addPsr4('JayeshMepani\\PanchangCore\\', $panchangSrc);
            $loader->register();
        }

        // Determine library using central resolver
        $this->activeLibraryPath = \App\Services\Astrology\NativeEphemerisResolver::resolve();
        $this->activeLibraryTier = PHP_OS_FAMILY === 'Windows'
            ? 'Windows x64 Native DLL'
            : 'Linux x86_64 glibc 2.28 Standalone Moshier';

        config([
            'panchang.library_path' => $this->activeLibraryPath,
            'jmeeph.library_path' => $this->activeLibraryPath,
            'panchang.jme_settings.mode' => 'moshier',
        ]);

        $this->jme = new JmeEphFFI($this->activeLibraryPath);
        $this->jme->configureEngine('MOSHIER');
        JmeRuntime::assertSunMoonEngine($this->jme, 'MOSHIER');
        JmeRuntime::setTrueCitra($this->jme);
    }

    /**
     * Assert circular degrees match within tolerance, correctly wrapping 0° / 360°.
     */
    protected function assertCircularDegreesMatch(float $expected, float $actual, float $toleranceDeg, string $message = ''): void
    {
        $angularDistance = CanonicalPanchangCacheKey::circularAngularDifference($expected, $actual);
        $this->assertLessThanOrEqual(
            $toleranceDeg,
            $angularDistance,
            sprintf("%s [Expected: %.4f°, Actual: %.4f°, Angular Diff: %.5f°, Max Allowed: %.4f°]", $message, $expected, $actual, $angularDistance, $toleranceDeg)
        );
    }

    /**
     * Test 1: Julian Day conversion at Epoch J2000.0 (UT vs TT distinction).
     * Reference: Standard Astronomical Ephemeris definition.
     * At 2000-01-01 12:00:00 UT, Julian Day is exactly 2451545.0 UT.
     */
    public function test_julian_day_calculation_matches_j2000_ut(): void
    {
        $jd = $this->jme->jme_julian_day(2000, 1, 1, 12.0, JmeEphFFI::JME_CALENDAR_GREGORIAN);
        $this->assertEqualsWithDelta(2451545.0, $jd, 1e-6, 'Julian Day UT for J2000.0 must be 2451545.0');
    }

    /**
     * Test 2: True Chitra Paksha (Lahiri) Ayanamsha at Epoch J2000.0.
     * Reference: Positional Astronomy Centre / Indian Ephemeris (IMD, Govt. of India).
     * Standard Chitra Ayanamsha at 2000-01-01 12:00 TT is 23° 51' 11.2" ≈ 23.85311°.
     * Permissible tolerance: ±0.005° (18 arcseconds).
     */
    public function test_true_citra_lahiri_ayanamsha_accuracy(): void
    {
        $jd = 2451545.0; // J2000.0 UT
        $ayanamsha = $this->jme->jme_get_ayanamsa_ut($jd);

        $this->assertEqualsWithDelta(
            23.8531,
            $ayanamsha,
            0.005,
            sprintf('Lahiri Ayanamsha out of tolerance: calculated %.5f° vs reference 23.8531°', $ayanamsha)
        );
    }

    /**
     * Test 3: Planetary positions during Total Solar Eclipse (2024-04-08 18:18:00 UTC).
     * Reference: NASA JPL Horizons Geocentric Apparent Tropical Coordinates:
     * - Sun Apparent Lon: ~19.400° (Aries 19° 24')
     * - Moon Apparent Lon: ~19.398°
     * Permissible tolerance for Moshier analytical theory vs JPL DE440: ±0.05° (3 arcmin).
     */
    public function test_solar_eclipse_planetary_positions(): void
    {
        // 2024-04-08 18:18:00 UTC = decimal hour 18.30
        $jd = $this->jme->jme_julian_day(2024, 4, 8, 18.3, JmeEphFFI::JME_CALENDAR_GREGORIAN);

        $ffi = $this->jme->getFFI();
        $sunRes = $ffi->new('double[6]');
        $moonRes = $ffi->new('double[6]');
        $err = $ffi->new('char[256]');

        $rcSun = $this->jme->jme_calc_ut($jd, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_CALC_SPEED, $sunRes, $err);
        $this->assertSame(JmeEphFFI::JME_OK, $rcSun, 'Sun calculation failed: ' . FFI::string($err));

        $rcMoon = $this->jme->jme_calc_ut($jd, JmeEphFFI::JME_BODY_MOON, JmeEphFFI::JME_CALC_SPEED, $moonRes, $err);
        $this->assertSame(JmeEphFFI::JME_OK, $rcMoon, 'Moon calculation failed: ' . FFI::string($err));

        $sunLon = $sunRes[0];
        $moonLon = $moonRes[0];

        // 1. Sun should match NASA Horizons 19.40° within 0.05°
        $this->assertCircularDegreesMatch(19.400, $sunLon, 0.05, 'Sun tropical longitude deviates from JPL Horizons');

        // 2. Moon should be in exact conjunction with Sun during eclipse
        $this->assertCircularDegreesMatch($sunLon, $moonLon, 0.05, 'Sun and Moon must be in conjunction during eclipse');
    }

    /**
     * Test 4: Core Panchang calculation performance and festival bypass.
     * Asserts that core Panchang with skip_festivals=true completes in < 350 ms.
     */
    public function test_core_panchang_execution_with_festival_bypass(): void
    {
        app()->instance(JmeEphFFI::class, $this->jme);
        config(['panchang.jme_settings.mode' => 'moshier']);

        $provider = new PanchangServiceProvider(app());
        $provider->register();

        /** @var PanchangService $panchangService */
        $panchangService = app()->make(PanchangService::class);
        $date = CarbonImmutable::parse('2026-11-01', 'Asia/Kolkata');

        $start = hrtime(true);
        $panchanga = $panchangService->getPanchanga(
            $date,
            28.6139,
            77.2090,
            'Asia/Kolkata',
            0.0,
            null,
            CalendarType::Amanta,
            ['skip_festivals' => true]
        );
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        $this->assertNotEmpty($panchanga['Tithi']['name']);
        $this->assertNotEmpty($panchanga['Nakshatra']['name']);
        $this->assertSame('Sunday', $panchanga['Vara']['name']);
        $this->assertEmpty($panchanga['Festivals'] ?? []); // Verified bypassed!
        $this->assertLessThan(350.0, $elapsedMs, sprintf('Panchang calculation took %.2f ms (exceeded 350ms limit)', $elapsedMs));
    }
}
