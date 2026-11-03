<?php

declare(strict_types=1);

namespace App\Services\Astrology;

use App\Contracts\Astrology\KundliCalculatorInterface;
use App\Models\UserAstrologyProfile;
use Carbon\CarbonImmutable;
use FFI;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Service for computing personalized Vedic astrology reference data and planetary transits.
 *
 * Implements M4B Stage 1 of the Astro Setu Product Roadmap.
 * Reuses verified True Chitra Paksha Lahiri ephemeris via PHP FFI + Moshier.
 */
class PersonalizedAstrologyService implements KundliCalculatorInterface
{
    public const RULESET_VERSION = 'M4B_V1';
    public const DEFAULT_AYANAMSHA = 'TRUE_CITRA_LAHIRI';

    /**
     * Standard approximate time windows mapped to local start and end times.
     */
    public const TIME_WINDOWS = [
        'early_morning' => ['04:00:00', '08:00:00'],
        'morning'       => ['08:00:00', '12:00:00'],
        'afternoon'     => ['12:00:00', '16:00:00'],
        'evening'       => ['16:00:00', '20:00:00'],
        'night'         => ['20:00:00', '23:59:59'],
        'late_night'    => ['00:00:00', '04:00:00'],
    ];

    /**
     * Navagraha celestial bodies for sidereal calculation.
     * Barycentric IDs used for outer planets per ephemeris standards.
     */
    public const BODIES = [
        'sun'     => ['name' => 'Sun', 'id' => JmeEphFFI::JME_BODY_SUN],
        'moon'    => ['name' => 'Moon', 'id' => JmeEphFFI::JME_BODY_MOON],
        'mars'    => ['name' => 'Mars', 'id' => JmeEphFFI::JME_BODY_MARS],
        'mercury' => ['name' => 'Mercury', 'id' => JmeEphFFI::JME_BODY_MERCURY],
        'jupiter' => ['name' => 'Jupiter', 'id' => JmeEphFFI::JME_BODY_JUPITER],
        'venus'   => ['name' => 'Venus', 'id' => JmeEphFFI::JME_BODY_VENUS],
        'saturn'  => ['name' => 'Saturn', 'id' => JmeEphFFI::JME_BODY_SATURN],
        'rahu'    => ['name' => 'Rahu', 'id' => JmeEphFFI::JME_BODY_MEAN_NODE],
        'ketu'    => ['name' => 'Ketu', 'id' => -999], // Derived 180° opposite Rahu
    ];

    protected JmeEphFFI $jme;
    protected AstronomyService $astronomy;

    protected CData $resultsBuffer;
    protected CData $errorBuffer;

    public function __construct(
        ?JmeEphFFI $jme = null,
        ?AstronomyService $astronomy = null
    ) {
        $this->jme = $jme ?? app(JmeEphFFI::class);
        $this->astronomy = $astronomy ?? app(AstronomyService::class);

        // Ensure True Chitra Lahiri is configured
        JmeRuntime::setTrueCitra($this->jme);

        $ffi = $this->jme->getFFI();
        $this->resultsBuffer = $ffi->new('double[6]');
        $this->errorBuffer = $ffi->new('char[256]');
    }

