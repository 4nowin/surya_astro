<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Transits\SaturnTransitService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SaturnTransitServiceTest extends TestCase
{
    protected SaturnTransitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SaturnTransitService::class);
    }

    /**
     * Test numerical bisection convergence:
     * Verified that root refinement converges to <= 1 minute precision
     * and that the calculated Saturn longitude at the crossing is within 0.0001° of the boundary.
     */
    public function test_numerical_bisection_convergence_and_precision(): void
    {
        // 2023-01-17 Aquarius ingress (300° boundary)
        $startDt = CarbonImmutable::parse('2023-01-16 00:00:00 UTC');
        $endDt = CarbonImmutable::parse('2023-01-18 00:00:00 UTC');

        $startJd = SaturnTransitService::carbonToJulianDay($startDt);
        $endJd = SaturnTransitService::carbonToJulianDay($endDt);

        $crossJd = $this->service->bisectBoundaryCrossing($startJd, $endJd, 300.0);

        // Verify bracket bounds
        $this->assertGreaterThan($startJd, $crossJd);
        $this->assertLessThan($endJd, $crossJd);

        // Verify Saturn longitude at the exact crossing JD
        $saturn = $this->service->calculateSaturnAtJd($crossJd);
        $diff = abs($saturn['longitude'] - 300.0);
        $this->assertLessThan(0.0001, $diff, "Saturn longitude at crossing must be within 0.0001° of 300°");

        // Verify timestamp precision
        $dt = SaturnTransitService::julianDayToCarbon($crossJd);
        $this->assertSame('2023-01-17', $dt->format('Y-m-d'));
        $this->assertSame('09', $dt->format('H'));
    }

    /**
     * Test 360° / 0° zodiac wraparound (Pisces to Aries transition).
     */
    public function test_zodiac_wraparound_360_0_degrees(): void
    {
        $timeline = $this->service->calculateTimeline(
            natalMoonRashi: 1, // Aries
            startDate: '1998-04-01',
            endDate: '1998-05-01'
        );

        $this->assertSame('calculated', $timeline['status']);
        $crossings = $timeline['raw_boundary_crossings'];
        $this->assertCount(1, $crossings);

        $crossing = $crossings[0];
        $this->assertSame(0.0, $crossing['boundary_degree']);
        $this->assertSame(11, $crossing['from_rashi_index']); // Pisces
        $this->assertSame(0, $crossing['to_rashi_index']);   // Aries
        $this->assertSame('Pisces', $crossing['from_sign']);
        $this->assertSame('Aries', $crossing['to_sign']);
        $this->assertSame('forward', $crossing['direction']);
        $this->assertFalse($crossing['is_retrograde']);
        $this->assertStringStartsWith('1998-04-17', $crossing['timestamp_utc']);
    }

    /**
     * Test retrograde egress and re-entry across the exact same boundary:
     * 2022-2023 Capricorn / Aquarius 300° boundary loop.
     */
    public function test_retrograde_egress_and_reentry_across_same_boundary(): void
    {
        $timeline = $this->service->calculateTimeline(
            natalMoonRashi: 11, // Aquarius
            startDate: '2022-01-01',
            endDate: '2023-06-01'
        );

        $crossings = $timeline['raw_boundary_crossings'];
        $this->assertCount(3, $crossings, "Must capture all 3 crossings of the 300° boundary during the retrograde loop");

        // Crossing 1: Direct ingress into Aquarius
        $c1 = $crossings[0];
        $this->assertSame(300.0, $c1['boundary_degree']);
        $this->assertSame('Capricorn', $c1['from_sign']);
        $this->assertSame('Aquarius', $c1['to_sign']);
        $this->assertSame('forward', $c1['direction']);
        $this->assertStringStartsWith('2022-04-28', $c1['timestamp_utc']);

        // Crossing 2: Retrograde egress back into Capricorn
        $c2 = $crossings[1];
        $this->assertSame(300.0, $c2['boundary_degree']);
        $this->assertSame('Aquarius', $c2['from_sign']);
        $this->assertSame('Capricorn', $c2['to_sign']);
        $this->assertSame('reverse', $c2['direction']);
        $this->assertTrue($c2['is_retrograde']);
        $this->assertLessThan(0.0, $c2['speed_at_crossing']);
        $this->assertStringStartsWith('2022-07-12', $c2['timestamp_utc']);

        // Crossing 3: Direct re-ingress into Aquarius
        $c3 = $crossings[2];
        $this->assertSame(300.0, $c3['boundary_degree']);
        $this->assertSame('Capricorn', $c3['from_sign']);
        $this->assertSame('Aquarius', $c3['to_sign']);
        $this->assertSame('forward', $c3['direction']);
        $this->assertFalse($c3['is_retrograde']);
        $this->assertGreaterThan(0.0, $c3['speed_at_crossing']);
        $this->assertStringStartsWith('2023-01-17', $c3['timestamp_utc']);
    }

    /**
     * Test multi-segment phase grouping without concealing individual segments:
     * Capricorn transit for Aquarius natal Moon (Rising Phase 12th).
     */
    public function test_multi_segment_phase_grouping_without_concealing_segments(): void
    {
        $timeline = $this->service->calculateTimeline(
            natalMoonRashi: 11, // Aquarius (12th = Capricorn, 1st = Aquarius)
            startDate: '2020-01-01',
            endDate: '2024-01-01'
        );

        $cycles = $timeline['sade_sati']['cycles'];
        $this->assertNotEmpty($cycles);

        $phases = $cycles[0]['phases'];

        // Rising phase (12th, Capricorn) has 2 segments due to the 2022 retrograde return
        $rising = $phases['rising_phase_12th'];
        $this->assertTrue($rising['has_retrograde_reentry']);
        $this->assertCount(2, $rising['segments']);

        $seg1 = $rising['segments'][0];
        $this->assertSame('direct_ingress', $seg1['start_type']);
        $this->assertSame('direct_egress', $seg1['end_type']);
        $this->assertStringStartsWith('2020-01-24', $seg1['start_utc']);
        $this->assertStringStartsWith('2022-04-28', $seg1['end_utc']);

        $seg2 = $rising['segments'][1];
        $this->assertSame('retrograde_ingress', $seg2['start_type']);
        $this->assertSame('direct_egress', $seg2['end_type']);
        $this->assertStringStartsWith('2022-07-12', $seg2['start_utc']);
        $this->assertStringStartsWith('2023-01-17', $seg2['end_utc']);
    }

    /**
     * Test chronological sequence of Sade Sati phases: Rising (12th) -> Peak (1st) -> Setting (2nd).
     */
    public function test_chronological_phase_ordering(): void
    {
        $timeline = $this->service->calculateTimeline(
            natalMoonRashi: 10, // Capricorn
            startDate: '2015-01-01',
            endDate: '2026-01-01'
        );

        $cycle = $timeline['sade_sati']['cycles'][0];
        $phases = $cycle['phases'];

        $this->assertNotNull($phases['rising_phase_12th']['start_utc']);
        $this->assertNotNull($phases['peak_phase_1st']['start_utc']);
        $this->assertNotNull($phases['setting_phase_2nd']['start_utc']);

        $tRising = CarbonImmutable::parse($phases['rising_phase_12th']['start_utc']);
        $tPeak = CarbonImmutable::parse($phases['peak_phase_1st']['start_utc']);
        $tSetting = CarbonImmutable::parse($phases['setting_phase_2nd']['start_utc']);

        $this->assertTrue($tRising->lessThan($tPeak), "Rising Phase must start before Peak Phase");
        $this->assertTrue($tPeak->lessThan($tSetting), "Peak Phase must start before Setting Phase");
    }

    /**
     * Test Dhaiya calculations:
     * Natal Moon in Scorpio (Rashi 8) -> 4th house is Aquarius (Kantaka Shani).
     * Natal Moon in Gemini (Rashi 3) -> 8th house is Capricorn (Ashtama Shani).
     */
    public function test_dhaiya_qualifying_and_non_qualifying_positions(): void
    {
        // Scorpio Moon (8): Kantaka Shani in Aquarius during 2022-2025
        $timelineScorpio = $this->service->calculateTimeline(
            natalMoonRashi: 8,
            startDate: '2022-01-01',
            endDate: '2025-06-01'
        );

        $dhaiya = $timelineScorpio['dhaiya'];
        $kantaka = $dhaiya['kantaka_shani_4th'];
        $this->assertSame(4, $kantaka['house_from_moon']);
        $this->assertSame('Aquarius', $kantaka['transiting_sign']['name']);
        $this->assertGreaterThan(0, $kantaka['occurrences_count']);

        // Gemini Moon (3): Ashtama Shani in Capricorn during 2020-2023
        $timelineGemini = $this->service->calculateTimeline(
            natalMoonRashi: 3,
            startDate: '2020-01-01',
            endDate: '2023-06-01'
        );

        $ashtama = $timelineGemini['dhaiya']['ashtama_shani_8th'];
        $this->assertSame(8, $ashtama['house_from_moon']);
        $this->assertSame('Capricorn', $ashtama['transiting_sign']['name']);
        $this->assertGreaterThan(0, $ashtama['occurrences_count']);
    }

    /**
     * Test qualitative current-phase fallback evaluation.
     */
    public function test_qualitative_current_phase_fallback(): void
    {
        // On 2024-06-01, Saturn was in Aquarius (sign index 10)
        $summaryAquarius = $this->service->evaluateCurrentPhaseAt(
            natalMoonRashi: 11, // Aquarius Moon -> Saturn in 1st house = Peak Phase
            targetDate: '2024-06-01'
        );

        $this->assertTrue($summaryAquarius['is_in_sade_sati']);
        $this->assertSame('peak_phase_1st', $summaryAquarius['sade_sati_phase']);
        $this->assertSame(1, $summaryAquarius['house_from_moon']);
        $this->assertFalse($summaryAquarius['is_in_dhaiya']);
        $this->assertStringContainsString('Peak Phase', $summaryAquarius['qualitative_title']);

        // For Taurus Moon (2) on 2024-06-01: Saturn in Aquarius is 10th house -> Neutral
        $summaryTaurus = $this->service->evaluateCurrentPhaseAt(
            natalMoonRashi: 2,
            targetDate: '2024-06-01'
        );

        $this->assertFalse($summaryTaurus['is_in_sade_sati']);
        $this->assertFalse($summaryTaurus['is_in_dhaiya']);
        $this->assertSame(10, $summaryTaurus['house_from_moon']);
        $this->assertSame('No Active Saturn Affliction', $summaryTaurus['qualitative_title']);
    }

    /**
     * Test interval stability integration:
     * When Moon crosses sign boundary in uncertainty window, dated timeline is suppressed.
     */
    public function test_profile_natal_moon_uncertainty_suppression(): void
    {
        // Create profile on boundary date: 1990-05-22 where Moon transitions between Pisces (11) and Aries (0) in Asia/Kolkata
        $profile = new UserAstrologyProfile();
        $profile->date_of_birth = CarbonImmutable::parse('1990-05-22');
        $profile->birth_time = null;
        $profile->birth_time_precision = UserAstrologyProfile::PRECISION_UNKNOWN;
        $profile->latitude = 28.6139;
        $profile->longitude = 77.2090;
        $profile->timezone = 'Asia/Kolkata';

        $result = $this->service->calculateForProfile($profile);

        $this->assertSame('suppressed', $result['status']);
        $this->assertFalse($result['is_stable']);
        $this->assertArrayHasKey('qualitative_fallback', $result);
        $this->assertArrayHasKey('saturn_current_sign', $result['qualitative_fallback']);
    }

    /**
     * Test bisection mathematical correctness across cardinal boundaries (0°, 30°, 180°, 330°)
     * for both forward and reverse crossing directions using real Moshier ephemeris.
     */
    public function test_bisection_mathematical_correctness_across_cardinal_boundaries_and_directions(): void
    {
        $cases = [
            // 0° Pisces <-> Aries
            '0_deg_forward' => [
                'boundary' => 0.0,
                'start' => '1998-04-16 00:00:00 UTC',
                'end' => '1998-04-18 00:00:00 UTC',
                'expected_date' => '1998-04-17',
                'expected_dir' => 'forward',
            ],
            '0_deg_reverse' => [
                'boundary' => 0.0,
                'start' => '1968-09-27 00:00:00 UTC',
                'end' => '1968-09-29 00:00:00 UTC',
                'expected_date' => '1968-09-28',
                'expected_dir' => 'reverse',
            ],
            // 30° Aries <-> Taurus
            '30_deg_forward' => [
                'boundary' => 30.0,
                'start' => '2000-06-05 00:00:00 UTC',
                'end' => '2000-06-07 00:00:00 UTC',
                'expected_date' => '2000-06-06',
                'expected_dir' => 'forward',
            ],
            // 180° Virgo <-> Libra
            '180_deg_forward' => [
                'boundary' => 180.0,
                'start' => '2011-11-14 00:00:00 UTC',
                'end' => '2011-11-16 00:00:00 UTC',
                'expected_date' => '2011-11-15',
                'expected_dir' => 'forward',
            ],
            '180_deg_reverse' => [
                'boundary' => 180.0,
                'start' => '2012-05-15 00:00:00 UTC',
                'end' => '2012-05-17 00:00:00 UTC',
                'expected_date' => '2012-05-16',
                'expected_dir' => 'reverse',
            ],
            // 330° Aquarius <-> Pisces
            '330_deg_forward' => [
                'boundary' => 330.0,
                'start' => '2025-03-28 00:00:00 UTC',
                'end' => '2025-03-30 00:00:00 UTC',
                'expected_date' => '2025-03-29',
                'expected_dir' => 'forward',
            ],
            '330_deg_reverse' => [
                'boundary' => 330.0,
                'start' => '1995-08-09 00:00:00 UTC',
                'end' => '1995-08-11 00:00:00 UTC',
                'expected_date' => '1995-08-10',
                'expected_dir' => 'reverse',
            ],
        ];

        foreach ($cases as $name => $c) {
            $startJd = SaturnTransitService::carbonToJulianDay(CarbonImmutable::parse($c['start']));
            $endJd = SaturnTransitService::carbonToJulianDay(CarbonImmutable::parse($c['end']));

            $crossJd = $this->service->bisectBoundaryCrossing($startJd, $endJd, $c['boundary']);

            // Verify bracket containment
            $this->assertGreaterThan($startJd, $crossJd, "{$name}: Root must be > startJd");
            $this->assertLessThan($endJd, $crossJd, "{$name}: Root must be < endJd");

            // Verify residual precision at converged root
            $pos = $this->service->calculateSaturnAtJd($crossJd);
            $diff = abs($pos['longitude'] - $c['boundary']);
            if ($diff > 180.0) {
                $diff = abs($diff - 360.0);
            }
            $this->assertLessThan(
                0.0001,
                $diff,
                "{$name}: Residual at root must be <= 0.0001° (sub-arcsecond accuracy)"
            );

            // Verify timestamp
            $dt = SaturnTransitService::julianDayToCarbon($crossJd);
            $this->assertSame($c['expected_date'], $dt->format('Y-m-d'), "{$name}: Timestamp date mismatch");

            // Verify velocity direction
            if ($c['expected_dir'] === 'forward') {
                $this->assertGreaterThan(0.0, $pos['speed'], "{$name}: Forward crossing must have positive speed");
            } else {
                $this->assertLessThan(0.0, $pos['speed'], "{$name}: Reverse crossing must have negative speed");
            }
        }
    }

    /**
     * Test bisection bracket validation:
     * When endpoints do not bracket the boundary, an InvalidArgumentException must be thrown.
     */
    public function test_bisection_bracket_validation_rejects_non_bracketed_intervals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not bracket boundary');

        // Interval where Saturn is entirely in Aquarius (305° to 306°) testing for 300° boundary
        $startJd = SaturnTransitService::carbonToJulianDay(CarbonImmutable::parse('2023-02-01 00:00:00 UTC'));
        $endJd = SaturnTransitService::carbonToJulianDay(CarbonImmutable::parse('2023-02-02 00:00:00 UTC'));

        $this->service->bisectBoundaryCrossing($startJd, $endJd, 300.0);
    }

    /**
     * Test intra-day double boundary crossing detection via stationary point subdivision:
     *
     * In a single 1-day interval [t0, t0+1.0]:
     * - Saturn starts in Aries at 29.9° moving direct (speed > 0).
     * - Crosses the 30.0° boundary into Taurus.
     * - Reaches a stationary point (v = 0) at 30.1° at t = 0.5.
     * - Reverses direction (retrograde, speed < 0).
     * - Crosses the 30.0° boundary back into Aries.
     * - Ends at 29.9° (Aries) at t = 1.0.
     *
     * Both endpoints are in Aries (rA === rB === 0).
     * Proves that stationary-point partitioning guarantees detection of BOTH crossings.
     */
    public function test_intra_day_double_boundary_crossing_detection_via_stationary_subdivision(): void
    {
        $baseJd = 2460000.0; // Synthetic base Julian Day

        // Synthetic trajectory: lambda(t) = 30.1 - 0.8 * (t - 0.5)^2
        // Speed: dlambda/dt = -1.6 * (t - 0.5)
        $syntheticService = new class extends SaturnTransitService {
            public function __construct()
            {
                // Bypass native FFI initialization for pure synthetic mathematical trajectory test
            }

            public function calculateSaturnAtJd(float $jd, int $bodyId = 6): array
            {
                $t = $jd - 2460000.0;
                $lambda = 30.1 - 0.8 * pow($t - 0.5, 2);
                $speed = -1.6 * ($t - 0.5);
                $rashiIdx = (int) floor($lambda / 30.0);

                return [
                    'longitude' => $lambda,
                    'rashi_index' => $rashiIdx,
                    'speed' => $speed,
                    'is_retrograde' => ($speed < 0.0),
                ];
            }
        };

        // 1. Verify endpoint condition: identical zodiac sign (Aries, rashi = 0)
        $posStart = $syntheticService->calculateSaturnAtJd($baseJd);
        $posEnd = $syntheticService->calculateSaturnAtJd($baseJd + 1.0);

        $this->assertSame(0, $posStart['rashi_index'], "Start must be in Aries (29.9°)");
        $this->assertSame(0, $posEnd['rashi_index'], "End must be in Aries (29.9°)");
        $this->assertEqualsWithDelta(29.9, $posStart['longitude'], 0.001);
        $this->assertEqualsWithDelta(29.9, $posEnd['longitude'], 0.001);

        // 2. Run detectAllBoundaryCrossings over the 1-day interval
        $crossings = $syntheticService->detectAllBoundaryCrossings(
            startJd: $baseJd,
            endJd: $baseJd + 1.0,
            stepDays: 1.0
        );

        // 3. Mathematical proof: BOTH crossings must be detected
        $this->assertCount(2, $crossings, "Must detect both boundary crossings despite identical endpoint signs");

        // Crossing 1: Direct ingress into Taurus (Aries -> Taurus)
        $c1 = $crossings[0];
        $this->assertSame(30.0, $c1['boundary_degree']);
        $this->assertSame(0, $c1['from_rashi_index']);
        $this->assertSame(1, $c1['to_rashi_index']);
        $this->assertSame('Aries', $c1['from_sign']);
        $this->assertSame('Taurus', $c1['to_sign']);
        $this->assertSame('forward', $c1['direction']);
        $this->assertFalse($c1['is_retrograde']);
        $this->assertGreaterThan(0.0, $c1['speed_at_crossing']);

        // Analytical root 1: 30.1 - 0.8*(t - 0.5)^2 = 30.0 => (t - 0.5)^2 = 0.125 => t = 0.5 - sqrt(0.125) = ~0.1464466
        $expectedJd1 = $baseJd + (0.5 - sqrt(0.125));
        $this->assertEqualsWithDelta($expectedJd1, $c1['jd'], 0.0001);

        // Crossing 2: Retrograde egress back to Aries (Taurus -> Aries)
        $c2 = $crossings[1];
        $this->assertSame(30.0, $c2['boundary_degree']);
        $this->assertSame(1, $c2['from_rashi_index']);
        $this->assertSame(0, $c2['to_rashi_index']);
        $this->assertSame('Taurus', $c2['from_sign']);
        $this->assertSame('Aries', $c2['to_sign']);
        $this->assertSame('reverse', $c2['direction']);
        $this->assertTrue($c2['is_retrograde']);
        $this->assertLessThan(0.0, $c2['speed_at_crossing']);

        // Analytical root 2: t = 0.5 + sqrt(0.125) = ~0.8535534
        $expectedJd2 = $baseJd + (0.5 + sqrt(0.125));
        $this->assertEqualsWithDelta($expectedJd2, $c2['jd'], 0.0001);
    }
}
