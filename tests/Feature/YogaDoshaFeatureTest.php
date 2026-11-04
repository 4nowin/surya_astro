<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\NativeEphemerisResolver;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use Composer\Autoload\ClassLoader;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JmeEph\FFI\JmeEphFFI;
use Tests\TestCase;

/**
 * Gate A Feature Test Suite: Classical Calculations, Yogas & Manglik Dosha (Work Package A2).
 *
 * Validates end-to-end integration with:
 * - Native Moshier Ephemeris (libjme.so / jme.dll).
 * - A1 Interval Stability Engine (AstrologyIntervalStabilityService).
 * - Kundli calculation engine (KundliService).
 * - Unified A2 Orchestrator (YogaDoshaOrchestrator).
 */
class YogaDoshaFeatureTest extends TestCase
{
    protected ?JmeEphFFI $jme = null;
    protected string $activeLibraryPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('FFI') || !class_exists('FFI')) {
            $this->markTestSkipped('PHP FFI extension is not loaded in this environment.');
        }

        $jmeSrc = base_path('packages/astrology/jpl-moshier-ephemeris-php/src/');
        $panchangSrc = base_path('packages/astrology/panchang-core/src/');

        if (!class_exists(JmeEphFFI::class)) {
            $loader = new ClassLoader();
            $loader->addPsr4('JmeEph\\', $jmeSrc);
            $loader->addPsr4('JayeshMepani\\PanchangCore\\', $panchangSrc);
            $loader->register();
        }

        $this->activeLibraryPath = NativeEphemerisResolver::resolve();
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
     * 1. Test end-to-end calculation and orchestration with a verified birth-time profile.
     */
    public function test_orchestrated_evaluation_for_verified_birth_time(): void
    {
        /** @var KundliService $kundliService */
        $kundliService = app(KundliService::class);
        /** @var AstrologyIntervalStabilityService $stabilityService */
        $stabilityService = app(AstrologyIntervalStabilityService::class);
        /** @var YogaDoshaOrchestrator $orchestrator */
        $orchestrator = app(YogaDoshaOrchestrator::class);

        $birthDate = '1990-05-15';
        $birthTime = '08:30:00';
        $lat = 28.6139;
        $lon = 77.2090;
        $tz = 'Asia/Kolkata';

        // 1. Generate Kundli
        $kundli = $kundliService->computeKundli(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: $lat,
            longitude: $lon,
            timezone: $tz
        );

        $this->assertNotEmpty($kundli['planets']);
        $this->assertSame('confirmed', $kundli['ascendant']['status']);

        // 2. Generate A1 Interval Stability
        $stability = $stabilityService->analyze(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: $lat,
            longitude: $lon,
            timezone: $tz
        );

        // 3. Orchestrate A2 calculations
        $result = $orchestrator->evaluateFromKundli($kundli, $stability, 34);

        // Assert top-level structure
        $this->assertArrayHasKey('aspects', $result);
        $this->assertArrayHasKey('yogas', $result);
        $this->assertArrayHasKey('manglik_dosha', $result);
        $this->assertArrayHasKey('meta', $result);

        // Metadata
        $this->assertSame(YogaDoshaOrchestrator::ENGINE_VERSION, $result['meta']['engine_version']);
        $this->assertTrue($result['meta']['is_lagna_invariant']);
        $this->assertSame(600, $result['meta']['uncertainty_window_seconds']); // 10 minutes = 600s

        // Aspects Matrix
        $aspects = $result['aspects'];
        $this->assertSame(
            ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn'],
            $aspects['included_planets']
        );
        $this->assertArrayHasKey('aspects_by_planet', $aspects);
        $this->assertArrayHasKey('aspected_houses', $aspects);

        // Yogas
        $yogas = $result['yogas'];
        $this->assertArrayHasKey('panch_mahapurusha', $yogas);
        $this->assertArrayHasKey('gaja_kesari', $yogas);
        $this->assertArrayHasKey('budhaditya', $yogas);

        $pm = $yogas['panch_mahapurusha'];
        $this->assertSame(5, $pm['total_evaluated']);
        $this->assertArrayHasKey('ruchaka', $pm['yogas']);
        $this->assertArrayHasKey('bhadra', $pm['yogas']);
        $this->assertArrayHasKey('hamsa', $pm['yogas']);
        $this->assertArrayHasKey('malavya', $pm['yogas']);
        $this->assertArrayHasKey('sasa', $pm['yogas']);

        // Gaja Kesari
        $gk = $yogas['gaja_kesari'];
        $this->assertIsBool($gk['is_formed']);
        $this->assertArrayHasKey('moon', $gk);
        $this->assertArrayHasKey('jupiter', $gk);
        $this->assertArrayHasKey('quality', $gk);

        // Budhaditya
        $ba = $yogas['budhaditya'];
        $this->assertIsBool($ba['is_formed']);
        $this->assertIsFloat($ba['angular_separation_degrees']);
        $this->assertIsBool($ba['is_combust']);

        // Manglik Dosha
        $manglik = $result['manglik_dosha'];
        $this->assertIsBool($manglik['is_manglik_formed']);
        $this->assertContains($manglik['final_status'], ['no_dosha', 'dosha_confirmed', 'dosha_cancelled']);
        $this->assertArrayHasKey('cancellation', $manglik);
        $this->assertArrayHasKey('traditional_mitigations', $manglik);
        $this->assertTrue($manglik['traditional_mitigations']['age_maturity']['applies'], 'Native age is 34 (> 28)');
    }

    /**
     * 2. Test unknown birth time suppresses house-dependent yogas and Lagna Manglik while retaining non-house components.
     */
    public function test_orchestration_for_unknown_birth_time_safely_suppresses_lagna_dependent_calculations(): void
    {
        /** @var KundliService $kundliService */
        $kundliService = app(KundliService::class);
        /** @var AstrologyIntervalStabilityService $stabilityService */
        $stabilityService = app(AstrologyIntervalStabilityService::class);
        /** @var YogaDoshaOrchestrator $orchestrator */
        $orchestrator = app(YogaDoshaOrchestrator::class);

        $birthDate = '1995-10-20';
        $lat = 19.0760;
        $lon = 72.8777;
        $tz = 'Asia/Kolkata';

        // 1. Generate Kundli with unknown birth time
        $kundli = $kundliService->computeKundli(
            birthDate: $birthDate,
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_UNKNOWN,
            approximateWindow: null,
            latitude: $lat,
            longitude: $lon,
            timezone: $tz
        );

        $this->assertContains($kundli['ascendant']['status'], ['unavailable', 'suppressed']);

        // 2. Generate A1 Interval Stability
        $stability = $stabilityService->analyze(
            birthDate: $birthDate,
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_UNKNOWN,
            approximateWindow: null,
            latitude: $lat,
            longitude: $lon,
            timezone: $tz
        );

        // 3. Orchestrate A2 calculations
        $result = $orchestrator->evaluateFromKundli($kundli, $stability, 31);

        // Meta
        $this->assertFalse($result['meta']['is_lagna_invariant']);
        $this->assertSame(86399, $result['meta']['uncertainty_window_seconds']); // 24h = 86399s

        // Panch Mahapurusha must be suppressed
        $this->assertSame('suppressed', $result['yogas']['panch_mahapurusha']['evaluation_status']);
        $this->assertFalse($result['yogas']['panch_mahapurusha']['has_any_mahapurusha']);

        // Manglik Dosha from Lagna must be suppressed
        $this->assertSame('suppressed', $result['manglik_dosha']['final_status']);
        $this->assertSame('suppressed_unknown_lagna', $result['manglik_dosha']['stability']['status']);

        // Moon-dependent and planetary co-presence must still compute!
        $this->assertIsBool($result['yogas']['gaja_kesari']['is_formed']);
        $this->assertIsBool($result['yogas']['budhaditya']['is_formed']);
        $this->assertIsArray($result['aspects']['aspects_by_planet']);
    }

    /**
     * 3. Test orchestration for approximate birth time with candidate signs handling.
     */
    public function test_orchestration_for_approximate_birth_time_evaluates_candidate_variations(): void
    {
        /** @var KundliService $kundliService */
        $kundliService = app(KundliService::class);
        /** @var AstrologyIntervalStabilityService $stabilityService */
        $stabilityService = app(AstrologyIntervalStabilityService::class);
        /** @var YogaDoshaOrchestrator $orchestrator */
        $orchestrator = app(YogaDoshaOrchestrator::class);

        $birthDate = '1988-08-25';
        $lat = 28.6139;
        $lon = 77.2090;
        $tz = 'Asia/Kolkata';

        // Approximate morning window (06:00 to 12:00)
        $kundli = $kundliService->computeKundli(
            birthDate: $birthDate,
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_APPROXIMATE,
            approximateWindow: 'morning',
            latitude: $lat,
            longitude: $lon,
            timezone: $tz
        );

        $stability = $stabilityService->analyze(
            birthDate: $birthDate,
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_APPROXIMATE,
            approximateWindow: 'morning',
            latitude: $lat,
            longitude: $lon,
            timezone: $tz
        );

        $result = $orchestrator->evaluateFromKundli($kundli, $stability, 38);

        // Meta
        $this->assertSame(14400, $result['meta']['uncertainty_window_seconds']); // 4h = 14400s
        $this->assertFalse($result['meta']['is_lagna_invariant']);

        // Yogas and Manglik should report candidate variations or qualified results
        $this->assertArrayHasKey('manglik_dosha', $result);
        $this->assertArrayHasKey('stability', $result['manglik_dosha']);
        $this->assertContains(
            $result['manglik_dosha']['stability']['status'],
            ['confirmed', 'uncertain_lagna_variation']
        );

        // Panch Mahapurusha should evaluate candidate signs
        $pm = $result['yogas']['panch_mahapurusha'];
        $this->assertArrayHasKey('yogas', $pm);
        foreach ($pm['yogas'] as $yoga) {
            $this->assertArrayHasKey('stability', $yoga);
            $this->assertContains(
                $yoga['stability']['status'],
                ['confirmed', 'confirmed_not_formed', 'uncertain_lagna_variation', 'suppressed']
            );
        }
    }
}
