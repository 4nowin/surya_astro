<?php

declare(strict_types=1);

namespace App\Services\Astrology\Stability;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\KundliService;
use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;
use JmeEph\FFI\JmeEphFFI;

/**
 * Service for evaluating interval stability of D-1 and D-9 Ascendants.
 *
 * Implements Work Package A1 of Astro Setu Milestone M9.2.
 */
class AscendantStabilityService
{
    public const DEGREES_PER_RASHI = 30.0;
    public const DEGREES_PER_NAVAMSHA = 3.3333333333333335; // 3° 20' = 10/3 degrees

    protected ?AstronomyService $astronomy = null;
    protected ?JmeEphFFI $jme = null;

    public function __construct(
        ?AstronomyService $astronomy = null,
        ?JmeEphFFI $jme = null
    ) {
        $this->astronomy = $astronomy;
        $this->jme = $jme;
    }

    protected function getAstronomy(): AstronomyService
    {
        if ($this->astronomy === null) {
            $this->jme ??= app(JmeEphFFI::class);
            JmeRuntime::setTrueCitra($this->jme);
            $this->astronomy = app(AstronomyService::class);
        }
        return $this->astronomy;
    }

    /**
     * Resolve the birth uncertainty interval T = [start, end].
     *
     * @return array{start: CarbonImmutable, mid: CarbonImmutable, end: CarbonImmutable, precision: string}
     */
    public function resolveInterval(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        string $timezone
    ): array {
        if ($precision === UserAstrologyProfile::PRECISION_VERIFIED) {
            $timeStr = $birthTime ?: '12:00:00';
            $exact = CarbonImmutable::parse("{$birthDate} {$timeStr}", $timezone);
            return [
                'start' => $exact->subMinutes(5),
                'mid' => $exact,
                'end' => $exact->addMinutes(5),
                'precision' => $precision,
            ];
        }

        if ($precision === UserAstrologyProfile::PRECISION_APPROXIMATE) {
            $window = KundliService::TIME_WINDOWS[$approximateWindow ?? 'morning'] ?? KundliService::TIME_WINDOWS['morning'];
            $start = CarbonImmutable::parse("{$birthDate} {$window[0]}", $timezone);
            $end = CarbonImmutable::parse("{$birthDate} {$window[1]}", $timezone);
            $midSec = (int) (($start->getTimestamp() + $end->getTimestamp()) / 2);
            $mid = CarbonImmutable::createFromTimestamp($midSec, $timezone);

            return [
                'start' => $start,
                'mid' => $mid,
                'end' => $end,
                'precision' => $precision,
            ];
        }

        // Unknown precision: full local civil day
        $start = CarbonImmutable::parse("{$birthDate} 00:00:00", $timezone);
        $end = CarbonImmutable::parse("{$birthDate} 23:59:59", $timezone);
        $mid = CarbonImmutable::parse("{$birthDate} 12:00:00", $timezone);

        return [
            'start' => $start,
            'mid' => $mid,
            'end' => $end,
            'precision' => $precision,
        ];
    }

