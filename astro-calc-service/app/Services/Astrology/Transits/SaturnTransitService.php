<?php

declare(strict_types=1);

namespace App\Services\Astrology\Transits;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\NativeEphemerisResolver;
use App\Services\Astrology\Stability\ContinuousArcUnwrapper;
use Carbon\CarbonImmutable;
use FFI;
use FFI\CData;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Astronomical calculation engine for Saturn's Sade Sati and Dhaiya transit periods.
 *
 * Implements Work Package A3 of Astro Setu Milestone M9.2 (Detailed Kundli Report).
 *
 * Core Capabilities:
 *  - Native Moshier ephemeris calculation via NativeEphemerisResolver (True Chitra Paksha Lahiri).
 *  - Daily sampling across user-specified or lifetime date windows with continuous arc unwrapping.
 *  - Bisection root refinement determining exact ingress/exit timestamps to <= 1 minute precision.
 *  - Complete tracking of Saturn's direct and retrograde motion across sign boundaries.
 *  - Classical 3-phase Sade Sati model: Rising (12th), Peak (1st), Setting (2nd) relative to natal Moon.
 *  - Classical Dhaiya model: Kantaka Shani (4th from Moon) and Ashtama Shani (8th from Moon).
 *  - Multi-ingress retrograde phase grouping without concealing individual segments.
 *  - Instant qualitative current-phase fallback and natal Moon stability evaluation.
 */
class SaturnTransitService
{
    /**
     * Bisection numerical tolerance in Julian Days.
     * 1 minute = 1 / 1440.0 ≈ 0.0006944 day.
     * Setting tolerance to 0.00005 day (≈ 4.3 seconds) guarantees <= 1 minute precision.
     */
    public const BISECTION_JD_TOLERANCE = 0.00005;
    public const MAX_BISECTION_ITERATIONS = 40;

    /**
     * Default daily sampling step in days.
     */
    public const DEFAULT_SAMPLE_STEP_DAYS = 1.0;

    protected ?JmeEphFFI $jme = null;
    protected ?CData $resultsBuffer = null;
    protected ?CData $errorBuffer = null;

    public function __construct(?JmeEphFFI $jme = null)
    {
        $this->jme = $jme;
    }

    /**
     * Lazily initialize native FFI ephemeris engine only when calculations execute.
     */
    protected function ensureNativeEphemerisInitialized(): void
    {
        if ($this->resultsBuffer !== null && $this->errorBuffer !== null) {
            return;
        }

        $this->jme ??= app(JmeEphFFI::class);
        JmeRuntime::setTrueCitra($this->jme);

        $ffi = $this->jme->getFFI();
        $this->resultsBuffer = $ffi->new('double[6]');
        $this->errorBuffer = $ffi->new('char[256]');
    }

    /**
     * Calculate complete Sade Sati and Dhaiya transit timeline for a given natal Moon sign
     * across a specified date interval.
     *
     * @param int $natalMoonRashi 1-based Rashi ID (1=Aries..12=Pisces) or 0-based index (0..11)
     * @param CarbonImmutable|string $startDate Interval start
     * @param CarbonImmutable|string $endDate Interval end
     * @param array<string, mixed> $options
     * @return array<string, mixed> Structured machine-readable transit timeline
     */
    public function calculateTimeline(
        int $natalMoonRashi,
        CarbonImmutable|string $startDate,
        CarbonImmutable|string $endDate,
        array $options = []
    ): array {
        $this->ensureNativeEphemerisInitialized();

        $natalMoonRashiIndex = ($natalMoonRashi >= 1 && $natalMoonRashi <= 12)
            ? $natalMoonRashi - 1
            : $natalMoonRashi;

        if ($natalMoonRashiIndex < 0 || $natalMoonRashiIndex > 11) {
            throw new InvalidArgumentException("Invalid natal Moon Rashi: {$natalMoonRashi}. Must be 1..12 or 0..11.");
        }

        $startDt = is_string($startDate) ? CarbonImmutable::parse($startDate, 'UTC') : $startDate->setTimezone('UTC');
        $endDt = is_string($endDate) ? CarbonImmutable::parse($endDate, 'UTC') : $endDate->setTimezone('UTC');

        if ($endDt->lessThanOrEqualTo($startDt)) {
            throw new InvalidArgumentException("End date ({$endDt->toIso8601String()}) must be after start date ({$startDt->toIso8601String()}).");
        }

        $startJd = self::carbonToJulianDay($startDt);
        $endJd = self::carbonToJulianDay($endDt);

        $stepDays = isset($options['sample_step_days'])
            ? (float) $options['sample_step_days']
            : self::DEFAULT_SAMPLE_STEP_DAYS;

        // 1. Detect all exact boundary crossings via daily sampling and bisection refinement
        $crossings = $this->detectAllBoundaryCrossings($startJd, $endJd, $stepDays);

        // 2. Build continuous sign occupancy segments for all 12 zodiac signs
        $signSegments = $this->buildSignOccupancySegments($startJd, $endJd, $crossings);

        // 3. Assemble Sade Sati cycles and phases relative to natal Moon
        $sadeSati = $this->assembleSadeSatiCycles($natalMoonRashiIndex, $signSegments);

        // 4. Assemble Dhaiya transits (4th and 8th from Moon)
        $dhaiya = $this->assembleDhaiyaTransits($natalMoonRashiIndex, $signSegments);

        // 5. Compute current active phase as of reference date (default = now in UTC, or start date if future)
        $refDate = isset($options['reference_date'])
            ? (is_string($options['reference_date']) ? CarbonImmutable::parse($options['reference_date'], 'UTC') : $options['reference_date']->setTimezone('UTC'))
            : CarbonImmutable::now('UTC');

        $currentPhase = $this->evaluateCurrentPhaseAt($natalMoonRashiIndex, $refDate);

        return [
            'status' => 'calculated',
            'natal_moon_sign' => [
                'id' => $natalMoonRashiIndex + 1,
                'index' => $natalMoonRashiIndex,
                'name' => Rasi::from($natalMoonRashiIndex)->getEnglishName(),
                'vedic_name' => Rasi::from($natalMoonRashiIndex)->getName(),
            ],
            'window' => [
                'start_utc' => $startDt->toIso8601String(),
                'end_utc' => $endDt->toIso8601String(),
                'duration_days' => round($endJd - $startJd, 2),
            ],
            'current_transit_summary' => $currentPhase,
            'sade_sati' => $sadeSati,
            'dhaiya' => $dhaiya,
            'raw_boundary_crossings' => $crossings,
        ];
    }

