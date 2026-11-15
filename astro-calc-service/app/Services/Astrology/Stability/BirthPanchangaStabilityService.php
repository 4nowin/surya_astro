<?php

declare(strict_types=1);

namespace App\Services\Astrology\Stability;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\KundliService;
use Carbon\CarbonImmutable;
use FFI;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Karana;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Paksha;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Enums\Yoga;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Service for evaluating interval stability of the 7 Vedic Birth Panchanga limbs.
 *
 * Implements Work Package A1 of Astro Setu Milestone M9.2.
 */
class BirthPanchangaStabilityService
{
    public const DEGREES_PER_TITHI = 12.0;
    public const DEGREES_PER_KARANA = 6.0;
    public const DEGREES_PER_NAKSHATRA = 13.333333333333334; // 13° 20' = 40/3 degrees
    public const DEGREES_PER_PADA = 3.3333333333333335;       // 3° 20' = 10/3 degrees
    public const DEGREES_PER_YOGA = 13.333333333333334;       // 13° 20' = 40/3 degrees

    protected ?JmeEphFFI $jme = null;
    protected ?AstronomyService $astronomy = null;
    protected ?SunService $sunService = null;
    protected ?CData $resultsBuffer = null;
    protected ?CData $errorBuffer = null;

    public function __construct(
        ?JmeEphFFI $jme = null,
        ?AstronomyService $astronomy = null,
        ?SunService $sunService = null
    ) {
        $this->jme = $jme;
        $this->astronomy = $astronomy;
        $this->sunService = $sunService;
    }

    protected function ensureInitialized(): void
    {
        if ($this->resultsBuffer !== null && $this->errorBuffer !== null) {
            return;
        }

        $this->jme ??= app(JmeEphFFI::class);
        $this->astronomy ??= app(AstronomyService::class);
        $this->sunService ??= app(SunService::class);

        JmeRuntime::setTrueCitra($this->jme);

        $ffi = $this->jme->getFFI();
        $this->resultsBuffer = $ffi->new('double[6]');
        $this->errorBuffer = $ffi->new('char[256]');
    }

    /**
     * Compute Julian Day (UTC) from CarbonImmutable.
     */
    protected function toJulianDay(CarbonImmutable $dt): float
    {
        return $this->astronomy->toJulianDayUtc([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $dt->getTimezone()->getName(),
        ]);
    }