    /**
     * Compute sidereal Ascendant at a given timestamp.
     */
    public function getAscendantAt(CarbonImmutable $dateTime, float $latitude, float $longitude, string $timezone): float
    {
        return $this->getAstronomy()->getAscendant([
            'year' => (int) $dateTime->format('Y'),
            'month' => (int) $dateTime->format('m'),
            'day' => (int) $dateTime->format('d'),
            'hour' => (int) $dateTime->format('H'),
            'minute' => (int) $dateTime->format('i'),
            'second' => (int) $dateTime->format('s'),
            'timezone' => $timezone,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Comprehensive evaluation of D-1 and D-9 Ascendants over the uncertainty interval.
     *
     * @return array<string, mixed>
     */
    public function evaluateStability(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        float $latitude,
        float $longitude,
        string $timezone
    ): array {
        $interval = $this->resolveInterval($birthDate, $birthTime, $precision, $approximateWindow, $timezone);
        $tStart = (float) $interval['start']->getTimestamp();
        $tEnd = (float) $interval['end']->getTimestamp();

        // Evaluator for Ascendant angle as a function of Unix timestamp t
        $evaluator = function (float $t) use ($latitude, $longitude, $timezone): float {
            $dt = CarbonImmutable::createFromTimestamp((int) round($t), $timezone);
            return $this->getAscendantAt($dt, $latitude, $longitude, $timezone);
        };

        // Velocity estimator (Ascendant direct diurnal rate ~ 360° / 86164s ~ 0.004178°/s)
        $velocityEvaluator = function (float $t) use ($evaluator): float {
            $deltaT = 5.0; // 5 seconds
            $a1 = $evaluator($t);
            $a2 = $evaluator($t + $deltaT);
            $diff = $a2 - $a1;
            if ($diff < -180.0) {
                $diff += 360.0;
            } elseif ($diff > 180.0) {
                $diff -= 360.0;
            }
            return $diff / $deltaT;
        };

        // Number of samples: verified (10m = 600s) -> 12 samples (every 50s)
        // approximate (4h = 14400s) -> 48 samples
        // unknown (24h = 86400s) -> 144 samples
        $durationSeconds = $tEnd - $tStart;
        $sampleCount = (int) max(12, min(144, ceil($durationSeconds / 600.0)));

        $analyzed = ContinuousArcUnwrapper::analyzeInterval($evaluator, $velocityEvaluator, $tStart, $tEnd, $sampleCount);

        // 1. Evaluate D-1 Ascendant Stability (30° boundaries)
        $d1BoundaryAnalysis = ContinuousArcUnwrapper::detectBoundaryCrossings($analyzed, $evaluator, self::DEGREES_PER_RASHI);
        $d1Result = $this->formatD1Result($interval, $analyzed, $d1BoundaryAnalysis, $evaluator, $timezone);

        // 2. Evaluate D-9 Navamsha Ascendant Stability (3° 20' boundaries)
        $d9BoundaryAnalysis = ContinuousArcUnwrapper::detectBoundaryCrossings($analyzed, $evaluator, self::DEGREES_PER_NAVAMSHA);
        $d9Result = $this->formatD9Result($interval, $analyzed, $d9BoundaryAnalysis, $evaluator, $timezone);

        return [
            'interval' => [
                'start' => $interval['start']->toIso8601String(),
                'mid' => $interval['mid']->toIso8601String(),
                'end' => $interval['end']->toIso8601String(),
                'duration_seconds' => (int) $durationSeconds,
                'precision' => $precision,
                'timezone' => $timezone,
            ],
            'd1_ascendant' => $d1Result,
            'd9_ascendant' => $d9Result,
        ];
    }

    /**
     * Format D-1 Ascendant results according to frozen specification.
     */
    protected function formatD1Result(
        array $interval,
        array $analyzed,
        array $boundaryAnalysis,
        \Closure $evaluator,
        string $timezone
    ): array {
        $precision = $interval['precision'];
        $tStart = (float) $interval['start']->getTimestamp();
        $tEnd = (float) $interval['end']->getTimestamp();
        $tMid = (float) $interval['mid']->getTimestamp();

        $midDeg = ContinuousArcUnwrapper::normalize($evaluator($tMid));
        $rashiIdxMid = (int) floor($midDeg / self::DEGREES_PER_RASHI);
        $rashiEnumMid = Rasi::from($rashiIdxMid);

        if ($boundaryAnalysis['is_invariant']) {
            $nakIdx = ZodiacDivision::nakshatraIndex($midDeg);
            $nakEnum = Nakshatra::from($nakIdx);
            $degInRashi = fmod($midDeg, 30.0);

            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'id' => $rashiEnumMid->value + 1,
                'index' => $rashiEnumMid->value,
                'name' => $rashiEnumMid->getEnglishName(),
                'vedic_name' => $rashiEnumMid->getName(),
                'longitude' => round($midDeg, 4),
                'degrees' => round($degInRashi, 4),
                'degrees_dms' => $this->formatDms($degInRashi),
                'nakshatra' => [
                    'id' => $nakEnum->value + 1,
                    'index' => $nakEnum->value,
                    'name' => $nakEnum->name,
                    'vedic_name' => $nakEnum->getName(),
                    'pada' => ZodiacDivision::nakshatraPada($midDeg),
                    'lord' => $nakEnum->getRulingPlanet(),
                ],
                'reason' => 'D-1 Ascendant invariant across complete recorded uncertainty interval.',
                'candidate_signs' => [
                    [
                        'id' => $rashiEnumMid->value + 1,
                        'name' => $rashiEnumMid->getEnglishName(),
                        'vedic_name' => $rashiEnumMid->getName(),
                        'interval_start' => $interval['start']->toIso8601String(),
                        'interval_end' => $interval['end']->toIso8601String(),
                    ],
                ],
                'boundary_crossings' => [],
                'house_dependent_content_suppressed' => false,
            ];
        }

        // Boundary crossing occurred
        $candidateSigns = [];
        $currentStart = $interval['start'];

        foreach ($boundaryAnalysis['crossings'] as $crossing) {
            $tCross = $crossing['t'];
            $crossCarbon = CarbonImmutable::createFromTimestamp((int) round($tCross), $timezone);
            $fromIdx = ($crossing['from_index'] % 12 + 12) % 12;
            $rEnum = Rasi::from($fromIdx);

            $candidateSigns[] = [
                'id' => $rEnum->value + 1,
                'name' => $rEnum->getEnglishName(),
                'vedic_name' => $rEnum->getName(),
                'interval_start' => $currentStart->toIso8601String(),
                'interval_end' => $crossCarbon->toIso8601String(),
            ];
            $currentStart = $crossCarbon;
        }

        // Final candidate up to interval end
        $lastCrossing = end($boundaryAnalysis['crossings']);
        $toIdx = ($lastCrossing['to_index'] % 12 + 12) % 12;
        $rEnumLast = Rasi::from($toIdx);
        $candidateSigns[] = [
            'id' => $rEnumLast->value + 1,
            'name' => $rEnumLast->getEnglishName(),
            'vedic_name' => $rEnumLast->getName(),
            'interval_start' => $currentStart->toIso8601String(),
            'interval_end' => $interval['end']->toIso8601String(),
        ];

        return [
            'status' => 'uncertain',
            'is_invariant' => false,
            'id' => null,
            'name' => null,
            'vedic_name' => null,
            'longitude' => null,
            'reason' => 'D-1 Ascendant crosses sign boundary within uncertainty interval (' .
                count($candidateSigns) . ' candidate signs detected).',
            'candidate_signs' => $candidateSigns,
            'boundary_crossings' => $boundaryAnalysis['crossings'],
            // Per frozen specification: multi-candidate presentation is permitted ONLY
            // when all candidate charts are calculated; otherwise house-dependent content is suppressed.
            'house_dependent_content_suppressed' => true,
        ];
    }

    /**
     * Format D-9 Navamsha Ascendant results according to frozen specification.
     */
    protected function formatD9Result(
        array $interval,
        array $analyzed,
        array $boundaryAnalysis,
        \Closure $evaluator,
        string $timezone
    ): array {
        $tMid = (float) $interval['mid']->getTimestamp();
        $midDeg = ContinuousArcUnwrapper::normalize($evaluator($tMid));

        $candidateSigns = [];
        $candidatePadas = [];
        $crossings = [];

        foreach ($boundaryAnalysis['crossings'] as $c) {
            $tCross = $c['t'];
            $carbonCross = CarbonImmutable::createFromTimestamp((int) round($tCross), $timezone);
            $fromD9 = ($c['from_index'] % 12 + 12) % 12 + 1;
            $toD9 = ($c['to_index'] % 12 + 12) % 12 + 1;

            $fromName = Rasi::from($fromD9 - 1)->getEnglishName();
            $toName = Rasi::from($toD9 - 1)->getEnglishName();
            $candidateSigns[$fromD9] = $fromName;
            $candidateSigns[$toD9] = $toName;

            $crossings[] = [
                'timestamp' => $carbonCross->toIso8601String(),
                'from_sign' => $fromName,
                'to_sign' => $toName,
            ];
        }

        if ($boundaryAnalysis['is_invariant']) {
            $d9SignId = ((int) (floor($midDeg / self::DEGREES_PER_NAVAMSHA) % 12 + 12) % 12) + 1;
            $d9Enum = Rasi::from($d9SignId - 1);
            $degInNavamsha = fmod($midDeg, self::DEGREES_PER_NAVAMSHA);

            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'is_suppressed' => false,
                'id' => $d9SignId,
                'index' => $d9SignId - 1,
                'name' => $d9Enum->getEnglishName(),
                'vedic_name' => $d9Enum->getName(),
                'invariant_navamsha_sign' => $d9Enum->getEnglishName(),
                'degrees' => round($degInNavamsha, 4),
                'candidate_navamsha_signs' => [$d9Enum->getEnglishName()],
                'candidate_navamsha_padas' => [ZodiacDivision::nakshatraPada($midDeg)],
                'boundary_crossings' => [],
                'reason' => 'D-9 Navamsha Ascendant invariant across complete recorded uncertainty interval.',
            ];
        }

        return [
            'status' => 'suppressed',
            'is_invariant' => false,
            'is_suppressed' => true,
            'id' => null,
            'index' => null,
            'name' => null,
            'vedic_name' => null,
            'invariant_navamsha_sign' => null,
            'candidate_navamsha_signs' => array_values($candidateSigns),
            'candidate_navamsha_padas' => array_values($candidatePadas),
            'boundary_crossings' => $crossings,
            'reason' => 'D-9 Navamsha Ascendant suppressed: boundary crossed within recorded uncertainty interval.',
        ];
    }

    protected function formatDms(float $deg): string
    {
        $d = (int) floor($deg);
        $remM = ($deg - $d) * 60.0;
        $m = (int) floor($remM);
        $s = (int) round(($remM - $m) * 60.0);
        if ($s >= 60) {
            $s = 0;
            $m++;
        }
        if ($m >= 60) {
            $m = 0;
            $d++;
        }

        return sprintf("%d° %02d' %02d\"", $d, $m, $s);
    }
}