    /**
     * Compute Sade Sati and Dhaiya for a UserAstrologyProfile model,
     * strictly enforcing interval stability checks from Work Package A1.
     *
     * @param UserAstrologyProfile $profile
     * @param CarbonImmutable|string|null $referenceDate
     * @param int $spanYears Lifetime span in years from birth (default 90)
     * @return array<string, mixed>
     */
    public function calculateForProfile(
        UserAstrologyProfile $profile,
        CarbonImmutable|string|null $referenceDate = null,
        int $spanYears = 90
    ): array {
        $this->ensureNativeEphemerisInitialized();

        // 1. Check natal Moon stability across birth uncertainty window
        $birthDate = $profile->date_of_birth->format('Y-m-d');
        $birthTime = $profile->birth_time;
        $precision = (string) $profile->birth_time_precision;
        $approximateWindow = $profile->approximate_time_window;
        $timezone = (string) $profile->timezone;

        $interval = $this->resolveProfileInterval($birthDate, $birthTime, $precision, $approximateWindow, $timezone);

        $startJd = self::carbonToJulianDay($interval['start']);
        $endJd = self::carbonToJulianDay($interval['end']);

        $moonStart = $this->calculateSaturnAtJd($startJd, JmeEphFFI::JME_BODY_MOON);
        $moonEnd = $this->calculateSaturnAtJd($endJd, JmeEphFFI::JME_BODY_MOON);

        $rashiStart = ZodiacDivision::rashiIndex($moonStart['longitude']);
        $rashiEnd = ZodiacDivision::rashiIndex($moonEnd['longitude']);

        $isMoonStable = ($rashiStart === $rashiEnd);

        if (!$isMoonStable && $precision !== UserAstrologyProfile::PRECISION_VERIFIED) {
            // Natal Moon sign is uncertain across the uncertainty window -> Suppress dated timeline!
            $currentTransit = $this->calculateSaturnAtJd(
                self::carbonToJulianDay(CarbonImmutable::now('UTC'))
            );
            $saturnRashiIdx = ZodiacDivision::rashiIndex($currentTransit['longitude']);

            return [
                'status' => 'suppressed',
                'reason' => 'Natal Moon sign crosses sign boundary (' .
                    Rasi::from($rashiStart)->getEnglishName() . ' to ' . Rasi::from($rashiEnd)->getEnglishName() .
                    ') across birth uncertainty window. Dated Sade Sati timeline is suppressed to preserve mathematical honesty.',
                'is_stable' => false,
                'possible_moon_signs' => [
                    Rasi::from($rashiStart)->getEnglishName(),
                    Rasi::from($rashiEnd)->getEnglishName(),
                ],
                'qualitative_fallback' => [
                    'saturn_current_sign' => Rasi::from($saturnRashiIdx)->getEnglishName(),
                    'saturn_current_longitude' => round($currentTransit['longitude'], 4),
                    'saturn_is_retrograde' => $currentTransit['is_retrograde'],
                    'interpretation' => 'Transit timeline requires an accurate birth time. Please consult an astrologer for birth-time rectification.',
                ],
            ];
        }

        // Verified / Stable Moon: calculate complete timeline from birth to +spanYears
        $timelineStart = $interval['mid'];
        $timelineEnd = $timelineStart->addYears($spanYears);

        $ref = $referenceDate
            ? (is_string($referenceDate) ? CarbonImmutable::parse($referenceDate, 'UTC') : $referenceDate)
            : CarbonImmutable::now('UTC');

        return $this->calculateTimeline(
            natalMoonRashi: $rashiStart + 1,
            startDate: $timelineStart,
            endDate: $timelineEnd,
            options: ['reference_date' => $ref]
        );
    }