    /**
     * Resolve the birth uncertainty interval T = [start, end].
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
     * Comprehensive evaluation of the 7 Panchanga limbs across the birth-time interval.
     *
     * @return array<string, mixed>
     */
    public function evaluatePanchangaStability(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        float $latitude,
        float $longitude,
        string $timezone
    ): array {
        $this->ensureInitialized();

        $interval = $this->resolveInterval($birthDate, $birthTime, $precision, $approximateWindow, $timezone);
        $tStart = (float) $interval['start']->getTimestamp();
        $tEnd = (float) $interval['end']->getTimestamp();
        $tMid = (float) $interval['mid']->getTimestamp();

        // 1. Calculate local Sunrise and Sunset for birth date & location
        $sunTimes = $this->calculateSunTimes($interval['mid'], $latitude, $longitude, $timezone);

        // 2. Evaluator functions for Sun and Moon longitudes
        $getSunLon = function (float $t) use ($timezone): float {
            $dt = CarbonImmutable::createFromTimestamp((int) round($t), $timezone);
            $jd = $this->toJulianDay($dt);
            $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
            $this->jme->jme_calc_ut($jd, JmeEphFFI::JME_BODY_SUN, $flags, $this->resultsBuffer, $this->errorBuffer);
            return AstroCore::normalize((float) $this->resultsBuffer[0]);
        };

        $getMoonLon = function (float $t) use ($timezone): float {
            $dt = CarbonImmutable::createFromTimestamp((int) round($t), $timezone);
            $jd = $this->toJulianDay($dt);
            $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
            $this->jme->jme_calc_ut($jd, JmeEphFFI::JME_BODY_MOON, $flags, $this->resultsBuffer, $this->errorBuffer);
            return AstroCore::normalize((float) $this->resultsBuffer[0]);
        };

        // Angular evaluators for limbs
        $tithiEvaluator = function (float $t) use ($getSunLon, $getMoonLon): float {
            return AstroCore::normalize($getMoonLon($t) - $getSunLon($t));
        };

        $yogaEvaluator = function (float $t) use ($getSunLon, $getMoonLon): float {
            return AstroCore::normalize($getSunLon($t) + $getMoonLon($t));
        };

        // Velocities (Moon moves ~13.2°/day, Sun ~1.0°/day => diff ~12.2°/day ~ 0.000141°/s)
        $constantPositiveVel = function (float $t): float {
            return 1.0; // Strictly increasing
        };

        $sampleCount = max(12, min(48, (int) ceil(($tEnd - $tStart) / 1800.0)));

        // 3. Evaluate Tithi & Paksha
        $tithiAnalysis = ContinuousArcUnwrapper::analyzeInterval($tithiEvaluator, $constantPositiveVel, $tStart, $tEnd, $sampleCount);
        $tithiCrossings = ContinuousArcUnwrapper::detectBoundaryCrossings($tithiAnalysis, $tithiEvaluator, self::DEGREES_PER_TITHI);
        $tithiResult = $this->formatTithiResult($interval, $tithiCrossings, $tithiEvaluator, $tMid, $timezone);

        // 4. Evaluate Karana (Half-Tithi, 6° step)
        $karanaCrossings = ContinuousArcUnwrapper::detectBoundaryCrossings($tithiAnalysis, $tithiEvaluator, self::DEGREES_PER_KARANA);
        $karanaResult = $this->formatKaranaResult($interval, $karanaCrossings, $tithiEvaluator, $tMid, $timezone);

        // 5. Evaluate Nakshatra & Pada
        $moonAnalysis = ContinuousArcUnwrapper::analyzeInterval($getMoonLon, $constantPositiveVel, $tStart, $tEnd, $sampleCount);
        $nakshatraCrossings = ContinuousArcUnwrapper::detectBoundaryCrossings($moonAnalysis, $getMoonLon, self::DEGREES_PER_NAKSHATRA);
        $padaCrossings = ContinuousArcUnwrapper::detectBoundaryCrossings($moonAnalysis, $getMoonLon, self::DEGREES_PER_PADA);

        $nakshatraResult = $this->formatNakshatraResult($interval, $nakshatraCrossings, $getMoonLon, $tMid, $timezone);
        $padaResult = $this->formatPadaResult($interval, $padaCrossings, $getMoonLon, $tMid, $timezone);

        // 6. Evaluate Yoga
        $yogaAnalysis = ContinuousArcUnwrapper::analyzeInterval($yogaEvaluator, $constantPositiveVel, $tStart, $tEnd, $sampleCount);
        $yogaCrossings = ContinuousArcUnwrapper::detectBoundaryCrossings($yogaAnalysis, $yogaEvaluator, self::DEGREES_PER_YOGA);
        $yogaResult = $this->formatYogaResult($interval, $yogaCrossings, $yogaEvaluator, $tMid, $timezone);

        // 7. Evaluate Vaara (Solar Weekday relative to local sunrise)
        $vaaraResult = $this->evaluateVaaraStability($interval, $sunTimes['sunrise'], $timezone);

        return [
            'interval' => [
                'start' => $interval['start']->toIso8601String(),
                'mid' => $interval['mid']->toIso8601String(),
                'end' => $interval['end']->toIso8601String(),
                'precision' => $precision,
                'timezone' => $timezone,
            ],
            'sun_times' => [
                'sunrise' => $sunTimes['sunrise']->toIso8601String(),
                'sunset' => $sunTimes['sunset']->toIso8601String(),
            ],
            'tithi' => $tithiResult,
            'paksha' => $tithiResult['paksha'],
            'vaara' => $vaaraResult,
            'nakshatra' => $nakshatraResult,
            'pada' => $padaResult,
            'yoga' => $yogaResult,
            'karana' => $karanaResult,
        ];
    }

