<?php

declare(strict_types=1);

namespace App\Services\Astrology\Stability;

use App\Services\Astrology\KundliService;
use Carbon\CarbonImmutable;
use FFI;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Service for evaluating planetary D-9 Navamsha stability and Vargottama status across uncertainty intervals.
 *
 * Implements Work Package A1 of Astro Setu Milestone M9.2.
 */
class PlanetaryNavamshaStabilityService
{
    public const DEGREES_PER_NAVAMSHA = 3.3333333333333335; // 3° 20' = 10/3 degrees
    public const DEGREES_PER_RASHI = 30.0;

    protected ?JmeEphFFI $jme = null;
    protected ?AstronomyService $astronomy = null;
    protected ?CData $resultsBuffer = null;
    protected ?CData $errorBuffer = null;

    public function __construct(
        ?JmeEphFFI $jme = null,
        ?AstronomyService $astronomy = null
    ) {
        $this->jme = $jme;
        $this->astronomy = $astronomy;
    }

    protected function ensureInitialized(): void
    {
        if ($this->resultsBuffer !== null && $this->errorBuffer !== null) {
            return;
        }

        $this->jme ??= app(JmeEphFFI::class);
        $this->astronomy ??= app(AstronomyService::class);

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
     * Evaluate Navamsha stability for all 9 Navagrahas over the birth interval.
     *
     * @param array{start: CarbonImmutable, mid: CarbonImmutable, end: CarbonImmutable, precision: string} $interval
     * @return array<string, array<string, mixed>>
     */
    public function evaluatePlanetaryStability(array $interval, string $timezone): array
    {
        $this->ensureInitialized();

        $tStart = (float) $interval['start']->getTimestamp();
        $tEnd = (float) $interval['end']->getTimestamp();
        $tMid = (float) $interval['mid']->getTimestamp();

        $planets = [];

        foreach (KundliService::BODIES as $key => $body) {
            if ($key === 'ketu') {
                continue; // Derived from Rahu
            }

            $bodyId = $body['id'];

            // Function to evaluate planetary longitude at timestamp t
            $angleEvaluator = function (float $t) use ($bodyId, $timezone): float {
                $dt = CarbonImmutable::createFromTimestamp((int) round($t), $timezone);
                $jd = $this->toJulianDay($dt);

                $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_SPEED;
                $rc = $this->jme->jme_calc_ut($jd, $bodyId, $flags, $this->resultsBuffer, $this->errorBuffer);
                if ($rc < 0) {
                    throw new RuntimeException("jme_calc_ut failed for body {$bodyId}: " . FFI::string($this->errorBuffer));
                }

                return AstroCore::normalize((float) $this->resultsBuffer[0]);
            };

            // Function to evaluate motional velocity dlambda/dt (deg/day) at timestamp t
            $velocityEvaluator = function (float $t) use ($bodyId, $timezone): float {
                $dt = CarbonImmutable::createFromTimestamp((int) round($t), $timezone);
                $jd = $this->toJulianDay($dt);

                $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_SPEED;
                $rc = $this->jme->jme_calc_ut($jd, $bodyId, $flags, $this->resultsBuffer, $this->errorBuffer);
                if ($rc < 0) {
                    throw new RuntimeException("jme_calc_ut failed for body {$bodyId}: " . FFI::string($this->errorBuffer));
                }

                return (float) $this->resultsBuffer[3]; // Daily speed in deg/day
            };

            // Sample density based on planet speed
            // Moon: moves ~13.2°/day -> 24 to 48 samples
            // Other planets: 12 to 24 samples
            $samples = ($key === 'moon') ? 36 : 16;
            $analyzed = ContinuousArcUnwrapper::analyzeInterval($angleEvaluator, $velocityEvaluator, $tStart, $tEnd, $samples);
            $boundaryAnalysis = ContinuousArcUnwrapper::detectBoundaryCrossings($analyzed, $angleEvaluator, self::DEGREES_PER_NAVAMSHA);

            $planets[$key] = $this->formatPlanetResult($key, $body, $tMid, $interval, $boundaryAnalysis, $angleEvaluator, $timezone);
        }

        // Derive Ketu directly from Rahu (180° opposite)
        $rahuResult = $planets['rahu'];
        $planets['ketu'] = $this->deriveKetuResult($rahuResult, $tMid, $interval, $timezone);

        return $planets;
    }

    /**
     * Format stability result for an individual celestial body.
     */
    protected function formatPlanetResult(
        string $key,
        array $body,
        float $tMid,
        array $interval,
        array $boundaryAnalysis,
        \Closure $angleEvaluator,
        string $timezone
    ): array {
        $midLon = ContinuousArcUnwrapper::normalize($angleEvaluator($tMid));
        $d1SignId = (int) floor($midLon / self::DEGREES_PER_RASHI) + 1;
        $d1Enum = Rasi::from($d1SignId - 1);

        $navamshaIndex = (int) floor($midLon / self::DEGREES_PER_NAVAMSHA);
        $d9SignId = ($navamshaIndex % 12 + 12) % 12 + 1;
        $d9Enum = Rasi::from($d9SignId - 1);

        $isVargottama = ($d1SignId === $d9SignId);

        if ($boundaryAnalysis['is_invariant']) {
            return [
                'key' => $key,
                'name' => $body['name'],
                'name_hi' => $body['name_hi'],
                'is_invariant' => true,
                'is_suppressed' => false,
                'status' => 'confirmed',
                'longitude' => round($midLon, 4),
                'd1_sign' => [
                    'id' => $d1SignId,
                    'index' => $d1SignId - 1,
                    'name' => $d1Enum->getEnglishName(),
                    'vedic_name' => $d1Enum->getName(),
                ],
                'd9_navamsha' => [
                    'id' => $d9SignId,
                    'index' => $d9SignId - 1,
                    'name' => $d9Enum->getEnglishName(),
                    'vedic_name' => $d9Enum->getName(),
                    'sign_lord' => $this->getSignLord($d9SignId),
                ],
                'invariant_d9_sign' => $d9Enum->getEnglishName(),
                'is_vargottama' => $isVargottama,
                'reason' => 'D-9 Navamsha placement invariant across complete uncertainty interval.',
            ];
        }

        // Boundary crossing detected
        $crossings = [];
        $candidateSigns = [];
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

        return [
            'key' => $key,
            'name' => $body['name'],
            'name_hi' => $body['name_hi'],
            'is_invariant' => false,
            'is_suppressed' => true,
            'status' => 'suppressed',
            'longitude' => round($midLon, 4),
            'd1_sign' => [
                'id' => $d1SignId,
                'index' => $d1SignId - 1,
                'name' => $d1Enum->getEnglishName(),
                'vedic_name' => $d1Enum->getName(),
            ],
            'd9_navamsha' => [
                'id' => null,
                'index' => null,
                'name' => null,
                'vedic_name' => null,
                'sign_lord' => null,
            ],
            'candidate_d9_signs' => array_values($candidateSigns),
            'is_vargottama' => false,
            'reason' => 'D-9 Navamsha placement suppressed: crossed Navamsha boundary within uncertainty interval.',
            'crossings' => $crossings,
        ];
    }

    /**
     * Derive Ketu stability from Rahu.
     */
    protected function deriveKetuResult(
        array $rahuResult,
        float $tMid,
        array $interval,
        string $timezone
    ): array {
        $midLon = AstroCore::normalize($rahuResult['longitude'] + 180.0);
        $d1SignId = (int) floor($midLon / self::DEGREES_PER_RASHI) + 1;
        $d1Enum = Rasi::from($d1SignId - 1);

        $navamshaIndex = (int) floor($midLon / self::DEGREES_PER_NAVAMSHA);
        $d9SignId = ($navamshaIndex % 12 + 12) % 12 + 1;
        $d9Enum = Rasi::from($d9SignId - 1);

        $isVargottama = ($d1SignId === $d9SignId);

        if ($rahuResult['is_invariant']) {
            return [
                'key' => 'ketu',
                'name' => 'Ketu',
                'name_hi' => 'केतु',
                'is_invariant' => true,
                'is_suppressed' => false,
                'status' => 'confirmed',
                'longitude' => round($midLon, 4),
                'd1_sign' => [
                    'id' => $d1SignId,
                    'index' => $d1SignId - 1,
                    'name' => $d1Enum->getEnglishName(),
                    'vedic_name' => $d1Enum->getName(),
                ],
                'd9_navamsha' => [
                    'id' => $d9SignId,
                    'index' => $d9SignId - 1,
                    'name' => $d9Enum->getEnglishName(),
                    'vedic_name' => $d9Enum->getName(),
                    'sign_lord' => $this->getSignLord($d9SignId),
                ],
                'invariant_d9_sign' => $d9Enum->getEnglishName(),
                'is_vargottama' => $isVargottama,
                'reason' => 'D-9 Navamsha placement invariant across complete uncertainty interval.',
            ];
        }

        return [
            'key' => 'ketu',
            'name' => 'Ketu',
            'name_hi' => 'केतु',
            'is_invariant' => false,
            'is_suppressed' => true,
            'status' => 'suppressed',
            'longitude' => round($midLon, 4),
            'd1_sign' => [
                'id' => $d1SignId,
                'index' => $d1SignId - 1,
                'name' => $d1Enum->getEnglishName(),
                'vedic_name' => $d1Enum->getName(),
            ],
            'd9_navamsha' => [
                'id' => null,
                'index' => null,
                'name' => null,
                'vedic_name' => null,
                'sign_lord' => null,
            ],
            'candidate_d9_signs' => $rahuResult['candidate_d9_signs'] ?? [],
            'is_vargottama' => false,
            'reason' => 'D-9 Navamsha placement suppressed: crossed Navamsha boundary within uncertainty interval.',
            'crossings' => $rahuResult['crossings'] ?? [],
        ];
    }

    protected function getSignLord(int $rashiId): string
    {
        return match ($rashiId) {
            1, 8 => 'Mars',
            2, 7 => 'Venus',
            3, 6 => 'Mercury',
            4 => 'Moon',
            5 => 'Sun',
            9, 12 => 'Jupiter',
            10, 11 => 'Saturn',
            default => 'Unknown',
        };
    }
}