    /**
     * Qualitative current-phase evaluation of Saturn transiting relative to natal Moon sign.
     *
     * Serves as the approved Gate B fallback when dated timelines are unvalidated,
     * or as a high-speed instant summary.
     *
     * @param int $natalMoonRashi 1..12 (1=Aries..12=Pisces) or 0..11
     * @param CarbonImmutable|string|null $targetDate Target evaluation date (default: now)
     * @return array<string, mixed>
     */
    public function evaluateCurrentPhaseAt(
        int $natalMoonRashi,
        CarbonImmutable|string|null $targetDate = null
    ): array {
        $this->ensureNativeEphemerisInitialized();

        $natalMoonRashiIndex = ($natalMoonRashi >= 1 && $natalMoonRashi <= 12)
            ? $natalMoonRashi - 1
            : $natalMoonRashi;

        $targetDt = $targetDate === null
            ? CarbonImmutable::now('UTC')
            : (is_string($targetDate) ? CarbonImmutable::parse($targetDate, 'UTC') : $targetDate->setTimezone('UTC'));

        $jd = self::carbonToJulianDay($targetDt);
        $saturn = $this->calculateSaturnAtJd($jd);

        $saturnRashiIdx = ZodiacDivision::rashiIndex($saturn['longitude']);
        $houseFromMoon = (($saturnRashiIdx - $natalMoonRashiIndex + 12) % 12) + 1;

        // Classical definitions
        $isSadeSati = in_array($houseFromMoon, [12, 1, 2], true);
        $isDhaiya = in_array($houseFromMoon, [4, 8], true);

        $sadeSatiPhase = match ($houseFromMoon) {
            12 => 'rising_phase_12th',
            1 => 'peak_phase_1st',
            2 => 'setting_phase_2nd',
            default => 'not_active',
        };

        $dhaiyaType = match ($houseFromMoon) {
            4 => 'kantaka_dhaiya_4th',
            8 => 'ashtama_dhaiya_8th',
            default => 'not_active',
        };

        // Determine qualitative summary and classical theme
        $phaseTitle = 'No Active Saturn Affliction';
        $theme = 'Favorable or neutral transit position.';
        if ($isSadeSati) {
            $phaseTitle = match ($houseFromMoon) {
                12 => 'Sade Sati — Rising Phase (12th from Moon)',
                1 => 'Sade Sati — Peak Phase / Janma Shani (1st from Moon)',
                2 => 'Sade Sati — Setting Phase (2nd from Moon)',
                default => 'Sade Sati',
            };
            $theme = match ($houseFromMoon) {
                12 => 'Mental restructuring, expenditures, introspection, and shedding obsolete structures.',
                1 => 'Core identity transformation, intense discipline, heightened responsibility, and perseverance.',
                2 => 'Financial stabilization, family realignment, speech mindfulness, and consolidation of lessons.',
                default => 'Karmic restructuring period.',
            };
        } elseif ($isDhaiya) {
            $phaseTitle = match ($houseFromMoon) {
                4 => 'Kantaka Shani (4th Dhaiya from Moon)',
                8 => 'Ashtama Shani (8th Dhaiya from Moon)',
                default => 'Saturn Dhaiya',
            };
            $theme = match ($houseFromMoon) {
                4 => 'Domestic patience, home environment adjustments, career focus, and emotional grounding.',
                8 => 'Deep transformation, psychological resilience, cautious health management, and endurance.',
                default => 'Minor Saturn 2.5-year transit.',
            };
        }

        return [
            'evaluation_date_utc' => $targetDt->toIso8601String(),
            'saturn_position' => [
                'longitude' => round($saturn['longitude'], 4),
                'sign_id' => $saturnRashiIdx + 1,
                'sign_index' => $saturnRashiIdx,
                'sign_name' => Rasi::from($saturnRashiIdx)->getEnglishName(),
                'sign_vedic_name' => Rasi::from($saturnRashiIdx)->getName(),
                'degree_in_sign' => round(fmod($saturn['longitude'], 30.0), 4),
                'is_retrograde' => $saturn['is_retrograde'],
                'speed_deg_day' => round($saturn['speed'], 5),
            ],
            'house_from_moon' => $houseFromMoon,
            'is_in_sade_sati' => $isSadeSati,
            'sade_sati_phase' => $sadeSatiPhase,
            'is_in_dhaiya' => $isDhaiya,
            'dhaiya_type' => $dhaiyaType,
            'qualitative_title' => $phaseTitle,
            'qualitative_theme' => $theme,
        ];
    }

