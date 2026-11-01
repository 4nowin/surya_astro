<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Astrology\CanonicalPanchangCacheKey;
use App\Services\Astrology\NativeEphemerisResolver;
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
 * Positional Astronomy Centre / Indian Ephemeris, and Swiss Ephemeris / Moshier standards).
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
        $this->activeLibraryPath = NativeEphemerisResolver::resolve();
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
     * Test 2: True Chitra Paksha Ayanamsha accuracy across 3 distinct epochs.
     *
     * In JME / Swiss Ephemeris, True Citra (mode 383, JME_SIDEREAL_TRUE_CITRA) dynamically locks
     * the fixed star Spica (Chitra / alpha Virginis) to exactly 180° 00' 00" of the sidereal zodiac
     * at the date of calculation, accounting for Spica's proper motion and nutation.
     *
     * Multi-Epoch Reference Values:
     * - 1950-01-01 00:00:00 UT (JD 2433282.5): 23° 08' 36.75" ≈ 23.143543°
     * - 2000-01-01 12:00:00 UT (JD 2451545.0): 23° 50' 28.89" ≈ 23.841358°
     * - 2026-11-01 12:00:00 UT (JD 2461346.0): 24° 12' 57.31" ≈ 24.215920°
     */
    public function test_true_citra_ayanamsha_accuracy_across_epochs(): void
    {
        JmeRuntime::setTrueCitra($this->jme);

        $fixtures = [
            'Epoch 1950' => ['jd' => 2433282.5, 'expected' => 23.143543],
            'Epoch J2000' => ['jd' => 2451545.0, 'expected' => 23.841358],
            'Epoch 2026' => ['jd' => 2461346.0, 'expected' => 24.215920],
        ];

        foreach ($fixtures as $name => $fixture) {
            $ayanamsha = $this->jme->jme_get_ayanamsa_ut($fixture['jd']);
            $this->assertEqualsWithDelta(
                $fixture['expected'],
                $ayanamsha,
                0.002, // Within 7.2 arcseconds
                sprintf('%s: True Citra Ayanamsha calculated %.6f° vs expected %.6f°', $name, $ayanamsha, $fixture['expected'])
            );
        }
    }

    /**
     * Test 3: Traditional Lahiri and ICRC Ayanamsha at Epoch J2000.0.
     *
     * Unlike True Citra (which pins Spica dynamically to 180°), Traditional Lahiri
     * (mode 1, JME_SIDEREAL_LAHIRI) uses an epoch-1900 reference with Newcomb precession:
     * - J2000.0 Conventional Lahiri: 23° 51' 25.53" ≈ 23.857092°
     * - J2000.0 Lahiri ICRC (mode 406): 23° 51' 24.87" ≈ 23.856910°
     *
     * This test documents and asserts the distinction between Traditional Lahiri
     * and True Citra (~42 to 56 arcseconds divergence due to Spica's proper motion).
     */
    public function test_traditional_lahiri_and_icrc_ayanamsha_accuracy(): void
    {
        $err = $this->jme->getFFI()->new('char[256]');
        $jd = 2451545.0; // J2000.0 UT

        // 1. Conventional Lahiri (mode 1)
        $rc1 = $this->jme->jme_set_sidereal_mode_checked(JmeEphFFI::JME_SIDEREAL_LAHIRI, 0.0, 0.0, $err);
        $this->assertSame(JmeEphFFI::JME_OK, $rc1, 'Setting JME_SIDEREAL_LAHIRI failed: ' . FFI::string($err));
        $ayanLahiri = $this->jme->jme_get_ayanamsa_ut($jd);
        $this->assertEqualsWithDelta(
            23.857092,
            $ayanLahiri,
            0.005,
            sprintf('Conventional Lahiri at J2000.0 out of tolerance: calculated %.6f°', $ayanLahiri)
        );

        // 2. Lahiri ICRC (mode 406 - Indian Calendar Reform Committee)
        $rc406 = $this->jme->jme_set_sidereal_mode_checked(JmeEphFFI::JME_SIDEREAL_LAHIRI_ICRC, 0.0, 0.0, $err);
        $this->assertSame(JmeEphFFI::JME_OK, $rc406, 'Setting JME_SIDEREAL_LAHIRI_ICRC failed: ' . FFI::string($err));
        $ayanIcrc = $this->jme->jme_get_ayanamsa_ut($jd);
        $this->assertEqualsWithDelta(
            23.856910,
            $ayanIcrc,
            0.005,
            sprintf('Lahiri ICRC at J2000.0 out of tolerance: calculated %.6f°', $ayanIcrc)
        );

        // Restore True Citra for panchang-core
        JmeRuntime::setTrueCitra($this->jme);
    }

    /**
     * Test 4: Planetary positions during Total Solar Eclipse (2024-04-08 18:18:00 UTC).
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
     * Test 5: Navagraha planetary positions at Epoch J2000.0 (JD 2451545.0 UT).
     * Validates Sun, Moon, Mercury, Venus, Mars, Jupiter, Saturn, Rahu (Mean Node),
     * and Ketu (180° opposite Rahu) against standard astronomical ephemeris ground truth.
     */
    public function test_navagraha_planetary_positions_at_j2000(): void
    {
        $jd = 2451545.0; // 2000-01-01 12:00:00 UT
        $ffi = $this->jme->getFFI();
        $res = $ffi->new('double[6]');
        $err = $ffi->new('char[256]');

        // Expected Tropical Longitudes at J2000.0 (NASA JPL / Astronomical Almanac):
        $expectedTropical = [
            JmeEphFFI::JME_BODY_SUN => ['name' => 'Surya (Sun)', 'lon' => 280.3705, 'tol' => 0.02],
            JmeEphFFI::JME_BODY_MOON => ['name' => 'Chandra (Moon)', 'lon' => 223.3238, 'tol' => 0.05],
            JmeEphFFI::JME_BODY_MERCURY => ['name' => 'Budha (Mercury)', 'lon' => 271.8903, 'tol' => 0.05],
            JmeEphFFI::JME_BODY_VENUS => ['name' => 'Shukra (Venus)', 'lon' => 241.5663, 'tol' => 0.05],
            JmeEphFFI::JME_BODY_MARS => ['name' => 'Mangala (Mars)', 'lon' => 327.9643, 'tol' => 0.05],
            JmeEphFFI::JME_BODY_JUPITER => ['name' => 'Guru (Jupiter)', 'lon' => 25.2532, 'tol' => 0.05],
            JmeEphFFI::JME_BODY_SATURN => ['name' => 'Shani (Saturn)', 'lon' => 40.3956, 'tol' => 0.05],
            JmeEphFFI::JME_BODY_MEAN_NODE => ['name' => 'Rahu (Mean Node)', 'lon' => 125.0445, 'tol' => 0.05],
        ];

        foreach ($expectedTropical as $bodyId => $expected) {
            $rc = $this->jme->jme_calc_ut($jd, $bodyId, JmeEphFFI::JME_CALC_SPEED, $res, $err);
            $this->assertSame(JmeEphFFI::JME_OK, $rc, "Failed calculating {$expected['name']}: " . FFI::string($err));

            $actualLon = $res[0];
            $this->assertCircularDegreesMatch(
                $expected['lon'],
                $actualLon,
                $expected['tol'],
                "{$expected['name']} tropical longitude mismatch at J2000.0"
            );
        }

        // Ketu is mathematically exactly 180° opposite Rahu
        $rcRahu = $this->jme->jme_calc_ut($jd, JmeEphFFI::JME_BODY_MEAN_NODE, JmeEphFFI::JME_CALC_SPEED, $res, $err);
        $this->assertSame(JmeEphFFI::JME_OK, $rcRahu);
        $rahuLon = $res[0];
        $ketuLon = fmod($rahuLon + 180.0, 360.0);
        $this->assertCircularDegreesMatch(305.0445, $ketuLon, 0.05, 'Ketu tropical longitude mismatch at J2000.0');
    }

    /**
     * Test 6: Ascendant (Lagna) and Houses calculation at Delhi at J2000.0 UT.
     * Coordinates: Delhi (28.6139° N, 77.2090° E), Epoch: 2000-01-01 12:00:00 UT.
     * Tropical Ascendant: ~100.191° (Cancer 10° 11').
     * Sidereal True Citra Ascendant: ~76.350° (Gemini 16° 21').
     */
    public function test_ascendant_houses_calculation_at_delhi(): void
    {
        $jd = 2451545.0; // J2000.0 UT
        $lat = 28.6139;
        $lon = 77.2090;

        $cusps = $this->jme->getFFI()->new('double[13]');
        $ascmc = $this->jme->getFFI()->new('double[10]');

        $rc = $this->jme->jme_houses($jd, $lat, $lon, ord('P'), $cusps, $ascmc);
        $this->assertSame(JmeEphFFI::JME_OK, $rc, 'Houses calculation failed');

        $ascTropical = $ascmc[0];
        $this->assertCircularDegreesMatch(100.1913, $ascTropical, 0.05, 'Delhi Tropical Ascendant mismatch at J2000.0');

        JmeRuntime::setTrueCitra($this->jme);
        $ayan = $this->jme->jme_get_ayanamsa_ut($jd);
        $ascSidereal = fmod($ascTropical - $ayan + 360.0, 360.0);
        $this->assertCircularDegreesMatch(76.3500, $ascSidereal, 0.05, 'Delhi Sidereal Ascendant mismatch at J2000.0');
    }

    /**
     * Test 7: Core Panchang calculation performance, sunrise/sunset and festival bypass.
     * Asserts that core Panchang with skip_festivals=true completes in < 750 ms on shared hosting.
     * (Initial unoptimized run was ~44,000 ms; optimized execution on Hostinger is ~550 ms).
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
        $this->assertNotEmpty($panchanga['Sunrise']);
        $this->assertNotEmpty($panchanga['Sunset']);
        $this->assertEmpty($panchanga['Festivals'] ?? []); // Verified bypassed!

        // Target: < 750 ms on shared hosting multi-tenant CPU quotas (Hostinger observed 554 ms).
        $this->assertLessThan(750.0, $elapsedMs, sprintf('Panchang calculation took %.2f ms (exceeded 750ms SLA)', $elapsedMs));
    }

    /**
     * Test 8: Festival bypass enabled contract verification.
     * Verifies that when skip_festivals=true:
     * - Calculation completes with zero undefined variable errors.
     * - Yoga_Windows in Resolution_Context is empty (bypassing heavy bisections).
     * - Festivals and Daily_Observances are empty arrays.
     * - Core Panchanga attributes (Tithi, Nakshatra, Vara, Sunrise, Sunset) remain fully populated.
     */
    public function test_panchang_festival_bypass_enabled(): void
    {
        app()->instance(JmeEphFFI::class, $this->jme);
        config(['panchang.jme_settings.mode' => 'moshier']);

        $provider = new PanchangServiceProvider(app());
        $provider->register();

        /** @var PanchangService $panchangService */
        $panchangService = app()->make(PanchangService::class);
        $date = CarbonImmutable::parse('2026-11-01', 'Asia/Kolkata');

        $selected = $panchangService->getSelectedDetails(
            $date,
            28.6139,
            77.2090,
            'Asia/Kolkata',
            ['Basic_Details'],
            0.0,
            null,
            CalendarType::Amanta,
            ['skip_festivals' => true]
        );

        $this->assertArrayHasKey('Basic_Details', $selected);
        $basic = $selected['Basic_Details'];

        // 1. Core Panchang fields remain available
        $this->assertNotEmpty($basic['Panchanga']['Tithi']['name']);
        $this->assertNotEmpty($basic['Panchanga']['Nakshatra']['name']);
        $this->assertSame('Sunday', $basic['Panchanga']['Vara']['name']);
        $this->assertNotEmpty($basic['Panchanga']['Sunrise']);
        $this->assertNotEmpty($basic['Panchanga']['Sunset']);

        // 2. Festival-only Yoga intervals are bypassed
        $this->assertEmpty($basic['Resolution_Context']['Yoga_Windows']);

        // 3. Festival resolution and daily observances are bypassed
        $this->assertEmpty($basic['Festivals']);
        $this->assertEmpty($basic['Daily_Observances']);
    }

    /**
     * Test 9: Festival bypass disabled contract verification.
     * Verifies that when skip_festivals=false:
     * - Normal festival-related path remains enabled.
     * - Yoga_Windows is calculated and not empty.
     */
    public function test_panchang_festival_bypass_disabled(): void
    {
        app()->instance(JmeEphFFI::class, $this->jme);
        config(['panchang.jme_settings.mode' => 'moshier']);

        $provider = new PanchangServiceProvider(app());
        $provider->register();

        /** @var PanchangService $panchangService */
        $panchangService = app()->make(PanchangService::class);
        $date = CarbonImmutable::parse('2026-11-01', 'Asia/Kolkata');

        $selected = $panchangService->getSelectedDetails(
            $date,
            28.6139,
            77.2090,
            'Asia/Kolkata',
            ['Basic_Details'],
            0.0,
            null,
            CalendarType::Amanta,
            ['skip_festivals' => false]
        );

        $basic = $selected['Basic_Details'];

        // 1. Yoga interval calculation is NOT skipped
        $this->assertNotEmpty($basic['Resolution_Context']['Yoga_Windows']);

        // 2. Core Panchanga fields remain intact
        $this->assertNotEmpty($basic['Panchanga']['Tithi']['name']);
    }

    /**
     * Test 10: Default behavior when skip_festivals is omitted.
     * Verifies that default behavior preserves normal festival path.
     */
    public function test_panchang_festival_bypass_default_behavior(): void
    {
        app()->instance(JmeEphFFI::class, $this->jme);
        config(['panchang.jme_settings.mode' => 'moshier']);

        $provider = new PanchangServiceProvider(app());
        $provider->register();

        /** @var PanchangService $panchangService */
        $panchangService = app()->make(PanchangService::class);
        $date = CarbonImmutable::parse('2026-11-01', 'Asia/Kolkata');

        $selected = $panchangService->getSelectedDetails(
            $date,
            28.6139,
            77.2090,
            'Asia/Kolkata',
            ['Basic_Details'],
            0.0,
            null,
            CalendarType::Amanta,
            []
        );

        $basic = $selected['Basic_Details'];

        // Default behavior preserves normal Yoga interval calculations
        $this->assertNotEmpty($basic['Resolution_Context']['Yoga_Windows']);
        $this->assertNotEmpty($basic['Panchanga']['Tithi']['name']);
    }
}