    /**
     * Calculate topocentric Sunrise and Sunset for a given location and date.
     *
     * @return array{sunrise: CarbonImmutable, sunset: CarbonImmutable}
     */
    public function calculateSunTimes(CarbonImmutable $dt, float $latitude, float $longitude, string $timezone): array
    {
        $this->ensureInitialized();

        $sunTimes = $this->sunService->getSunriseSunset([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'elevation' => 0.0,
        ]);

        return [
            'sunrise' => $sunTimes[0],
            'sunset' => $sunTimes[1],
        ];
    }

    /**
     * Evaluate Vaara stability based on local sunrise-to-sunrise rule.
     */
    protected function evaluateVaaraStability(
        array $interval,
        CarbonImmutable $localSunrise,
        string $timezone
    ): array {
        $start = $interval['start'];
        $end = $interval['end'];
        $mid = $interval['mid'];

        // Determine weekday from solar day perspective
        // If before local sunrise, belongs to previous solar day
        $getVaraFor = function (CarbonImmutable $c) use ($localSunrise): Vara {
            $civilWeekday = (int) $c->format('w'); // 0=Sunday, 1=Monday... 6=Saturday
            if ($c->lessThan($localSunrise)) {
                $solarWeekday = ($civilWeekday - 1 + 7) % 7;
            } else {
                $solarWeekday = $civilWeekday;
            }
            return Vara::from($solarWeekday);
        };

        $startVara = $getVaraFor($start);
        $endVara = $getVaraFor($end);
        $midVara = $getVaraFor($mid);

        // Does the uncertainty interval span the local sunrise boundary?
        $spansSunrise = ($start->lessThan($localSunrise) && $end->greaterThanOrEqualTo($localSunrise));

        if (!$spansSunrise && $startVara === $endVara) {
            $singleCandidate = [
                'id' => $midVara->value,
                'name' => $midVara->getEnglishName(),
                'vedic_name' => $midVara->getName(),
                'interval_start' => $start->toIso8601String(),
                'interval_end' => $end->toIso8601String(),
            ];

            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'spans_sunrise' => false,
                'id' => $midVara->value,
                'name' => $midVara->getEnglishName(),
                'vedic_name' => $midVara->getName(),
                'ruling_planet' => $midVara->getRulingPlanet(),
                'reason' => 'Vaara invariant across uncertainty interval relative to local sunrise.',
                'candidates' => [$singleCandidate],
                'candidate_vaaras' => [$singleCandidate],
                'boundary_crossings' => [],
            ];
        }

        // Interval spans sunrise (especially common for unknown precision: 00:00 to 23:59)
        $candidates = [
            [
                'id' => $startVara->value,
                'name' => $startVara->getEnglishName(),
                'vedic_name' => $startVara->getName(),
                'interval_start' => $start->toIso8601String(),
                'interval_end' => $localSunrise->toIso8601String(),
            ],
            [
                'id' => $endVara->value,
                'name' => $endVara->getEnglishName(),
                'vedic_name' => $endVara->getName(),
                'interval_start' => $localSunrise->toIso8601String(),
                'interval_end' => $end->toIso8601String(),
            ],
        ];