    /**
     * Daily sampling and bisection root refinement detecting every sign-boundary crossing of Saturn
     * in the interval [startJd, endJd].
     *
     * Guarantees completeness across retrograde turnaround loops:
     * When Saturn's velocity changes sign within a sampling interval (speedA * speedB < 0),
     * a stationary point exists. The interval is partitioned at the stationary point t_station (v=0),
     * producing two strictly monotonic sub-intervals. This guarantees detection of intra-day
     * turnaround crossings even when the start and end signs are identical (rA === rB).
     *
     * @param float $startJd
     * @param float $endJd
     * @param float $stepDays Sampling step in days (default 1.0)
     * @return list<array<string, mixed>> Chronologically sorted crossing events
     */
    public function detectAllBoundaryCrossings(float $startJd, float $endJd, float $stepDays = self::DEFAULT_SAMPLE_STEP_DAYS): array
    {
        $this->ensureNativeEphemerisInitialized();

        $crossings = [];
        $currentJd = $startJd;

        $posA = $this->calculateSaturnAtJd($currentJd);

        while ($currentJd < $endJd) {
            $nextJd = min($endJd, $currentJd + $stepDays);
            $posB = $this->calculateSaturnAtJd($nextJd);

            // Check if velocity changes sign in [currentJd, nextJd] (stationary turnaround point)
            if ($posA['speed'] * $posB['speed'] < 0.0) {
                $stationJd = $this->bisectStationaryPoint($currentJd, $nextJd);
                $posStation = $this->calculateSaturnAtJd($stationJd);

                // Sub-interval 1: [currentJd, stationJd] (monotonic)
                $this->recordCrossingIfAny($currentJd, $stationJd, $posA, $posStation, $crossings);

                // Sub-interval 2: [stationJd, nextJd] (monotonic)
                $this->recordCrossingIfAny($stationJd, $nextJd, $posStation, $posB, $crossings);
            } else {
                // Strictly monotonic interval
                $this->recordCrossingIfAny($currentJd, $nextJd, $posA, $posB, $crossings);
            }

            $currentJd = $nextJd;
            $posA = $posB;
        }

        // Sort chronologically
        usort($crossings, static fn(array $a, array $b) => $a['jd'] <=> $b['jd']);

        return $crossings;
    }

    /**
     * Inspect a monotonic interval [subA_jd, subB_jd] and record a boundary crossing if signs differ.
     *
     * @param float $subA_jd
     * @param float $subB_jd
     * @param array<string, mixed> $posSubA
     * @param array<string, mixed> $posSubB
     * @param list<array<string, mixed>> &$crossings
     */
    protected function recordCrossingIfAny(
        float $subA_jd,
        float $subB_jd,
        array $posSubA,
        array $posSubB,
        array &$crossings
    ): void {
        $rA = $posSubA['rashi_index'];
        $rB = $posSubB['rashi_index'];

        if ($rA === $rB) {
            return;
        }

        $isForward = (($rA + 1) % 12 === $rB);
        $boundaryDeg = (float) fmod(($isForward ? $rB : $rA) * 30.0, 360.0);

        $crossJd = $this->bisectBoundaryCrossing($subA_jd, $subB_jd, $boundaryDeg);
        $posCross = $this->calculateSaturnAtJd($crossJd);
        $dtCross = self::julianDayToCarbon($crossJd);

        $crossings[] = [
            'jd' => $crossJd,
            'timestamp_utc' => $dtCross->toIso8601String(),
            'boundary_degree' => $boundaryDeg,
            'from_rashi_index' => $rA,
            'to_rashi_index' => $rB,
            'from_sign' => Rasi::from($rA)->getEnglishName(),
            'to_sign' => Rasi::from($rB)->getEnglishName(),
            'direction' => $isForward ? 'forward' : 'reverse',
            'is_retrograde' => $posCross['is_retrograde'],
            'speed_at_crossing' => round($posCross['speed'], 5),
        ];
    }

    /**
     * Bisection root finding to find a stationary point (speed = 0) in [jdA, jdB].
     *
     * @param float $jdA
     * @param float $jdB
     * @return float Refined Julian Day where Saturn's sidereal speed is zero
     */
    public function bisectStationaryPoint(float $jdA, float $jdB): float
    {
        $evalSpeed = function (float $jd): float {
            $pos = $this->calculateSaturnAtJd($jd);
            return $pos['speed'];
        };

        $low = $jdA;
        $high = $jdB;
        $vLow = $evalSpeed($low);
        $vHigh = $evalSpeed($high);

        if (abs($vLow) <= 1e-8) {
            return $low;
        }
        if (abs($vHigh) <= 1e-8) {
            return $high;
        }

        // Sign check: if endpoints do not bracket zero, fallback to midpoint
        if (($vLow > 0.0 && $vHigh > 0.0) || ($vLow < 0.0 && $vHigh < 0.0)) {
            return ($low + $high) / 2.0;
        }

        for ($iter = 0; $iter < self::MAX_BISECTION_ITERATIONS; $iter++) {
            $mid = ($low + $high) / 2.0;
            $vMid = $evalSpeed($mid);

            if (abs($vMid) <= 1e-7 || ($high - $low) / 2.0 <= self::BISECTION_JD_TOLERANCE) {
                return $mid;
            }

            if (($vLow > 0.0 && $vMid > 0.0) || ($vLow < 0.0 && $vMid < 0.0)) {
                $low = $mid;
                $vLow = $vMid;
            } else {
                $high = $mid;
                $vHigh = $vMid;
            }
        }

        return ($low + $high) / 2.0;
    }