    /**
     * KundliCalculatorInterface implementation.
     */
    public function calculate(
        CarbonImmutable $birthDateTime,
        float $latitude,
        float $longitude,
        string $timezone,
        array $options = []
    ): array {
        $precision = (string) ($options['precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED);
        $targetDate = isset($options['target_date']) ? (string) $options['target_date'] : null;
        $approximateWindow = isset($options['approximate_time_window']) ? (string) $options['approximate_time_window'] : null;

        return $this->computePersonalizedInsights(
            birthDate: $birthDateTime->format('Y-m-d'),
            birthTime: $birthDateTime->format('H:i:s'),
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            targetDate: $targetDate
        );
    }

    /**
     * Compute full personalized insights from a UserAstrologyProfile model.
     */
    public function calculateForProfile(UserAstrologyProfile $profile, ?string $targetDate = null): array
    {
        return $this->computePersonalizedInsights(
            birthDate: $profile->date_of_birth->format('Y-m-d'),
            birthTime: $profile->birth_time,
            precision: (string) $profile->birth_time_precision,
            approximateWindow: $profile->approximate_time_window,
            latitude: (float) $profile->latitude,
            longitude: (float) $profile->longitude,
            timezone: (string) $profile->timezone,
            targetDate: $targetDate,
            profileMetadata: [
                'profile_id' => $profile->id,
                'name' => $profile->name,
                'relationship' => $profile->relationship,
                'birth_city' => $profile->birth_city,
            ]
        );
    }

    /**
     * Compute full personalized insights from a job/worker payload array.
     *
     * @param array<string, mixed> $payload
     */
    public function calculateFromPayload(array $payload): array
    {
        return $this->computePersonalizedInsights(
            birthDate: (string) $payload['date_of_birth'],
            birthTime: isset($payload['birth_time']) ? (string) $payload['birth_time'] : null,
            precision: (string) ($payload['birth_time_precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED),
            approximateWindow: isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : null,
            latitude: (float) $payload['latitude'],
            longitude: (float) $payload['longitude'],
            timezone: (string) ($payload['timezone'] ?? 'UTC'),
            targetDate: isset($payload['target_date']) ? (string) $payload['target_date'] : null,
            profileMetadata: [
                'profile_id' => $payload['profile_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
            ]
        );
    }

    /**
     * Core computation pipeline enforcing reliability rules and transit geometry.
     */
    public function computePersonalizedInsights(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        float $latitude,
        float $longitude,
        string $timezone,
        ?string $targetDate = null,
        array $profileMetadata = []
    ): array {
        // Enforce True Citra Lahiri
        JmeRuntime::setTrueCitra($this->jme);

        $nowInTimezone = CarbonImmutable::now($timezone);
        $transitDateStr = $targetDate ?: $nowInTimezone->format('Y-m-d');
        $transitDateTime = CarbonImmutable::parse("{$transitDateStr} 12:00:00", $timezone);

        // 1. Resolve birth-time evaluation interval
        $interval = $this->resolveEvaluationInterval(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            timezone: $timezone
        );

        // 2. Evaluate natal reference positions & reliability
        $natal = $this->evaluateNatalReference(
            interval: $interval,
            precision: $precision,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone
        );

        // 3. Calculate current planetary positions & transits
        $transits = $this->calculateCurrentTransits(
            transitDateTime: $transitDateTime,
            natalMoonRashiIndex: $natal['moon_sign']['status'] === 'confirmed'
                ? $natal['moon_sign']['index']
                : null,
            natalMoonPossibleIndices: $natal['moon_sign']['possible_indices'] ?? []
        );

        // 4. Calculate ayanamsha at target date
        $transitJd = $this->toJulianDay($transitDateTime);
        $ayanamshaDeg = $this->jme->jme_get_ayanamsa_ut($transitJd);

        return [
            'metadata' => [
                'ruleset_version' => self::RULESET_VERSION,
                'engine_version' => 'JME_2.0.0_MOSHIER',
                'ayanamsha' => [
                    'name' => 'True Chitra Paksha (Lahiri)',
                    'code' => self::DEFAULT_AYANAMSHA,
                    'value_degrees' => round($ayanamshaDeg, 6),
                ],
                'birth_time_precision' => $precision,
                'target_date' => $transitDateStr,
                'calculation_timestamp' => now()->toIso8601String(),
                'timezone' => $timezone,
                'profile' => $profileMetadata,
                'evaluation_interval' => [
                    'start' => $interval['start']->toIso8601String(),
                    'mid' => $interval['mid']->toIso8601String(),
                    'end' => $interval['end']->toIso8601String(),
                ],
            ],
            'birth_chart_reference' => $natal,
            'current_transits' => $transits,
        ];
    }

    /**
     * Resolve the exact time interval to evaluate based on birth time reliability rules.
     *
     * @return array{start: CarbonImmutable, mid: CarbonImmutable, end: CarbonImmutable}
     */
    public function resolveEvaluationInterval(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        string $timezone
    ): array {
        if ($precision === UserAstrologyProfile::PRECISION_VERIFIED && !empty($birthTime)) {
            $exact = CarbonImmutable::parse("{$birthDate} {$birthTime}", $timezone);
            return [
                'start' => $exact,
                'mid'   => $exact,
                'end'   => $exact,
            ];
        }

        if ($precision === UserAstrologyProfile::PRECISION_APPROXIMATE) {
            $windowKey = strtolower(trim((string) $approximateWindow));
            if (isset(self::TIME_WINDOWS[$windowKey])) {
                [$startTime, $endTime] = self::TIME_WINDOWS[$windowKey];
                $start = CarbonImmutable::parse("{$birthDate} {$startTime}", $timezone);
                $end = CarbonImmutable::parse("{$birthDate} {$endTime}", $timezone);
            } elseif (!empty($birthTime)) {
                // Approximate time given (e.g. "around 14:00") -> evaluate ±1 hour window
                $center = CarbonImmutable::parse("{$birthDate} {$birthTime}", $timezone);
                $start = $center->subHour();
                $end = $center->addHour();
            } else {
                // Approximate with no window specified -> evaluate full day
                $start = CarbonImmutable::parse("{$birthDate} 00:00:00", $timezone);
                $end = CarbonImmutable::parse("{$birthDate} 23:59:59", $timezone);
            }

            $midSeconds = (int) round(($start->getTimestamp() + $end->getTimestamp()) / 2);
            $mid = CarbonImmutable::createFromTimestamp($midSeconds, $timezone);

            return [
                'start' => $start,
                'mid'   => $mid,
                'end'   => $end,
            ];
        }

        // Unknown precision: evaluate across the complete local birth date (00:00:00 to 23:59:59)
        $start = CarbonImmutable::parse("{$birthDate} 00:00:00", $timezone);
        $end = CarbonImmutable::parse("{$birthDate} 23:59:59", $timezone);
        $mid = CarbonImmutable::parse("{$birthDate} 12:00:00", $timezone);

        return [
            'start' => $start,
            'mid'   => $mid,
            'end'   => $end,
        ];
    }

    /**
     * Evaluate natal positions across the interval and apply stability rules.
     */
    protected function evaluateNatalReference(
        array $interval,
        string $precision,
        float $latitude,
        float $longitude,
        string $timezone
    ): array {
        $startJd = $this->toJulianDay($interval['start']);
        $midJd = $this->toJulianDay($interval['mid']);
        $endJd = $this->toJulianDay($interval['end']);

        // 1. Calculate Moon at interval start, mid, end
        $moonStart = $this->calculatePlanetAtJd($startJd, JmeEphFFI::JME_BODY_MOON);
        $moonMid = $this->calculatePlanetAtJd($midJd, JmeEphFFI::JME_BODY_MOON);
        $moonEnd = $this->calculatePlanetAtJd($endJd, JmeEphFFI::JME_BODY_MOON);

        $moonRashiStart = ZodiacDivision::rashiIndex($moonStart['longitude']);
        $moonRashiMid = ZodiacDivision::rashiIndex($moonMid['longitude']);
        $moonRashiEnd = ZodiacDivision::rashiIndex($moonEnd['longitude']);

        $moonNakStart = ZodiacDivision::nakshatraIndex($moonStart['longitude']);
        $moonNakMid = ZodiacDivision::nakshatraIndex($moonMid['longitude']);
        $moonNakEnd = ZodiacDivision::nakshatraIndex($moonEnd['longitude']);

        $moonPadaStart = ZodiacDivision::nakshatraPada($moonStart['longitude']);
        $moonPadaEnd = ZodiacDivision::nakshatraPada($moonEnd['longitude']);

        // Moon Sign (Janma Rashi) Stability Check
        $isMoonRashiStable = ($moonRashiStart === $moonRashiEnd);
        $moonSignStatus = ($precision === UserAstrologyProfile::PRECISION_VERIFIED || $isMoonRashiStable)
            ? 'confirmed'
            : 'uncertain';

        $moonRashiEnum = Rasi::from($moonRashiMid);

        $possibleMoonSigns = [];
        $possibleMoonIndices = [];
        if (!$isMoonRashiStable) {
            $possibleMoonIndices = array_values(array_unique([$moonRashiStart, $moonRashiEnd]));
            foreach ($possibleMoonIndices as $idx) {
                $possibleMoonSigns[] = [
                    'index' => $idx,
                    'name' => Rasi::from($idx)->getEnglishName(),
                    'vedic_name' => Rasi::from($idx)->getName(),
                ];
            }
        }

        // Moon Nakshatra Stability Check
        $isMoonNakStable = ($moonNakStart === $moonNakEnd);
        $moonNakStatus = ($precision === UserAstrologyProfile::PRECISION_VERIFIED || $isMoonNakStable)
            ? 'confirmed'
            : 'uncertain';

        $moonNakEnum = Nakshatra::from($moonNakMid);

        $possibleNakshatras = [];
        if (!$isMoonNakStable) {
            $nakIndices = array_values(array_unique([$moonNakStart, $moonNakEnd]));
            foreach ($nakIndices as $idx) {
                $possibleNakshatras[] = [
                    'index' => $idx,
                    'name' => Nakshatra::from($idx)->name,
                    'lord' => Nakshatra::from($idx)->getRulingPlanet(),
                ];
            }
        }

        // Moon Pada Stability Check
        $isMoonPadaStable = ($isMoonNakStable && $moonPadaStart === $moonPadaEnd);
        $moonPadaStatus = ($precision === UserAstrologyProfile::PRECISION_VERIFIED || $isMoonPadaStable)
            ? 'confirmed'
            : 'uncertain';

        // 2. Sun Sign Stability Check
        $sunStart = $this->calculatePlanetAtJd($startJd, JmeEphFFI::JME_BODY_SUN);
        $sunEnd = $this->calculatePlanetAtJd($endJd, JmeEphFFI::JME_BODY_SUN);
        $sunMid = $this->calculatePlanetAtJd($midJd, JmeEphFFI::JME_BODY_SUN);

        $sunRashiStart = ZodiacDivision::rashiIndex($sunStart['longitude']);
        $sunRashiEnd = ZodiacDivision::rashiIndex($sunEnd['longitude']);
        $isSunRashiStable = ($sunRashiStart === $sunRashiEnd);

        $sunSignStatus = ($precision === UserAstrologyProfile::PRECISION_VERIFIED || $isSunRashiStable)
            ? 'confirmed'
            : 'uncertain';

        $sunRashiEnum = Rasi::from($sunMid['rashi_index']);

        // 3. Ascendant (Lagna) Evaluation
        $ascendantData = $this->evaluateAscendant(
            interval: $interval,
            precision: $precision,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone
        );

        // 4. Calculate All 9 Natal Planetary Positions at midpoint (or exact time)
        $natalPlanets = [];
        foreach (self::BODIES as $key => $body) {
            if ($key === 'ketu') {
                $rahuLon = $natalPlanets['rahu']['longitude'] ?? 0.0;
                $ketuLon = AstroCore::normalize($rahuLon + 180.0);
                $rashiIdx = ZodiacDivision::rashiIndex($ketuLon);
                $natalPlanets['ketu'] = [
                    'planet' => 'Ketu',
                    'longitude' => round($ketuLon, 4),
                    'rashi_index' => $rashiIdx,
                    'rashi_name' => Rasi::from($rashiIdx)->getEnglishName(),
                    'rashi_vedic_name' => Rasi::from($rashiIdx)->getName(),
                    'degree_in_rashi' => round(fmod($ketuLon, 30.0), 4),
                    'is_retrograde' => true,
                    'speed' => $natalPlanets['rahu']['speed'] ?? 0.0,
                ];
                continue;
            }

            $pos = $this->calculatePlanetAtJd($midJd, $body['id']);
            $natalPlanets[$key] = [
                'planet' => $body['name'],
                'longitude' => round($pos['longitude'], 4),
                'rashi_index' => $pos['rashi_index'],
                'rashi_name' => Rasi::from($pos['rashi_index'])->getEnglishName(),
                'rashi_vedic_name' => Rasi::from($pos['rashi_index'])->getName(),
                'degree_in_rashi' => round($pos['degree_in_rashi'], 4),
                'is_retrograde' => $pos['is_retrograde'],
                'speed' => round($pos['speed'], 4),
            ];
        }

        return [
            'sun_sign' => [
                'id' => $sunRashiEnum->value + 1,
                'index' => $sunRashiEnum->value,
                'name' => $sunRashiEnum->getEnglishName(),
                'vedic_name' => $sunRashiEnum->getName(),
                'longitude' => round($sunMid['longitude'], 4),
                'degree_in_rashi' => round(fmod($sunMid['longitude'], 30.0), 4),
                'status' => $sunSignStatus,
            ],
            'moon_sign' => [
                'id' => $moonRashiEnum->value + 1,
                'index' => $moonRashiEnum->value,
                'name' => $moonRashiEnum->getEnglishName(),
                'vedic_name' => $moonRashiEnum->getName(),
                'longitude' => round($moonMid['longitude'], 4),
                'degree_in_rashi' => round(fmod($moonMid['longitude'], 30.0), 4),
                'status' => $moonSignStatus,
                'is_stable_across_interval' => $isMoonRashiStable,
                'possible_signs' => $possibleMoonSigns,
                'possible_indices' => $possibleMoonIndices,
            ],
            'nakshatra' => [
                'id' => $moonNakEnum->value + 1,
                'index' => $moonNakEnum->value,
                'name' => $moonNakEnum->name,
                'sanskrit_name' => $moonNakEnum->getName(),
                'pada' => ZodiacDivision::nakshatraPada($moonMid['longitude']),
                'lord' => $moonNakEnum->getRulingPlanet(),
                'status' => $moonNakStatus,
                'pada_status' => $moonPadaStatus,
                'possible_nakshatras' => $possibleNakshatras,
            ],
            'ascendant' => $ascendantData,
            'planetary_positions' => $natalPlanets,
        ];
    }

    /**
     * Evaluate Ascendant (Lagna) adhering to strict reliability rules:
     * - Unknown birth time: strictly UNAVAILABLE (null).
     * - Approximate birth time: SUPPRESSED unless Ascendant remains identical across the window.
     * - Verified birth time: CONFIRMED.
     */
    protected function evaluateAscendant(
        array $interval,
        string $precision,
        float $latitude,
        float $longitude,
        string $timezone
    ): array {
        if ($precision === UserAstrologyProfile::PRECISION_UNKNOWN) {
            return [
                'status' => 'unavailable',
                'id' => null,
                'index' => null,
                'name' => null,
                'vedic_name' => null,
                'longitude' => null,
                'degree_in_rashi' => null,
                'reason' => 'Ascendant unavailable: birth time is unknown. Exact time is required for Lagna calculation.',
            ];
        }

        if ($precision === UserAstrologyProfile::PRECISION_APPROXIMATE) {
            // Ascendant shifts ~1 degree every 4 minutes (changes sign every ~2 hours).
            // Evaluate Ascendant at start and end of the approximate window.
            $ascStart = $this->calculateAscendantAtCarbon($interval['start'], $latitude, $longitude, $timezone);
            $ascEnd = $this->calculateAscendantAtCarbon($interval['end'], $latitude, $longitude, $timezone);

            $ascRashiStart = ZodiacDivision::rashiIndex($ascStart);
            $ascRashiEnd = ZodiacDivision::rashiIndex($ascEnd);

            if ($ascRashiStart !== $ascRashiEnd) {
                return [
                    'status' => 'uncertain',
                    'id' => null,
                    'index' => null,
                    'name' => null,
                    'vedic_name' => null,
                    'longitude' => null,
                    'degree_in_rashi' => null,
                    'reason' => 'Ascendant suppressed: approximate birth-time window spans multiple signs (' .
                        Rasi::from($ascRashiStart)->getEnglishName() . ' to ' . Rasi::from($ascRashiEnd)->getEnglishName() . ').',
                    'possible_signs' => [
                        Rasi::from($ascRashiStart)->getEnglishName(),
                        Rasi::from($ascRashiEnd)->getEnglishName(),
                    ],
                ];
            }

            // In rare narrow windows (< 15 mins) where Ascendant didn't cross a boundary
            $ascMid = $this->calculateAscendantAtCarbon($interval['mid'], $latitude, $longitude, $timezone);
            $rashiEnum = Rasi::from($ascRashiStart);

            return [
                'status' => 'confirmed',
                'id' => $rashiEnum->value + 1,
                'index' => $rashiEnum->value,
                'name' => $rashiEnum->getEnglishName(),
                'vedic_name' => $rashiEnum->getName(),
                'longitude' => round($ascMid, 4),
                'degree_in_rashi' => round(fmod($ascMid, 30.0), 4),
                'reason' => 'Ascendant verified: remains stable across narrow approximate window.',
            ];
        }

        // Verified precision: compute Ascendant at exact birth time
        $ascDeg = $this->calculateAscendantAtCarbon($interval['mid'], $latitude, $longitude, $timezone);
        $rashiIdx = ZodiacDivision::rashiIndex($ascDeg);
        $rashiEnum = Rasi::from($rashiIdx);

        return [
            'status' => 'confirmed',
            'id' => $rashiEnum->value + 1,
            'index' => $rashiEnum->value,
            'name' => $rashiEnum->getEnglishName(),
            'vedic_name' => $rashiEnum->getName(),
            'longitude' => round($ascDeg, 4),
            'degree_in_rashi' => round(fmod($ascDeg, 30.0), 4),
            'reason' => 'Calculated from verified birth time.',
        ];
    }

    /**
     * Calculate current planetary transits and Gochara house positions relative to natal Moon sign.
     *
     * @param int|null $natalMoonRashiIndex Confirmed natal Moon Rashi index (0..11)
     * @param array<int> $natalMoonPossibleIndices Possible Moon Rashi indices if uncertain
     */
    protected function calculateCurrentTransits(
        CarbonImmutable $transitDateTime,
        ?int $natalMoonRashiIndex,
        array $natalMoonPossibleIndices = []
    ): array {
        $transitJd = $this->toJulianDay($transitDateTime);

        $planets = [];
        foreach (self::BODIES as $key => $body) {
            if ($key === 'ketu') {
                $rahuLon = $planets['rahu']['longitude'] ?? 0.0;
                $ketuLon = AstroCore::normalize($rahuLon + 180.0);
                $rashiIdx = ZodiacDivision::rashiIndex($ketuLon);
                $nakIdx = ZodiacDivision::nakshatraIndex($ketuLon);
                $nakEnum = Nakshatra::from($nakIdx);

                $gochara = $this->calculateGocharaHouse(
                    transitingRashiIndex: $rashiIdx,
                    natalMoonRashiIndex: $natalMoonRashiIndex,
                    natalMoonPossibleIndices: $natalMoonPossibleIndices
                );

                $planets['ketu'] = [
                    'planet' => 'Ketu',
                    'key' => 'ketu',
                    'longitude' => round($ketuLon, 4),
                    'rashi' => [
                        'id' => $rashiIdx + 1,
                        'index' => $rashiIdx,
                        'name' => Rasi::from($rashiIdx)->getEnglishName(),
                        'vedic_name' => Rasi::from($rashiIdx)->getName(),
                        'degrees' => round(fmod($ketuLon, 30.0), 4),
                    ],
                    'nakshatra' => [
                        'id' => $nakEnum->value + 1,
                        'index' => $nakEnum->value,
                        'name' => $nakEnum->name,
                        'pada' => ZodiacDivision::nakshatraPada($ketuLon),
                        'lord' => $nakEnum->getRulingPlanet(),
                    ],
                    'speed' => $planets['rahu']['speed'] ?? 0.0,
                    'is_retrograde' => true,
                    'gochara_house' => $gochara['house'],
                    'gochara_status' => $gochara['status'],
                    'gochara_possible_houses' => $gochara['possible_houses'],
                ];
                continue;
            }

            $pos = $this->calculatePlanetAtJd($transitJd, $body['id']);
            $nakIdx = ZodiacDivision::nakshatraIndex($pos['longitude']);
            $nakEnum = Nakshatra::from($nakIdx);

            $gochara = $this->calculateGocharaHouse(
                transitingRashiIndex: $pos['rashi_index'],
                natalMoonRashiIndex: $natalMoonRashiIndex,
                natalMoonPossibleIndices: $natalMoonPossibleIndices
            );

            $planets[$key] = [
                'planet' => $body['name'],
                'key' => $key,
                'longitude' => round($pos['longitude'], 4),
                'rashi' => [
                    'id' => $pos['rashi_index'] + 1,
                    'index' => $pos['rashi_index'],
                    'name' => Rasi::from($pos['rashi_index'])->getEnglishName(),
                    'vedic_name' => Rasi::from($pos['rashi_index'])->getName(),
                    'degrees' => round($pos['degree_in_rashi'], 4),
                ],
                'nakshatra' => [
                    'id' => $nakEnum->value + 1,
                    'index' => $nakEnum->value,
                    'name' => $nakEnum->name,
                    'pada' => ZodiacDivision::nakshatraPada($pos['longitude']),
                    'lord' => $nakEnum->getRulingPlanet(),
                ],
                'speed' => round($pos['speed'], 4),
                'is_retrograde' => $pos['is_retrograde'],
                'gochara_house' => $gochara['house'],
                'gochara_status' => $gochara['status'],
                'gochara_possible_houses' => $gochara['possible_houses'],
            ];
        }

        return [
            'transit_date' => $transitDateTime->format('Y-m-d'),
            'reference_time' => '12:00:00',
            'planets' => $planets,
        ];
    }

    /**
     * Calculate Gochara house (1..12) of a transiting planet relative to natal Janma Rashi.
     * Formula: ((transiting_rashi - natal_moon_rashi + 12) % 12) + 1
     *
     * @return array{house: int|null, status: string, possible_houses: array<int>}
     */
    public function calculateGocharaHouse(
        int $transitingRashiIndex,
        ?int $natalMoonRashiIndex,
        array $natalMoonPossibleIndices = []
    ): array {
        if ($natalMoonRashiIndex !== null) {
            $house = (($transitingRashiIndex - $natalMoonRashiIndex + 12) % 12) + 1;
            return [
                'house' => $house,
                'status' => 'confirmed',
                'possible_houses' => [$house],
            ];
        }

        if (!empty($natalMoonPossibleIndices)) {
            $possible = [];
            foreach ($natalMoonPossibleIndices as $idx) {
                $possible[] = (($transitingRashiIndex - $idx + 12) % 12) + 1;
            }
            return [
                'house' => null,
                'status' => 'uncertain',
                'possible_houses' => array_values(array_unique($possible)),
            ];
        }

        return [
            'house' => null,
            'status' => 'unavailable',
            'possible_houses' => [],
        ];
    }

    /**
     * Compute planet position and speed at exact Julian Day.
     *
     * @return array{longitude: float, rashi_index: int, degree_in_rashi: float, speed: float, is_retrograde: bool}
     */
    protected function calculatePlanetAtJd(float $jd, int $bodyId): array
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION
            | JmeEphFFI::JME_CALC_SIDEREAL
            | JmeEphFFI::JME_CALC_SPEED;

        $rc = $this->jme->jme_calc_ut($jd, $bodyId, $flags, $this->resultsBuffer, $this->errorBuffer);
        if ($rc < 0) {
            throw new RuntimeException("jme_calc_ut failed for body {$bodyId} at JD {$jd}: " . FFI::string($this->errorBuffer));
        }

        $longitude = AstroCore::normalize((float) $this->resultsBuffer[0]);
        $speed = (float) $this->resultsBuffer[3];
        $rashiIdx = ZodiacDivision::rashiIndex($longitude);
        $degInRashi = fmod($longitude, 30.0);

        return [
            'longitude' => $longitude,
            'rashi_index' => $rashiIdx,
            'degree_in_rashi' => $degInRashi,
            'speed' => $speed,
            'is_retrograde' => $speed < 0,
        ];
    }

    /**
     * Compute Ascendant (Lagna) degrees at a given Carbon timestamp and location.
     */
    protected function calculateAscendantAtCarbon(
        CarbonImmutable $dateTime,
        float $latitude,
        float $longitude,
        string $timezone
    ): float {
        return $this->astronomy->getAscendant([
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
     * Convert CarbonImmutable into Julian Day (UTC).
     */
    protected function toJulianDay(CarbonImmutable $dateTime): float
    {
        return $this->astronomy->toJulianDayUtc([
            'year' => (int) $dateTime->format('Y'),
            'month' => (int) $dateTime->format('m'),
            'day' => (int) $dateTime->format('d'),
            'hour' => (int) $dateTime->format('H'),
            'minute' => (int) $dateTime->format('i'),
            'second' => (int) $dateTime->format('s'),
            'timezone' => $dateTime->getTimezone()->getName(),
        ]);
    }
}
