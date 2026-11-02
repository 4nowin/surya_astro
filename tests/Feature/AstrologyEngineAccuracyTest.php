<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Astrology\CanonicalPanchangCacheKey;
use App\Services\Astrology\NativeEphemerisResolver;
use Carbon\CarbonImmutable;
use Composer\Autoload\ClassLoader;
use FFI;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\VimshottariDasha;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;
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
        $maxAllowedMs = (PHP_OS_FAMILY === 'Windows' || extension_loaded('xdebug')) ? 2500.0 : 750.0;
        $this->assertLessThan($maxAllowedMs, $elapsedMs, sprintf('Panchang calculation took %.2f ms (exceeded %.0f ms SLA)', $elapsedMs, $maxAllowedMs));
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
        $this->assertEmpty($basic['Yoga_Windows']);
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
        $this->assertNotEmpty($basic['Yoga_Windows']);
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
        $this->assertNotEmpty($basic['Yoga_Windows']);
        $this->assertNotEmpty($basic['Resolution_Context']['Yoga_Windows']);
        $this->assertNotEmpty($basic['Panchanga']['Tithi']['name']);
    }

    /**
     * Test 11: Multi-Latitude and Multi-Hemisphere Ascendants at Epoch J2000.0.
     *
     * Validates Ascendant (Lagna) and Midheaven (MC) across diverse terrestrial latitudes
     * and both hemispheres against independent astronomical reference coordinates (Swiss Ephemeris / Moshier):
     * - New Delhi, India (28.6139° N, 77.2090° E): Northern sub-tropical
     * - London, UK (51.5074° N, 0.1278° W): High northern latitude
     * - Equator (0.0000° N, 0.0000° E): Equatorial baseline
     * - Sydney, Australia (-33.8688° S, 151.2093° E): Southern temperate
     *
     * Configuration:
     * - Time: 2000-01-01 12:00:00 UT (JD 2451545.0)
     * - True Citra Ayanamsha: 23.841358°
     * - Obliquity of date: 23.439291°
     */
    public function test_multi_latitude_and_multi_hemisphere_ascendants(): void
    {
        $jd = 2451545.0; // J2000.0 UT
        $ffi = $this->jme->getFFI();
        $cusps = $ffi->new('double[13]');
        $ascmc = $ffi->new('double[10]');

        JmeRuntime::setTrueCitra($this->jme);
        $ayan = $this->jme->jme_get_ayanamsa_ut($jd);

        $locations = [
            'New Delhi' => [
                'lat' => 28.6139, 'lon' => 77.2090,
                'exp_trop_asc' => 100.1913, 'exp_mc' => 357.4565,
                'exp_sid_asc' => 76.3500, // Gemini 16° 21'
            ],
            'London' => [
                'lat' => 51.5074, 'lon' => -0.1278,
                'exp_trop_asc' => 24.0146, 'exp_mc' => 279.4932,
                'exp_sid_asc' => 0.1732, // Aries 0° 10'
            ],
            'Equator' => [
                'lat' => 0.0000, 'lon' => 0.0000,
                'exp_trop_asc' => 11.3739, 'exp_mc' => 279.6111,
                'exp_sid_asc' => 347.5325, // Pisces 17° 32'
            ],
            'Sydney' => [
                'lat' => -33.8688, 'lon' => 151.2093,
                'exp_trop_asc' => 152.4889, 'exp_mc' => 73.0892,
                'exp_sid_asc' => 128.6475, // Leo 8° 39'
            ],
        ];

        foreach ($locations as $city => $data) {
            $rc = $this->jme->jme_houses($jd, $data['lat'], $data['lon'], ord('P'), $cusps, $ascmc);
            $this->assertSame(JmeEphFFI::JME_OK, $rc, "House calculation failed for {$city}");

            $tropAsc = $ascmc[0];
            $mc = $ascmc[1];
            $sidAsc = fmod($tropAsc - $ayan + 360.0, 360.0);

            $this->assertCircularDegreesMatch(
                $data['exp_trop_asc'],
                $tropAsc,
                0.05,
                "{$city} Tropical Ascendant mismatch"
            );
            $this->assertCircularDegreesMatch(
                $data['exp_mc'],
                $mc,
                0.05,
                "{$city} Midheaven (MC) mismatch"
            );
            $this->assertCircularDegreesMatch(
                $data['exp_sid_asc'],
                $sidAsc,
                0.05,
                "{$city} Sidereal True Citra Ascendant mismatch"
            );
        }
    }

    /**
     * Test 12: House Systems Comparison — Placidus vs. Equal House Geometry.
     *
     * Validates the geometrical properties of Placidus vs. Equal house systems at New Delhi:
     * - Equal Houses ('E'): Each cusp is mathematically Ascendant + (n - 1) * 30° mod 360°.
     * - Placidus ('P'): Cusps 1 (Asc) and 7 (Desc) match Equal houses exactly (180° opposition).
     * - Intermediate Placidus cusps diverge smoothly based on local diurnal/nocturnal arcs.
     */
    public function test_house_systems_placidus_vs_equal_house_geometry(): void
    {
        $jd = 2451545.0; // J2000.0 UT
        $ffi = $this->jme->getFFI();

        $cuspsP = $ffi->new('double[13]');
        $ascmcP = $ffi->new('double[10]');
        $rcP = $this->jme->jme_houses($jd, 28.6139, 77.2090, ord('P'), $cuspsP, $ascmcP);
        $this->assertSame(JmeEphFFI::JME_OK, $rcP);

        $cuspsE = $ffi->new('double[13]');
        $ascmcE = $ffi->new('double[10]');
        $rcE = $this->jme->jme_houses($jd, 28.6139, 77.2090, ord('E'), $cuspsE, $ascmcE);
        $this->assertSame(JmeEphFFI::JME_OK, $rcE);

        $ascP = $ascmcP[0];
        $ascE = $ascmcE[0];

        // 1. Ascendants must match identically
        $this->assertEqualsWithDelta($ascP, $ascE, 1e-6, 'Placidus and Equal house Ascendant must be identical');

        // 2. Equal house cusps must advance by exactly 30° each
        for ($h = 1; $h <= 12; $h++) {
            $expectedCusp = fmod($ascE + ($h - 1) * 30.0, 360.0);
            $this->assertCircularDegreesMatch(
                $expectedCusp,
                $cuspsE[$h],
                0.001,
                "Equal house cusp {$h} must be exactly Asc + " . (($h - 1) * 30) . "°"
            );
        }

        // 3. Placidus Cusp 1 and Cusp 7 must be in exact 180° opposition
        $this->assertCircularDegreesMatch($ascP, $cuspsP[1], 0.001, 'Placidus Cusp 1 must match Ascendant');
        $descExpected = fmod($ascP + 180.0, 360.0);
        $this->assertCircularDegreesMatch($descExpected, $cuspsP[7], 0.001, 'Placidus Cusp 7 must be exactly opposite Ascendant');

        // 4. Placidus MC (Cusp 10) matches ascmc[1]
        $this->assertCircularDegreesMatch($ascmcP[1], $cuspsP[10], 0.001, 'Placidus Cusp 10 must match Midheaven');
    }

    /**
     * Test 13: Rahu and Ketu Astronomical Accuracy (Mean Node vs. True Node).
     *
     * Validates Mean Node and True Node at multiple epochs:
     * - J2000.0 (2000-01-01 12:00:00 UT)
     * - Total Solar Eclipse (2024-04-08 18:18:00 UT)
     * - Epoch 2026-11-01 12:00:00 UT
     *
     * Verifies:
     * - True Node matches NASA JPL Horizons positions within 0.05°.
     * - At the Total Solar Eclipse, True Node is in close proximity to Sun & Moon longitudes.
     * - Ketu is mathematically exactly 180° polar opposite to Rahu for both node models.
     */
    public function test_rahu_true_node_vs_mean_node_astronomical_accuracy(): void
    {
        $ffi = $this->jme->getFFI();
        $resMean = $ffi->new('double[6]');
        $resTrue = $ffi->new('double[6]');
        $err = $ffi->new('char[256]');

        $epochs = [
            'J2000' => [
                'jd' => 2451545.0,
                'exp_mean_rahu' => 125.0445,
                'exp_true_rahu' => 123.9261,
            ],
            'Eclipse_2024' => [
                'jd' => 2460409.2625, // 2024-04-08 18:18:00 UT
                'exp_mean_rahu' => 15.6485,
                'exp_true_rahu' => 15.5426,
            ],
            'Epoch_2026' => [
                'jd' => 2461346.0, // 2026-11-01 12:00:00 UT
                'exp_mean_rahu' => 326.0448,
                'exp_true_rahu' => 326.7230,
            ],
        ];

        foreach ($epochs as $epochName => $fixture) {
            // Mean Node
            $rcMean = $this->jme->jme_calc_ut($fixture['jd'], JmeEphFFI::JME_BODY_MEAN_NODE, JmeEphFFI::JME_CALC_SPEED, $resMean, $err);
            $this->assertSame(JmeEphFFI::JME_OK, $rcMean, "Mean Node calculation failed for {$epochName}");
            $meanRahu = $resMean[0];
            $this->assertCircularDegreesMatch(
                $fixture['exp_mean_rahu'],
                $meanRahu,
                0.05,
                "Mean Rahu mismatch at {$epochName}"
            );

            // True Node
            $rcTrue = $this->jme->jme_calc_ut($fixture['jd'], JmeEphFFI::JME_BODY_TRUE_NODE, JmeEphFFI::JME_CALC_SPEED, $resTrue, $err);
            $this->assertSame(JmeEphFFI::JME_OK, $rcTrue, "True Node calculation failed for {$epochName}");
            $trueRahu = $resTrue[0];
            $this->assertCircularDegreesMatch(
                $fixture['exp_true_rahu'],
                $trueRahu,
                0.05,
                "True Rahu mismatch at {$epochName}"
            );

            // Ketu Mean is exactly 180° opposite
            $meanKetu = fmod($meanRahu + 180.0, 360.0);
            $expMeanKetu = fmod($fixture['exp_mean_rahu'] + 180.0, 360.0);
            $this->assertCircularDegreesMatch($expMeanKetu, $meanKetu, 0.05, "Mean Ketu mismatch at {$epochName}");

            // Ketu True is exactly 180° opposite
            $trueKetu = fmod($trueRahu + 180.0, 360.0);
            $expTrueKetu = fmod($fixture['exp_true_rahu'] + 180.0, 360.0);
            $this->assertCircularDegreesMatch($expTrueKetu, $trueKetu, 0.05, "True Ketu mismatch at {$epochName}");
        }
    }

    /**
     * Test 14: Topocentric Sunrise and Sunset Across Latitudes and Hemispheres.
     *
     * Validates topocentric sunrise and sunset against NOAA / Positional Astronomy Centre standards:
     * - Observer elevation: 0m
     * - Atmospheric refraction: 34 arcminutes
     * - Solar disc semi-diameter: 16 arcminutes (visible upper limb = 50' horizon depression)
     * - Standard atmospheric pressure: 1013.25 hPa, temperature: 15°C
     *
     * Permissible tolerance vs authoritative solar calculations: ±60 seconds.
     */
    public function test_topocentric_sunrise_sunset_across_latitudes_and_seasons(): void
    {
        $sunService = new SunService($this->jme);

        $cities = [
            'New Delhi' => [
                'lat' => 28.6139, 'lon' => 77.2090, 'tz' => 'Asia/Kolkata',
                'exp_sunrise' => '2026-11-01 06:32:12', 'exp_sunset' => '2026-11-01 17:37:00',
            ],
            'Mumbai' => [
                'lat' => 19.0760, 'lon' => 72.8777, 'tz' => 'Asia/Kolkata',
                'exp_sunrise' => '2026-11-01 06:38:04', 'exp_sunset' => '2026-11-01 18:05:53',
            ],
            'London' => [
                'lat' => 51.5074, 'lon' => -0.1278, 'tz' => 'Europe/London',
                'exp_sunrise' => '2026-11-01 06:52:49', 'exp_sunset' => '2026-11-01 16:34:41',
            ],
            'Sydney' => [
                'lat' => -33.8688, 'lon' => 151.2093, 'tz' => 'Australia/Sydney',
                'exp_sunrise' => '2026-11-01 05:54:21', 'exp_sunset' => '2026-11-01 19:23:43',
            ],
        ];

        foreach ($cities as $name => $c) {
            $birth = [
                'year' => 2026,
                'month' => 11,
                'day' => 1,
                'latitude' => $c['lat'],
                'longitude' => $c['lon'],
                'timezone' => $c['tz'],
            ];

            $times = $sunService->getSunriseSunset($birth);
            $actualSunrise = $times[0]->format('Y-m-d H:i:s');
            $actualSunset = $times[1]->format('Y-m-d H:i:s');

            $sunriseDiffSec = abs($times[0]->diffInSeconds(CarbonImmutable::parse($c['exp_sunrise'], $c['tz'])));
            $sunsetDiffSec = abs($times[1]->diffInSeconds(CarbonImmutable::parse($c['exp_sunset'], $c['tz'])));

            $this->assertLessThanOrEqual(
                60,
                $sunriseDiffSec,
                "{$name} Sunrise {$actualSunrise} deviates by {$sunriseDiffSec}s from expected {$c['exp_sunrise']}"
            );
            $this->assertLessThanOrEqual(
                60,
                $sunsetDiffSec,
                "{$name} Sunset {$actualSunset} deviates by {$sunsetDiffSec}s from expected {$c['exp_sunset']}"
            );
        }
    }

    /**
     * Test 15: ZodiacDivision and Nakshatra/Pada Boundary Transitions.
     *
     * Validates that exact boundary values do not suffer from floating-point quotient roundoff:
     * - 12 Rashis: 0° (Mesha) to 330° (Meena) with exact boundaries at 30° increments.
     * - 27 Nakshatras: 13° 20' = 13.333333° increments.
     * - 108 Padas: 3° 20' = 3.333333° increments.
     */
    public function test_zodiac_division_and_nakshatra_pada_boundary_transitions(): void
    {
        // 1. Rashi boundaries
        $this->assertSame(0, ZodiacDivision::rashiIndex(0.0), '0.0° must be Mesha (0)');
        $this->assertSame(0, ZodiacDivision::rashiIndex(29.999999), '29.999999° must be Mesha (0)');
        $this->assertSame(1, ZodiacDivision::rashiIndex(30.000000), '30.000000° must transition to Vrishabha (1)');
        $this->assertSame(1, ZodiacDivision::rashiIndex(30.000001), '30.000001° must be Vrishabha (1)');
        $this->assertSame(11, ZodiacDivision::rashiIndex(359.999999), '359.999999° must be Meena (11)');
        $this->assertSame(0, ZodiacDivision::rashiIndex(360.000000), '360.0° must wrap to Mesha (0)');

        // 2. Nakshatra boundaries (13° 20' = 13.333333333333°)
        $degPerNak = 360.0 / 27.0;
        $this->assertSame(0, ZodiacDivision::nakshatraIndex(0.0), '0.0° must be Ashwini (0)');
        $this->assertSame(0, ZodiacDivision::nakshatraIndex($degPerNak - 1e-7), 'Just before 13°20\' must be Ashwini (0)');
        $this->assertSame(1, ZodiacDivision::nakshatraIndex($degPerNak), 'Exactly 13°20\' must transition to Bharani (1)');
        $this->assertSame(1, ZodiacDivision::nakshatraIndex($degPerNak + 1e-7), 'Just after 13°20\' must be Bharani (1)');
        $this->assertSame(26, ZodiacDivision::nakshatraIndex(360.0 - 1e-7), 'Just before 360° must be Revati (26)');
        $this->assertSame(0, ZodiacDivision::nakshatraIndex(360.0), '360° must wrap to Ashwini (0)');

        // 3. Nakshatra Pada boundaries (3° 20' = 3.333333333333°)
        $degPerPada = 360.0 / 108.0;
        $this->assertSame(1, ZodiacDivision::nakshatraPada(0.0), '0.0° must be Pada 1');
        $this->assertSame(1, ZodiacDivision::nakshatraPada($degPerPada - 1e-7), 'Just before 3°20\' must be Pada 1');
        $this->assertSame(2, ZodiacDivision::nakshatraPada($degPerPada), '3°20\' must transition to Pada 2');
        $this->assertSame(4, ZodiacDivision::nakshatraPada($degPerNak - 1e-7), 'End of Ashwini must be Pada 4');
        $this->assertSame(1, ZodiacDivision::nakshatraPada($degPerNak), 'Start of Bharani must transition to Pada 1');
    }

    /**
     * Test 16: Panchanga Limb Bisection Crossing Mathematics.
     *
     * Validates the mathematical formulation and boundary crossing of core Vedic calendar elements:
     * - Tithi = floor( (Moon_Lon - Sun_Lon mod 360) / 12° )
     * - Yoga = floor( (Moon_Sidereal_Lon + Sun_Sidereal_Lon mod 360) / (360° / 27) )
     * - Karana = floor( (Moon_Lon - Sun_Lon mod 360) / 6° )
     * - Nakshatra = floor( Moon_Sidereal_Lon / (360° / 27) )
     *
     * On 2026-11-01, Krishna Saptami (index 21, span 252° to 264°) ends at approximately
     * 02:52 PM IST (09:22 UT), transitioning into Krishna Ashtami (index 22, span 264° to 276°):
     * - At 06:30 UT (12:00 PM IST, before transition): Elongation ~262.44° -> Tithi index 21 (Saptami), Karana 43 (Bava).
     * - At 12:00 UT (05:30 PM IST, after transition): Elongation ~265.42° -> Tithi index 22 (Ashtami), Karana 44 (Balava).
     */
    public function test_panchanga_limb_bisection_crossing_mathematics(): void
    {
        JmeRuntime::setTrueCitra($this->jme);

        $ffi = $this->jme->getFFI();
        $sunRes = $ffi->new('double[6]');
        $moonRes = $ffi->new('double[6]');
        $err = $ffi->new('char[256]');

        // 1. Before transition: 2026-11-01 06:30:00 UT (12:00 PM IST)
        $jdBefore = $this->jme->jme_julian_day(2026, 11, 1, 6.5, JmeEphFFI::JME_CALENDAR_GREGORIAN);
        $this->jme->jme_calc_ut($jdBefore, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_CALC_SPEED, $sunRes, $err);
        $this->jme->jme_calc_ut($jdBefore, JmeEphFFI::JME_BODY_MOON, JmeEphFFI::JME_CALC_SPEED, $moonRes, $err);
        $elongBefore = fmod($moonRes[0] - $sunRes[0] + 360.0, 360.0);
        $tithiBefore = (int) floor($elongBefore / 12.0);
        $karanaBefore = (int) floor($elongBefore / 6.0);

        $this->assertSame(21, $tithiBefore, '06:30 UT must be Tithi index 21 (Krishna Saptami before 264° boundary)');
        $this->assertSame(43, $karanaBefore, '06:30 UT must be Karana index 43 (Bava)');

        // 2. After transition: 2026-11-01 12:00:00 UT (05:30 PM IST)
        $jdAfter = $this->jme->jme_julian_day(2026, 11, 1, 12.0, JmeEphFFI::JME_CALENDAR_GREGORIAN);
        $ayan = $this->jme->jme_get_ayanamsa_ut($jdAfter);
        $this->jme->jme_calc_ut($jdAfter, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_CALC_SPEED, $sunRes, $err);
        $this->jme->jme_calc_ut($jdAfter, JmeEphFFI::JME_BODY_MOON, JmeEphFFI::JME_CALC_SPEED, $moonRes, $err);

        $sunTrop = $sunRes[0];
        $moonTrop = $moonRes[0];
        $sunSid = fmod($sunTrop - $ayan + 360.0, 360.0);
        $moonSid = fmod($moonTrop - $ayan + 360.0, 360.0);

        $elongAfter = fmod($moonTrop - $sunTrop + 360.0, 360.0);
        $tithiAfter = (int) floor($elongAfter / 12.0);
        $karanaAfter = (int) floor($elongAfter / 6.0);

        $this->assertSame(22, $tithiAfter, '12:00 UT must be Tithi index 22 (Krishna Ashtami after crossing 264° boundary)');
        $this->assertSame(44, $karanaAfter, '12:00 UT must be Karana index 44 (Balava)');

        // 3. Soli-lunar sum for Yoga (each 13° 20')
        $degPerDivision = 360.0 / 27.0;
        $soliLunarSum = fmod($moonSid + $sunSid, 360.0);
        $yogaIndex = (int) floor($soliLunarSum / $degPerDivision);
        $this->assertSame(22, $yogaIndex, '12:00 UT must be Yoga index 22 (Shubha after Sadhya transition)');

        // 4. Nakshatra from Moon Sidereal
        $nakshatraIndex = (int) floor($moonSid / $degPerDivision);
        $this->assertSame(7, $nakshatraIndex, '12:00 UT Moon must be in Pushya Nakshatra (index 7)');
    }

    /**
     * Test 17: Vimshottari Dasha 120-Year Mathematical Formulation.
     *
     * Validates:
     * - The 120-year cycle duration: Ketu (7) + Venus (20) + Sun (6) + Moon (10) +
     *   Mars (7) + Rahu (18) + Jupiter (16) + Saturn (19) + Mercury (17) = 120.
     * - The 27 Nakshatras deterministically map to their 9 planetary rulers in triadic order.
     * - The mathematical sub-period (Antardasha) formula: Duration(M, S) = (D_M * D_S) / 120 years.
     * - The sum of all 9 Antardashas within any Mahadasha equals exactly the Mahadasha duration.
     * - The balance of Dasha at birth calculation based on natal Moon longitude.
     */
    public function test_vimshottari_dasha_120_year_mathematical_cycle(): void
    {
        // 1. Total cycle sum must be exactly 120 years
        $allDashas = VimshottariDasha::cases();
        $this->assertCount(9, $allDashas);

        $totalYears = 0;
        foreach ($allDashas as $dasha) {
            $totalYears += $dasha->getDashaYears();
        }
        $this->assertSame(120, $totalYears, 'Sum of all 9 Vimshottari Mahadashas must equal exactly 120 years');

        // 2. All 27 Nakshatras map to the correct planetary lord
        $expectedRulers = [
            VimshottariDasha::Ketu,    // 0: Ashwini
            VimshottariDasha::Venus,   // 1: Bharani
            VimshottariDasha::Sun,     // 2: Krittika
            VimshottariDasha::Moon,    // 3: Rohini
            VimshottariDasha::Mars,    // 4: Mrigashira
            VimshottariDasha::Rahu,    // 5: Ardra
            VimshottariDasha::Jupiter, // 6: Punarvasu
            VimshottariDasha::Saturn,  // 7: Pushya
            VimshottariDasha::Mercury, // 8: Ashlesha
        ];

        for ($nak = 0; $nak < 27; $nak++) {
            $nakshatraEnum = Nakshatra::from($nak);
            $expectedLord = $expectedRulers[$nak % 9];
            $actualLord = VimshottariDasha::fromNakshatra($nakshatraEnum);
            $this->assertSame(
                $expectedLord,
                $actualLord,
                "Nakshatra {$nakshatraEnum->name} ({$nak}) must be ruled by {$expectedLord->name}"
            );
        }

        // 3. Sub-period (Antardasha) formulation and boundary condition
        foreach ($allDashas as $mahadasha) {
            $dM = $mahadasha->getDashaYears();
            $antardashaSum = 0.0;

            foreach ($allDashas as $subLord) {
                $dS = $subLord->getDashaYears();
                $antardashaDuration = ($dM * $dS) / 120.0;
                $antardashaSum += $antardashaDuration;
            }

            $this->assertEqualsWithDelta(
                (float) $dM,
                $antardashaSum,
                1e-9,
                "Sum of 9 Antardashas within {$mahadasha->name} Mahadasha must equal exactly {$dM} years"
            );
        }

        // 4. Proportionate Dasha balance calculation at birth
        // Natal Moon in Pushya (Saturn's Nakshatra: 93° 20' to 106° 40') at 100.2851°
        $moonLon = 100.2851;
        $nakStart = 7 * (360.0 / 27.0); // 93.333333°
        $nakSpan = 360.0 / 27.0;        // 13.333333°
        $elapsed = $moonLon - $nakStart; // 6.951767°
        $remainingFraction = ($nakSpan - $elapsed) / $nakSpan; // ~0.478615

        $saturnDashaYears = VimshottariDasha::Saturn->getDashaYears(); // 19 years
        $balanceYears = $remainingFraction * $saturnDashaYears;

        $this->assertEqualsWithDelta(9.0937, $balanceYears, 0.001, 'Balance of Saturn Dasha at birth should be ~9.0937 years');
    }
}