        return [
            'status' => 'qualified',
            'is_invariant' => false,
            'spans_sunrise' => true,
            'id' => null,
            'name' => null,
            'vedic_name' => null,
            'ruling_planet' => null,
            'reason' => 'Vaara uncertainty: birth interval crosses local sunrise (' .
                $localSunrise->format('H:i:s') . '). Time before sunrise belongs to ' .
                $startVara->getEnglishName() . '; time after belongs to ' . $endVara->getEnglishName() . '.',
            'sunrise_boundary' => $localSunrise->toIso8601String(),
            'candidates' => $candidates,
            'candidate_vaaras' => $candidates,
            'boundary_crossings' => [
                [
                    'timestamp' => $localSunrise->toIso8601String(),
                    'from_vaara' => $startVara->getEnglishName(),
                    'to_vaara' => $endVara->getEnglishName(),
                ],
            ],
        ];
    }

    /**
     * Format Tithi and Paksha results.
     */
    protected function formatTithiResult(
        array $interval,
        array $crossings,
        \Closure $tithiEvaluator,
        float $tMid,
        string $timezone
    ): array {
        $midDeg = ContinuousArcUnwrapper::normalize($tithiEvaluator($tMid));
        $midIdx = (int) floor($midDeg / self::DEGREES_PER_TITHI) + 1; // 1 to 30
        $midEnum = Tithi::from($midIdx);

        $getPaksha = function (int $tithiId): array {
            $isShukla = ($tithiId <= 15);
            $enum = $isShukla ? Paksha::Shukla : Paksha::Krishna;
            return [
                'id' => $enum->value,
                'name' => $enum->name,
                'vedic_name' => $enum->getName(),
            ];
        };

        if ($crossings['is_invariant']) {
            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'id' => $midIdx,
                'name' => $midEnum->name,
                'vedic_name' => $midEnum->getName(),
                'paksha' => array_merge(['status' => 'confirmed', 'is_invariant' => true], $getPaksha($midIdx)),
                'reason' => 'Tithi invariant across uncertainty interval.',
                'candidates' => [
                    [
                        'id' => $midIdx,
                        'name' => $midEnum->name,
                        'vedic_name' => $midEnum->getName(),
                        'interval_start' => $interval['start']->toIso8601String(),
                        'interval_end' => $interval['end']->toIso8601String(),
                    ],
                ],
            ];
        }

        // Tithi boundary crossed
        $candidates = [];
        $currentStart = $interval['start'];
        $pakshaCandidates = [];

        foreach ($crossings['crossings'] as $c) {
            $cCarbon = CarbonImmutable::createFromTimestamp((int) round($c['t']), $timezone);
            $fromId = ($c['from_index'] % 30 + 30) % 30 + 1;
            $fEnum = Tithi::from($fromId);

            $candidates[] = [
                'id' => $fromId,
                'name' => $fEnum->name,
                'vedic_name' => $fEnum->getName(),
                'interval_start' => $currentStart->toIso8601String(),
                'interval_end' => $cCarbon->toIso8601String(),
            ];
            $pakshaCandidates[] = $getPaksha($fromId);
            $currentStart = $cCarbon;
        }

        $lastC = end($crossings['crossings']);
        $toId = ($lastC['to_index'] % 30 + 30) % 30 + 1;
        $tEnum = Tithi::from($toId);

        $candidates[] = [
            'id' => $toId,
            'name' => $tEnum->name,
            'vedic_name' => $tEnum->getName(),
            'interval_start' => $currentStart->toIso8601String(),
            'interval_end' => $interval['end']->toIso8601String(),
        ];
        $pakshaCandidates[] = $getPaksha($toId);

        $pakshaInvariant = ($pakshaCandidates[0]['id'] === end($pakshaCandidates)['id']);

        return [
            'status' => 'qualified',
            'is_invariant' => false,
            'id' => null,
            'name' => null,
            'vedic_name' => null,
            'paksha' => [
                'status' => $pakshaInvariant ? 'confirmed' : 'qualified',
                'is_invariant' => $pakshaInvariant,
                'id' => $pakshaInvariant ? $pakshaCandidates[0]['id'] : null,
                'name' => $pakshaInvariant ? $pakshaCandidates[0]['name'] : null,
                'vedic_name' => $pakshaInvariant ? $pakshaCandidates[0]['vedic_name'] : null,
            ],
            'reason' => 'Tithi crosses boundary within uncertainty interval (' . count($candidates) . ' candidates detected).',
            'candidates' => $candidates,
        ];
    }

    /**
     * Format Karana results.
     */
    protected function formatKaranaResult(
        array $interval,
        array $crossings,
        \Closure $tithiEvaluator,
        float $tMid,
        string $timezone
    ): array {
        $midDeg = ContinuousArcUnwrapper::normalize($tithiEvaluator($tMid));
        $midIdx = (int) floor($midDeg / self::DEGREES_PER_KARANA) + 1; // 1 to 60
        $karanaInfo = Karana::getFromLongitudes(0.0, $midDeg);
        $karanaName = $karanaInfo[0];

        if ($crossings['is_invariant']) {
            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'id' => $midIdx,
                'name' => $karanaName,
                'reason' => 'Karana invariant across uncertainty interval.',
            ];
        }

        return [
            'status' => 'qualified',
            'is_invariant' => false,
            'id' => null,
            'name' => null,
            'reason' => 'Karana crosses boundary within uncertainty interval.',
            'crossings_count' => count($crossings['crossings']),
        ];
    }

    /**
     * Format Nakshatra results.
     */
    protected function formatNakshatraResult(
        array $interval,
        array $crossings,
        \Closure $getMoonLon,
        float $tMid,
        string $timezone
    ): array {
        $midDeg = ContinuousArcUnwrapper::normalize($getMoonLon($tMid));
        $midIdx = (int) floor($midDeg / self::DEGREES_PER_NAKSHATRA);
        $midEnum = Nakshatra::from($midIdx);

        if ($crossings['is_invariant']) {
            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'id' => $midIdx + 1,
                'index' => $midIdx,
                'name' => $midEnum->name,
                'vedic_name' => $midEnum->getName(),
                'ruling_planet' => $midEnum->getRulingPlanet(),
                'reason' => 'Nakshatra invariant across uncertainty interval.',
            ];
        }

        $candidates = [];
        $currentStart = $interval['start'];

        foreach ($crossings['crossings'] as $c) {
            $cCarbon = CarbonImmutable::createFromTimestamp((int) round($c['t']), $timezone);
            $fromIdx = ($c['from_index'] % 27 + 27) % 27;
            $fEnum = Nakshatra::from($fromIdx);

            $candidates[] = [
                'id' => $fromIdx + 1,
                'name' => $fEnum->name,
                'vedic_name' => $fEnum->getName(),
                'ruling_planet' => $fEnum->getRulingPlanet(),
                'interval_start' => $currentStart->toIso8601String(),
                'interval_end' => $cCarbon->toIso8601String(),
            ];
            $currentStart = $cCarbon;
        }

        $lastC = end($crossings['crossings']);
        $toIdx = ($lastC['to_index'] % 27 + 27) % 27;
        $tEnum = Nakshatra::from($toIdx);
        $candidates[] = [
            'id' => $toIdx + 1,
            'name' => $tEnum->name,
            'vedic_name' => $tEnum->getName(),
            'ruling_planet' => $tEnum->getRulingPlanet(),
            'interval_start' => $currentStart->toIso8601String(),
            'interval_end' => $interval['end']->toIso8601String(),
        ];

        return [
            'status' => 'qualified',
            'is_invariant' => false,
            'id' => null,
            'name' => null,
            'vedic_name' => null,
            'ruling_planet' => null,
            'reason' => 'Nakshatra boundary crossed within uncertainty interval.',
            'candidates' => $candidates,
        ];
    }

    /**
     * Format Pada results.
     */
    protected function formatPadaResult(
        array $interval,
        array $crossings,
        \Closure $getMoonLon,
        float $tMid,
        string $timezone
    ): array {
        $midDeg = ContinuousArcUnwrapper::normalize($getMoonLon($tMid));
        $pada = ZodiacDivision::nakshatraPada($midDeg);

        if ($crossings['is_invariant']) {
            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'pada' => $pada,
                'reason' => 'Pada invariant across uncertainty interval.',
            ];
        }

        return [
            'status' => 'qualified',
            'is_invariant' => false,
            'pada' => null,
            'reason' => 'Pada boundary crossed within uncertainty interval.',
        ];
    }

    /**
     * Format Yoga results.
     */
    protected function formatYogaResult(
        array $interval,
        array $crossings,
        \Closure $yogaEvaluator,
        float $tMid,
        string $timezone
    ): array {
        $midDeg = ContinuousArcUnwrapper::normalize($yogaEvaluator($tMid));
        $midIdx = (int) floor($midDeg / self::DEGREES_PER_YOGA);
        $midEnum = Yoga::from($midIdx);

        if ($crossings['is_invariant']) {
            return [
                'status' => 'confirmed',
                'is_invariant' => true,
                'id' => $midIdx + 1,
                'name' => $midEnum->name,
                'vedic_name' => $midEnum->getName(),
                'reason' => 'Yoga invariant across uncertainty interval.',
            ];
        }

        return [
            'status' => 'qualified',
            'is_invariant' => false,
            'id' => null,
            'name' => null,
            'vedic_name' => null,
            'reason' => 'Yoga boundary crossed within uncertainty interval.',
            'crossings_count' => count($crossings['crossings']),
        ];
    }
}
