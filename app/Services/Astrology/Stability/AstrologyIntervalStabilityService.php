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
use JayeshMepani\PanchangCore\Core\AstroCore;
use JmeEph\FFI\JmeEphFFI;

/**
 * Unified Stability and Uncertainty Orchestration Service.
 *
 * Implements Work Package A1 of Astro Setu Milestone M9.2.
 * Coordinates interval stability evaluation for Ascendants (D-1, D-9),
 * planetary Navamsha placements, Birth Panchanga limbs, and Vimshottari uncertainty bounds.
 */
class AstrologyIntervalStabilityService
{
    public const STABILITY_ENGINE_VERSION = 'M9.2_A1_V1';

    protected AscendantStabilityService $ascendantStabilityService;
    protected PlanetaryNavamshaStabilityService $planetaryStabilityService;
    protected BirthPanchangaStabilityService $panchangaStabilityService;
    protected VimshottariUncertaintyService $dashaUncertaintyService;

    protected ?JmeEphFFI $jme = null;
    protected ?AstronomyService $astronomy = null;
    protected ?CData $resultsBuffer = null;
    protected ?CData $errorBuffer = null;

    public function __construct(
        ?AscendantStabilityService $ascendantStabilityService = null,
        ?PlanetaryNavamshaStabilityService $planetaryStabilityService = null,
        ?BirthPanchangaStabilityService $panchangaStabilityService = null,
        ?VimshottariUncertaintyService $dashaUncertaintyService = null,
        ?JmeEphFFI $jme = null,
        ?AstronomyService $astronomy = null
    ) {
        $this->ascendantStabilityService = $ascendantStabilityService ?? app(AscendantStabilityService::class);
        $this->planetaryStabilityService = $planetaryStabilityService ?? app(PlanetaryNavamshaStabilityService::class);
        $this->panchangaStabilityService = $panchangaStabilityService ?? app(BirthPanchangaStabilityService::class);
        $this->dashaUncertaintyService = $dashaUncertaintyService ?? app(VimshottariUncertaintyService::class);
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
     * Complete interval stability analysis across all astrological domains.
     *
     * @param string $birthDate YYYY-MM-DD
     * @param string|null $birthTime HH:MM:SS or null
     * @param string $precision 'verified', 'approximate', 'unknown'
     * @param string|null $approximateWindow 'morning', 'afternoon', etc.
     * @param float $latitude Decimal latitude
     * @param float $longitude Decimal longitude
     * @param string $timezone Canonical IANA timezone
     * @param string|null $targetDate Target date for dasha evaluation
     * @param string $locale 'en' or 'hi'
     * @return array<string, mixed> Structured result payload for consumption by subsequent work packages
     */
    public function analyze(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        float $latitude,
        float $longitude,
        string $timezone,
        ?string $targetDate = null,
        string $locale = 'en'
    ): array {
        $this->ensureInitialized();

        // 1. Resolve birth interval T = [start, end]
        $interval = $this->ascendantStabilityService->resolveInterval(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            timezone: $timezone
        );

        // 2. Evaluate D-1 & D-9 Ascendant stability
        $ascendantData = $this->ascendantStabilityService->evaluateStability(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone
        );

        // 3. Evaluate 9 Navagraha planetary Navamsha placements & Vargottama status
        $planetaryData = $this->planetaryStabilityService->evaluatePlanetaryStability(
            interval: $interval,
            timezone: $timezone
        );

        // 4. Evaluate 7 Birth Panchanga limbs & Sunrise/Sunset
        $panchangaData = $this->panchangaStabilityService->evaluatePanchangaStability(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone
        );

        // 5. Sample Moon longitudes across interval for Vimshottari uncertainty propagation
        $tStart = (float) $interval['start']->getTimestamp();
        $tEnd = (float) $interval['end']->getTimestamp();
        $sampleCount = max(8, min(36, (int) ceil(($tEnd - $tStart) / 3600.0)));
        $dtSec = ($tEnd - $tStart) / $sampleCount;

        $moonSamples = [];
        for ($s = 0; $s <= $sampleCount; $s++) {
            $t = ($s === $sampleCount) ? $tEnd : ($tStart + $s * $dtSec);
            $dt = CarbonImmutable::createFromTimestamp((int) round($t), $timezone);
            $jd = $this->toJulianDay($dt);

            $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
            $this->jme->jme_calc_ut($jd, JmeEphFFI::JME_BODY_MOON, $flags, $this->resultsBuffer, $this->errorBuffer);
            $moonSamples[] = AstroCore::normalize((float) $this->resultsBuffer[0]);
        }

        $dashaData = $this->dashaUncertaintyService->calculateWithUncertainty(
            moonLongitudes: $moonSamples,
            birthStart: $interval['start'],
            birthEnd: $interval['end'],
            birthMid: $interval['mid'],
            targetDate: $targetDate,
            locale: $locale
        );

        $summary = [
            'is_d1_ascendant_invariant' => $ascendantData['d1_ascendant']['is_invariant'],
            'is_d9_ascendant_invariant' => $ascendantData['d9_ascendant']['is_invariant'],
            'suppressed_planetary_d9_count' => count(array_filter($planetaryData, fn($p) => $p['is_suppressed'] ?? false)),
            'is_moon_nakshatra_invariant' => $dashaData['is_invariant'] ?? false,
            'ready_for_detailed_report' => $ascendantData['d1_ascendant']['is_invariant'],
        ];

        return [
            'metadata' => [
                'engine' => 'Astro Setu Interval Stability Engine',
                'version' => self::STABILITY_ENGINE_VERSION,
                'precision_tier' => $precision,
                'calculated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
            'birth_time_precision' => $precision,
            'interval' => [
                'start' => $interval['start']->toIso8601String(),
                'mid' => $interval['mid']->toIso8601String(),
                'end' => $interval['end']->toIso8601String(),
                'duration_seconds' => $interval['end']->getTimestamp() - $interval['start']->getTimestamp(),
                'timezone' => $timezone,
            ],
            'uncertainty_window' => [
                'start' => $interval['start']->toIso8601String(),
                'mid' => $interval['mid']->toIso8601String(),
                'end' => $interval['end']->toIso8601String(),
                'duration_seconds' => $interval['end']->getTimestamp() - $interval['start']->getTimestamp(),
                'timezone' => $timezone,
            ],
            'd1_ascendant' => $ascendantData['d1_ascendant'],
            'd9_ascendant' => $ascendantData['d9_ascendant'],
            'planetary_stability' => $planetaryData,
            'planetary_navamshas' => $planetaryData,
            'birth_panchanga' => [
                'sun_times' => $panchangaData['sun_times'],
                'tithi' => $panchangaData['tithi'],
                'paksha' => $panchangaData['paksha'],
                'vaara' => $panchangaData['vaara'],
                'nakshatra' => $panchangaData['nakshatra'],
                'pada' => $panchangaData['pada'],
                'yoga' => $panchangaData['yoga'],
                'karana' => $panchangaData['karana'],
            ],
            'panchanga' => [
                'sun_times' => $panchangaData['sun_times'],
                'tithi' => $panchangaData['tithi'],
                'paksha' => $panchangaData['paksha'],
                'vaara' => $panchangaData['vaara'],
                'nakshatra' => $panchangaData['nakshatra'],
                'pada' => $panchangaData['pada'],
                'yoga' => $panchangaData['yoga'],
                'karana' => $panchangaData['karana'],
            ],
            'vimshottari_dasha' => $dashaData,
            'vimshottari' => $dashaData,
            'summary' => $summary,
        ];
    }
}