    /**
     * Bisection root finding to pinpoint the exact Julian Day when Saturn's sidereal longitude
     * equals $targetBoundaryDeg.
     *
     * @param float $jdA Left bracket endpoint
     * @param float $jdB Right bracket endpoint
     * @param float $targetBoundaryDeg 0, 30, 60, ... 330
     * @return float Refined crossing Julian Day
     */
    public function bisectBoundaryCrossing(float $jdA, float $jdB, float $targetBoundaryDeg): float
    {
        $evalError = function (float $jd) use ($targetBoundaryDeg): float {
            $pos = $this->calculateSaturnAtJd($jd);
            $diff = $pos['longitude'] - $targetBoundaryDeg;

            // Handle 360° / 0° circular wraparound (Pisces <-> Aries)
            if ($diff > 180.0) {
                $diff -= 360.0;
            } elseif ($diff < -180.0) {
                $diff += 360.0;
            }

            return $diff;
        };

        $low = $jdA;
        $high = $jdB;
        $fLow = $evalError($low);
        $fHigh = $evalError($high);

        if (abs($fLow) <= 1e-7) {
            return $low;
        }
        if (abs($fHigh) <= 1e-7) {
            return $high;
        }

        // Strict mathematical validation: verify bracket has opposite signs
        if (($fLow > 0.0 && $fHigh > 0.0) || ($fLow < 0.0 && $fHigh < 0.0)) {
            throw new InvalidArgumentException(
                sprintf("Interval [%.6f, %.6f] does not bracket boundary %.1f° (fLow=%.6f, fHigh=%.6f).", $jdA, $jdB, $targetBoundaryDeg, $fLow, $fHigh)
            );
        }

        for ($iter = 0; $iter < self::MAX_BISECTION_ITERATIONS; $iter++) {
            $mid = ($low + $high) / 2.0;
            $fMid = $evalError($mid);

            if (abs($fMid) <= 1e-7 || ($high - $low) / 2.0 <= self::BISECTION_JD_TOLERANCE) {
                return $mid;
            }

            if (($fLow > 0.0 && $fMid > 0.0) || ($fLow < 0.0 && $fMid < 0.0)) {
                $low = $mid;
                $fLow = $fMid;
            } else {
                $high = $mid;
                $fHigh = $fMid;
            }
        }

        $finalMid = ($low + $high) / 2.0;
        $finalResidual = abs($evalError($finalMid));
        if ($finalResidual > 1e-3) {
            throw new RuntimeException(
                sprintf("Bisection root finding failed to converge to boundary %.1f° (residual = %.6f).", $targetBoundaryDeg, $finalResidual)
            );
        }

        return $finalMid;
    }

    /**
     * Build continuous occupancy segments for each of the 12 signs across the interval [startJd, endJd].
     *
     * @param float $startJd
     * @param float $endJd
     * @param list<array<string, mixed>> $crossings
     * @return array<int, list<array<string, mixed>>> Keyed by Rashi index (0..11)
     */
    public function buildSignOccupancySegments(float $startJd, float $endJd, array $crossings): array
    {
        $segmentsBySign = array_fill(0, 12, []);

        $initialPos = $this->calculateSaturnAtJd($startJd);
        $currentSign = $initialPos['rashi_index'];
        $currentSegmentStartJd = $startJd;
        $currentStartType = 'window_start';
        $currentStartDt = self::julianDayToCarbon($startJd);

        foreach ($crossings as $crossing) {
            $crossJd = $crossing['jd'];
            $fromSign = $crossing['from_rashi_index'];
            $toSign = $crossing['to_rashi_index'];

            // Conclude the segment for $fromSign
            $endDt = self::julianDayToCarbon($crossJd);
            $durationDays = max(0.0, round($crossJd - $currentSegmentStartJd, 4));

            $segmentsBySign[$fromSign][] = [
                'sign_id' => $fromSign + 1,
                'sign_index' => $fromSign,
                'sign_name' => Rasi::from($fromSign)->getEnglishName(),
                'start_jd' => $currentSegmentStartJd,
                'start_utc' => $currentStartDt->toIso8601String(),
                'start_type' => $currentStartType,
                'end_jd' => $crossJd,
                'end_utc' => $endDt->toIso8601String(),
                'end_type' => ($crossing['direction'] === 'forward') ? 'direct_egress' : 'retrograde_egress',
                'duration_days' => $durationDays,
            ];

            // Begin new segment for $toSign
            $currentSign = $toSign;
            $currentSegmentStartJd = $crossJd;
            $currentStartType = ($crossing['direction'] === 'forward') ? 'direct_ingress' : 'retrograde_ingress';
            $currentStartDt = $endDt;
        }

        // Conclude final segment reaching $endJd
        $finalDt = self::julianDayToCarbon($endJd);
        $finalDuration = max(0.0, round($endJd - $currentSegmentStartJd, 4));

        $segmentsBySign[$currentSign][] = [
            'sign_id' => $currentSign + 1,
            'sign_index' => $currentSign,
            'sign_name' => Rasi::from($currentSign)->getEnglishName(),
            'start_jd' => $currentSegmentStartJd,
            'start_utc' => $currentStartDt->toIso8601String(),
            'start_type' => $currentStartType,
            'end_jd' => $endJd,
            'end_utc' => $finalDt->toIso8601String(),
            'end_type' => 'window_end',
            'duration_days' => $finalDuration,
        ];

        return $segmentsBySign;
    }

