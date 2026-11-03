<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\NativeEphemerisResolver;
use App\Services\Astrology\Stability\AscendantStabilityService;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Stability\BirthPanchangaStabilityService;
use App\Services\Astrology\Stability\ContinuousArcUnwrapper;
use App\Services\Astrology\Stability\PlanetaryNavamshaStabilityService;
use App\Services\Astrology\Stability\VimshottariUncertaintyService;
use Carbon\CarbonImmutable;
use Composer\Autoload\ClassLoader;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JmeEph\FFI\JmeEphFFI;
use Tests\TestCase;

/**
 * Gate A Feature Test Suite: Mathematical Foundation & Interval Stability.
 *
 * Validates Work Package A1 mathematical algorithms:
 * 1. Continuous arc unwrapping across 0°/360° boundaries.
 * 2. D-1 Ascendant interval stability (verified, approximate, unknown).
 * 3. D-9 Ascendant and planetary Navamsha stability & Vargottama evaluation.
 * 4. Birth Panchanga interval stability and sunrise boundary crossing.
 * 5. Vimshottari Dasha uncertainty propagation and Pratyantardasha reliability threshold (≤ 25%).
 * 6. Unified orchestrator coordination.
 */
class MathematicalFoundationStabilityTest extends TestCase
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
     * 1. Test Continuous Arc Unwrapping and Boundary Crossing Detection.
     */
    public function test_continuous_arc_unwrapper_and_boundary_crossings(): void
    {
        // Test angle wrapping across 0°/360°
        $rawAngles = [358.0, 359.5, 1.0, 2.5];
        $unwrapped = ContinuousArcUnwrapper::unwrapSequence($rawAngles);

        $this->assertEqualsWithDelta(358.0, $unwrapped[0], 1e-6);
        $this->assertEqualsWithDelta(359.5, $unwrapped[1], 1e-6);
        $this->assertEqualsWithDelta(361.0, $unwrapped[2], 1e-6);
        $this->assertEqualsWithDelta(362.5, $unwrapped[3], 1e-6);

        // Circular differences
        $this->assertEqualsWithDelta(1.0, ContinuousArcUnwrapper::circularDifference(359.0, 0.0), 1e-6);
        $this->assertEqualsWithDelta(1.0, ContinuousArcUnwrapper::circularDifference(0.0, 359.0), 1e-6);
        $this->assertEqualsWithDelta(30.0, ContinuousArcUnwrapper::circularDifference(15.0, 45.0), 1e-6);

        // Stationary points and monotonic intervals
        // Angle oscillates around 30°: angle(t) = 30 + 5 * sin(t) for t in [0, 2*pi]
        $evaluator = fn(float $t): float => ContinuousArcUnwrapper::normalize(30.0 + 5.0 * sin($t));
        $velocity = fn(float $t): float => 5.0 * cos($t);

        $analysis = ContinuousArcUnwrapper::analyzeInterval($evaluator, $velocity, 0.0, 6.28318, 40);
        $this->assertCount(2, $analysis['stationary_points'], 'Should detect 2 stationary points where cos(t) = 0');

        $boundaries = ContinuousArcUnwrapper::detectBoundaryCrossings($analysis, $evaluator, 30.0);
        $this->assertFalse($boundaries['is_invariant'], 'Oscillating across 30° is not invariant');
        $this->assertNotEmpty($boundaries['crossings']);
    }

    /**
     * 2. Test D-1 Ascendant Stability across Precision Tiers.
     */
    public function test_d1_ascendant_stability_verified_vs_approximate_vs_unknown(): void
    {
        $service = new AscendantStabilityService();

        // New Delhi coordinates
        $lat = 28.6139;
        $lng = 77.2090;
        $tz = 'Asia/Kolkata';

        // Case A: Stable Verified Birth Time (deep inside sign)
        // 2026-05-15 12:00:00 IST (~06:30 UT). At this time, Ascendant in Delhi is ~Leo
        $resultVerified = $service->evaluateStability('2026-05-15', '12:00:00', UserAstrologyProfile::PRECISION_VERIFIED, null, $lat, $lng, $tz);

        $this->assertTrue($resultVerified['d1_ascendant']['is_invariant']);
        $this->assertEquals('confirmed', $resultVerified['d1_ascendant']['status']);
        $this->assertCount(1, $resultVerified['d1_ascendant']['candidate_signs']);
        $this->assertEmpty($resultVerified['d1_ascendant']['boundary_crossings']);

        // Case B: Approximate Birth Time (morning window 06:00 to 12:00)
        // Over 6 hours, Ascendant traverses 3-4 signs
        $resultApprox = $service->evaluateStability('2026-05-15', null, UserAstrologyProfile::PRECISION_APPROXIMATE, 'morning', $lat, $lng, $tz);

        $this->assertFalse($resultApprox['d1_ascendant']['is_invariant']);
        $this->assertEquals('uncertain', $resultApprox['d1_ascendant']['status']);
        $this->assertGreaterThanOrEqual(2, count($resultApprox['d1_ascendant']['candidate_signs']));
        $this->assertNotEmpty($resultApprox['d1_ascendant']['boundary_crossings']);

        // Case C: Unknown Birth Time (Entire civil day)
        // Over 24 hours, Ascendant traverses all 12 signs
        $resultUnknown = $service->evaluateStability('2026-05-15', null, UserAstrologyProfile::PRECISION_UNKNOWN, null, $lat, $lng, $tz);

        $this->assertFalse($resultUnknown['d1_ascendant']['is_invariant']);
        $this->assertEquals('uncertain', $resultUnknown['d1_ascendant']['status']);
        $uniqueSigns = array_unique(array_column($resultUnknown['d1_ascendant']['candidate_signs'], 'id'));
        $this->assertCount(12, $uniqueSigns);
        $this->assertGreaterThanOrEqual(11, count($resultUnknown['d1_ascendant']['boundary_crossings']));
    }

    /**
     * 3. Test D-9 Ascendant Stability and Suppression Rule.
     */
    public function test_d9_ascendant_stability_and_suppression(): void
    {
        $service = new AscendantStabilityService();
        $lat = 28.6139;
        $lng = 77.2090;
        $tz = 'Asia/Kolkata';

        $result = $service->evaluateStability('2026-05-15', '12:00:00', UserAstrologyProfile::PRECISION_VERIFIED, null, $lat, $lng, $tz);

        $d9 = $result['d9_ascendant'];
        $this->assertArrayHasKey('is_invariant', $d9);
        $this->assertArrayHasKey('candidate_navamsha_signs', $d9);
        $this->assertArrayHasKey('candidate_navamsha_padas', $d9);
        $this->assertArrayHasKey('boundary_crossings', $d9);

        if ($d9['is_invariant']) {
            $this->assertEquals('confirmed', $d9['status']);
            $this->assertFalse($d9['is_suppressed']);
            $this->assertNotNull($d9['invariant_navamsha_sign']);
        } else {
            $this->assertEquals('suppressed', $d9['status']);
            $this->assertTrue($d9['is_suppressed']);
            $this->assertGreaterThanOrEqual(2, count($d9['candidate_navamsha_signs']));
        }
    }

    /**
     * 4. Test Planetary Navamsha Stability and Vargottama Evaluation.
     */
    public function test_planetary_navamsha_stability_and_vargottama(): void
    {
        $ascService = new AscendantStabilityService();
        $planetService = new PlanetaryNavamshaStabilityService();
        $tz = 'Asia/Kolkata';

        // Verified interval (±5 min)
        $intervalVerified = $ascService->resolveInterval('2026-05-15', '12:00:00', UserAstrologyProfile::PRECISION_VERIFIED, null, $tz);
        $planetsVerified = $planetService->evaluatePlanetaryStability($intervalVerified, $tz);

        $this->assertCount(9, $planetsVerified);

        // Slow planet (Saturn) moves < 0.001° in 10 minutes -> invariant
        $saturn = $planetsVerified['saturn'];
        $this->assertArrayHasKey('is_invariant', $saturn);
        $this->assertArrayHasKey('d1_sign', $saturn);
        $this->assertArrayHasKey('is_vargottama', $saturn);

        // Vargottama consistency check: if is_vargottama is true, D-1 sign must match D-9 sign
        if ($saturn['is_vargottama']) {
            $this->assertEquals($saturn['d1_sign'], $saturn['invariant_d9_sign']);
        }

        // Test with unknown interval (24 hours) where Moon moves ~13° and MUST cross several Navamshas
        $intervalUnknown = $ascService->resolveInterval('2026-05-15', null, UserAstrologyProfile::PRECISION_UNKNOWN, null, $tz);
        $planetsUnknown = $planetService->evaluatePlanetaryStability($intervalUnknown, $tz);
        $moonUnknown = $planetsUnknown['moon'];

        $this->assertFalse($moonUnknown['is_invariant'], 'Moon must cross Navamsha boundaries across 24 hours');
        $this->assertTrue($moonUnknown['is_suppressed']);
        $this->assertGreaterThanOrEqual(3, count($moonUnknown['candidate_d9_signs']));
    }

    /**
     * 5. Test Birth Panchanga Interval Stability & Sunrise Transition.
     */
    public function test_birth_panchanga_interval_stability_and_sunrise_transition(): void
    {
        $service = new BirthPanchangaStabilityService();
        $lat = 28.6139;
        $lng = 77.2090;
        $tz = 'Asia/Kolkata';

        // Verified birth time (±5 min)
        $panchangVerified = $service->evaluatePanchangaStability('2026-05-15', '12:00:00', UserAstrologyProfile::PRECISION_VERIFIED, null, $lat, $lng, $tz);

        $this->assertArrayHasKey('tithi', $panchangVerified);
        $this->assertArrayHasKey('nakshatra', $panchangVerified);
        $this->assertArrayHasKey('yoga', $panchangVerified);
        $this->assertArrayHasKey('karana', $panchangVerified);
        $this->assertArrayHasKey('vaara', $panchangVerified);
        $this->assertArrayHasKey('sun_times', $panchangVerified);

        // Unknown birth time (Entire civil day 00:00 to 23:59:59)
        // Spans local sunrise (around 05:30 IST in May)
        $unknownPanchang = $service->evaluatePanchangaStability('2026-05-15', null, UserAstrologyProfile::PRECISION_UNKNOWN, null, $lat, $lng, $tz);

        $vaara = $unknownPanchang['vaara'];
        $this->assertTrue($vaara['spans_sunrise'], 'Civil day interval must span sunrise');
        $this->assertFalse($vaara['is_invariant'], 'Vaara crossing sunrise is not invariant in Vedic tradition');
        $this->assertCount(2, $vaara['candidate_vaaras'], 'Must report both pre-sunrise and post-sunrise Vedic weekdays');
        $this->assertNotEmpty($vaara['boundary_crossings']);
    }

    /**
     * 6. Test Vimshottari Dasha Uncertainty Propagation & Pratyantardasha Reliability.
     */
    public function test_vimshottari_dasha_uncertainty_propagation_and_pratyantardasha(): void
    {
        $service = new VimshottariUncertaintyService();

        // Simulate 5 Moon longitude samples across a ±5 min interval around 45.0° (Rohini Nakshatra: 40° to 53°20')
        // In 10 minutes, Moon moves ~0.09°
        $moonLongitudes = [45.00, 45.02, 45.04, 45.07, 45.09];
        $start = CarbonImmutable::parse('2026-05-15 11:55:00', 'Asia/Kolkata');
        $end = CarbonImmutable::parse('2026-05-15 12:05:00', 'Asia/Kolkata');
        $mid = CarbonImmutable::parse('2026-05-15 12:00:00', 'Asia/Kolkata');

        $dashaResult = $service->calculateWithUncertainty($moonLongitudes, $start, $end, $mid, $mid);

        $this->assertEquals('available', $dashaResult['status']);
        $this->assertTrue($dashaResult['is_invariant']);
        $this->assertNotEmpty($dashaResult['mahadashas']);

        $firstMahadasha = $dashaResult['mahadashas'][0];
        $this->assertArrayHasKey('planet', $firstMahadasha);
        $this->assertArrayHasKey('lord', $firstMahadasha);
        $this->assertArrayHasKey('start_date', $firstMahadasha);
        $this->assertArrayHasKey('uncertainty_bounds', $firstMahadasha);
        $this->assertArrayHasKey('antardashas', $firstMahadasha);

        // Test Pratyantardasha reliability rule: W_period <= 0.25 * D_Prat
        $activePrat = $dashaResult['current_dasha']['pratyantardasha'];
        $this->assertNotNull($activePrat);
        $this->assertArrayHasKey('nominal_duration_days', $activePrat);
        $this->assertArrayHasKey('period_uncertainty_days', $activePrat);
        $this->assertArrayHasKey('uncertainty_ratio', $activePrat);
        $this->assertArrayHasKey('is_reliable', $activePrat);

        // Verify mathematical threshold: is_reliable must match ratio <= 0.25
        if ($activePrat['uncertainty_ratio'] <= 0.25) {
            $this->assertTrue($activePrat['is_reliable']);
            $this->assertFalse($activePrat['is_suppressed']);
        } else {
            $this->assertFalse($activePrat['is_reliable']);
            $this->assertTrue($activePrat['is_suppressed']);
        }

        // Test Nakshatra boundary crossing (e.g. Moon from 53.25° to 53.40° crossing Rohini -> Mrigashira at 53.3333°)
        $crossingLongitudes = [53.25, 53.30, 53.35, 53.40];
        $crossingResult = $service->calculateWithUncertainty($crossingLongitudes, $start, $end, $mid);

        $this->assertEquals('uncertain_nakshatra_transition', $crossingResult['status']);
        $this->assertFalse($crossingResult['is_invariant']);
        $this->assertCount(2, $crossingResult['candidate_nakshatras']);
    }

    /**
     * 7. Test Unified Orchestrator AstrologyIntervalStabilityService.
     */
    public function test_orchestrated_astrology_interval_stability_service(): void
    {
        $orchestrator = new AstrologyIntervalStabilityService();
        $lat = 28.6139;
        $lng = 77.2090;
        $tz = 'Asia/Kolkata';

        $evaluation = $orchestrator->analyze(
            birthDate: '2026-05-15',
            birthTime: '12:00:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: $lat,
            longitude: $lng,
            timezone: $tz
        );

        $this->assertEquals(UserAstrologyProfile::PRECISION_VERIFIED, $evaluation['birth_time_precision']);
        $this->assertArrayHasKey('uncertainty_window', $evaluation);
        $this->assertArrayHasKey('d1_ascendant', $evaluation);
        $this->assertArrayHasKey('d9_ascendant', $evaluation);
        $this->assertArrayHasKey('planetary_navamshas', $evaluation);
        $this->assertArrayHasKey('panchanga', $evaluation);
        $this->assertArrayHasKey('vimshottari', $evaluation);
        $this->assertArrayHasKey('summary', $evaluation);

        $summary = $evaluation['summary'];
        $this->assertArrayHasKey('is_d1_ascendant_invariant', $summary);
        $this->assertArrayHasKey('is_d9_ascendant_invariant', $summary);
        $this->assertArrayHasKey('suppressed_planetary_d9_count', $summary);
        $this->assertArrayHasKey('is_moon_nakshatra_invariant', $summary);
        $this->assertArrayHasKey('ready_for_detailed_report', $summary);
    }
}