    /**
     * Assemble classical Sade Sati cycles from sign occupancy segments.
     *
     * Phase 1: 12th from natal Moon (Rising Phase)
     * Phase 2: 1st from natal Moon / Janma Shani (Peak Phase)
     * Phase 3: 2nd from natal Moon (Setting Phase)
     *
     * @param int $natalMoonRashiIndex 0..11
     * @param array<int, list<array<string, mixed>>> $signSegments
     * @return array<string, mixed>
     */
    protected function assembleSadeSatiCycles(int $natalMoonRashiIndex, array $signSegments): array
    {
        $sign12 = ($natalMoonRashiIndex - 1 + 12) % 12; // 12th from Moon
        $sign1 = $natalMoonRashiIndex;                  // 1st from Moon
        $sign2 = ($natalMoonRashiIndex + 1) % 12;        // 2nd from Moon

        $allSadeSatiSegments = [];

        foreach ($signSegments[$sign12] as $seg) {
            $allSadeSatiSegments[] = array_merge($seg, ['phase_key' => 'rising', 'house_from_moon' => 12]);
        }
        foreach ($signSegments[$sign1] as $seg) {
            $allSadeSatiSegments[] = array_merge($seg, ['phase_key' => 'peak', 'house_from_moon' => 1]);
        }
        foreach ($signSegments[$sign2] as $seg) {
            $allSadeSatiSegments[] = array_merge($seg, ['phase_key' => 'setting', 'house_from_moon' => 2]);
        }

        // Sort all qualifying segments chronologically
        usort($allSadeSatiSegments, static fn(array $a, array $b) => $a['start_jd'] <=> $b['start_jd']);

        // Group into cycles: a cycle spans consecutive transits through signs 12, 1, 2.
        // A gap of > 15 years between Sade Sati transits indicates a new cycle (~29.5 year orbit).
        $cycles = [];
        $currentCycleSegments = [];

        foreach ($allSadeSatiSegments as $seg) {
            if (empty($currentCycleSegments)) {
                $currentCycleSegments[] = $seg;
                continue;
            }

            $lastSeg = end($currentCycleSegments);
            $gapDays = $seg['start_jd'] - $lastSeg['end_jd'];

            // If gap exceeds 4,000 days (~11 years), Saturn has completed its orbital journey
            // through the remaining 9 signs, beginning the next Sade Sati cycle!
            if ($gapDays > 4000.0) {
                $cycles[] = $this->buildCyclePayload(count($cycles) + 1, $currentCycleSegments);
                $currentCycleSegments = [$seg];
            } else {
                $currentCycleSegments[] = $seg;
            }
        }

        if (!empty($currentCycleSegments)) {
            $cycles[] = $this->buildCyclePayload(count($cycles) + 1, $currentCycleSegments);
        }

        return [
            'qualifying_signs' => [
                'rising_12th' => ['id' => $sign12 + 1, 'name' => Rasi::from($sign12)->getEnglishName()],
                'peak_1st' => ['id' => $sign1 + 1, 'name' => Rasi::from($sign1)->getEnglishName()],
                'setting_2nd' => ['id' => $sign2 + 1, 'name' => Rasi::from($sign2)->getEnglishName()],
            ],
            'cycle_count' => count($cycles),
            'cycles' => $cycles,
        ];
    }

    /**
     * Assemble Dhaiya transits (4th from Moon and 8th from Moon).
     *
     * @param int $natalMoonRashiIndex 0..11
     * @param array<int, list<array<string, mixed>>> $signSegments
     * @return array<string, mixed>
     */
    protected function assembleDhaiyaTransits(int $natalMoonRashiIndex, array $signSegments): array
    {
        $sign4 = ($natalMoonRashiIndex + 3) % 12; // 4th from Moon (Kantaka Shani)
        $sign8 = ($natalMoonRashiIndex + 7) % 12; // 8th from Moon (Ashtama Shani)

        $kantakaPeriods = $this->buildDhaiyaPayload(
            signIndex: $sign4,
            houseFromMoon: 4,
            dhaiyaType: 'kantaka_dhaiya_4th',
            title: 'Kantaka Shani (4th Dhaiya)',
            segments: $signSegments[$sign4]
        );

        $ashtamaPeriods = $this->buildDhaiyaPayload(
            signIndex: $sign8,
            houseFromMoon: 8,
            dhaiyaType: 'ashtama_dhaiya_8th',
            title: 'Ashtama Shani (8th Dhaiya)',
            segments: $signSegments[$sign8]
        );

        return [
            'kantaka_shani_4th' => $kantakaPeriods,
            'ashtama_shani_8th' => $ashtamaPeriods,
        ];
    }

    /**
     * Build structured payload for a Sade Sati cycle.
     *
     * @param int $cycleNum
     * @param list<array<string, mixed>> $segments
     * @return array<string, mixed>
     */
    protected function buildCyclePayload(int $cycleNum, array $segments): array
    {
        $firstSeg = $segments[0];
        $lastSeg = end($segments);

        $phases = [
            'rising_phase_12th' => ['segments' => [], 'total_days' => 0.0],
            'peak_phase_1st' => ['segments' => [], 'total_days' => 0.0],
            'setting_phase_2nd' => ['segments' => [], 'total_days' => 0.0],
        ];

        $totalActiveDays = 0.0;

        foreach ($segments as $seg) {
            $pk = match ($seg['phase_key']) {
                'rising' => 'rising_phase_12th',
                'peak' => 'peak_phase_1st',
                'setting' => 'setting_phase_2nd',
            };

            $phases[$pk]['segments'][] = [
                'start_utc' => $seg['start_utc'],
                'start_type' => $seg['start_type'],
                'end_utc' => $seg['end_utc'],
                'end_type' => $seg['end_type'],
                'duration_days' => $seg['duration_days'],
            ];
            $phases[$pk]['total_days'] += $seg['duration_days'];
            $totalActiveDays += $seg['duration_days'];
        }

        // Add phase overall start and end dates
        foreach ($phases as $k => &$pData) {
            if (!empty($pData['segments'])) {
                $pData['start_utc'] = $pData['segments'][0]['start_utc'];
                $pData['end_utc'] = end($pData['segments'])['end_utc'];
                $pData['has_retrograde_reentry'] = count($pData['segments']) > 1;
            } else {
                $pData['start_utc'] = null;
                $pData['end_utc'] = null;
                $pData['has_retrograde_reentry'] = false;
            }
        }

        return [
            'cycle_number' => $cycleNum,
            'cycle_start_utc' => $firstSeg['start_utc'],
            'cycle_end_utc' => $lastSeg['end_utc'],
            'total_span_days' => round($lastSeg['end_jd'] - $firstSeg['start_jd'], 2),
            'total_active_days' => round($totalActiveDays, 2),
            'phases' => $phases,
        ];
    }

    /**
     * Build structured payload for Dhaiya transits.
     *
     * @param int $signIndex
     * @param int $houseFromMoon
     * @param string $dhaiyaType
     * @param string $title
     * @param list<array<string, mixed>> $segments
     * @return array<string, mixed>
     */
    protected function buildDhaiyaPayload(
        int $signIndex,
        int $houseFromMoon,
        string $dhaiyaType,
        string $title,
        array $segments
    ): array {
        $signEnum = Rasi::from($signIndex);

        // Group into distinct transit occurrences across life (~29.5 year orbit between transits)
        $occurrences = [];
        $currentOccurrence = [];

        foreach ($segments as $seg) {
            if (empty($currentOccurrence)) {
                $currentOccurrence[] = $seg;
                continue;
            }

            $lastSeg = end($currentOccurrence);
            $gapDays = $seg['start_jd'] - $lastSeg['end_jd'];

            if ($gapDays > 4000.0) {
                $occurrences[] = $this->buildDhaiyaOccurrence($currentOccurrence);
                $currentOccurrence = [$seg];
            } else {
                $currentOccurrence[] = $seg;
            }
        }

        if (!empty($currentOccurrence)) {
            $occurrences[] = $this->buildDhaiyaOccurrence($currentOccurrence);
        }

        return [
            'dhaiya_type' => $dhaiyaType,
            'title' => $title,
            'house_from_moon' => $houseFromMoon,
            'transiting_sign' => [
                'id' => $signIndex + 1,
                'index' => $signIndex,
                'name' => $signEnum->getEnglishName(),
                'vedic_name' => $signEnum->getName(),
            ],
            'occurrences_count' => count($occurrences),
            'occurrences' => $occurrences,
        ];
    }

    /**
     * Build single occurrence of Dhaiya (with possible retrograde multi-segments).
     *
     * @param list<array<string, mixed>> $segments
     * @return array<string, mixed>
     */
    protected function buildDhaiyaOccurrence(array $segments): array
    {
        $first = $segments[0];
        $last = end($segments);
        $totalDays = 0.0;

        $cleanSegments = [];
        foreach ($segments as $s) {
            $cleanSegments[] = [
                'start_utc' => $s['start_utc'],
                'start_type' => $s['start_type'],
                'end_utc' => $s['end_utc'],
                'end_type' => $s['end_type'],
                'duration_days' => $s['duration_days'],
            ];
            $totalDays += $s['duration_days'];
        }

        return [
            'start_utc' => $first['start_utc'],
            'end_utc' => $last['end_utc'],
            'total_span_days' => round($last['end_jd'] - $first['start_jd'], 2),
            'active_days' => round($totalDays, 2),
            'has_retrograde_reentry' => count($cleanSegments) > 1,
            'segments' => $cleanSegments,
        ];
    }

    /**
     * Compute sidereal position and speed of Saturn (or another body) at Julian Day.
     *
     * @param float $jd
     * @param int $bodyId Default JME_BODY_SATURN
     * @return array{longitude: float, rashi_index: int, speed: float, is_retrograde: bool}
     */
    public function calculateSaturnAtJd(float $jd, int $bodyId = JmeEphFFI::JME_BODY_SATURN): array
    {
        $this->ensureNativeEphemerisInitialized();

        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION
            | JmeEphFFI::JME_CALC_SIDEREAL
            | JmeEphFFI::JME_CALC_SPEED;

        $rc = $this->jme->jme_calc_ut($jd, $bodyId, $flags, $this->resultsBuffer, $this->errorBuffer);
        if ($rc < 0) {
            throw new RuntimeException("jme_calc_ut failed for Saturn at JD {$jd}: " . FFI::string($this->errorBuffer));
        }

        $longitude = AstroCore::normalize((float) $this->resultsBuffer[0]);
        $speed = (float) $this->resultsBuffer[3];
        $rashiIdx = ZodiacDivision::rashiIndex($longitude);

        return [
            'longitude' => $longitude,
            'rashi_index' => $rashiIdx,
            'speed' => $speed,
            'is_retrograde' => ($speed < 0.0),
        ];
    }

    /**
     * Convert CarbonImmutable into Julian Day (UTC).
     */
    public static function carbonToJulianDay(CarbonImmutable $dt): float
    {
        $utc = $dt->setTimezone('UTC');
        return ($utc->getTimestamp() + $utc->micro / 1_000_000.0) / 86400.0 + 2440587.5;
    }

    /**
     * Convert Julian Day into CarbonImmutable (UTC).
     */
    public static function julianDayToCarbon(float $jd): CarbonImmutable
    {
        $unixSec = ($jd - 2440587.5) * 86400.0;
        return CarbonImmutable::createFromTimestampUTC((int) round($unixSec));
    }

    /**
     * Resolve birth interval and precision bounds.
     *
     * @return array{start: CarbonImmutable, mid: CarbonImmutable, end: CarbonImmutable}
     */
    protected function resolveProfileInterval(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        string $timezone
    ): array {
        if ($precision === UserAstrologyProfile::PRECISION_VERIFIED) {
            $timeStr = $birthTime ?? '12:00:00';
            $mid = CarbonImmutable::parse("{$birthDate} {$timeStr}", $timezone);
            return [
                'start' => $mid->subMinutes(5),
                'mid' => $mid,
                'end' => $mid->addMinutes(5),
            ];
        }

        if ($precision === UserAstrologyProfile::PRECISION_APPROXIMATE) {
            if ($approximateWindow && isset(\App\Services\Astrology\KundliService::TIME_WINDOWS[$approximateWindow])) {
                [$startStr, $endStr] = \App\Services\Astrology\KundliService::TIME_WINDOWS[$approximateWindow];
                $start = CarbonImmutable::parse("{$birthDate} {$startStr}", $timezone);
                $end = CarbonImmutable::parse("{$birthDate} {$endStr}", $timezone);
                $midDiff = (int) round($start->diffInSeconds($end) / 2);
                $mid = $start->addSeconds($midDiff);
                return ['start' => $start, 'mid' => $mid, 'end' => $end];
            }

            $timeStr = $birthTime ?? '12:00:00';
            $mid = CarbonImmutable::parse("{$birthDate} {$timeStr}", $timezone);
            return [
                'start' => $mid->subHours(2),
                'mid' => $mid,
                'end' => $mid->addHours(2),
            ];
        }

        // Unknown: civil day
        $start = CarbonImmutable::parse("{$birthDate} 00:00:00", $timezone);
        $end = CarbonImmutable::parse("{$birthDate} 23:59:59", $timezone);
        $mid = CarbonImmutable::parse("{$birthDate} 12:00:00", $timezone);
        return ['start' => $start, 'mid' => $mid, 'end' => $end];
    }
}
