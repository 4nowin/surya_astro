<?php

declare(strict_types=1);

namespace App\Services\Astrology;

use App\Contracts\Astrology\KundliCalculatorInterface;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\DashaService;
use App\Services\Astrology\Interpretation\KundliInterpretationService;
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
 * Service for computing personal Vedic birth charts (Janam Kundli) and planetary longitudes.
 *
 * Implements M5 of the Astro Setu Product Roadmap.
 * Reuses validated True Chitra Paksha Lahiri ephemeris via PHP FFI + Moshier.
 */
class KundliService implements KundliCalculatorInterface
{
    public const RULESET_VERSION = 'M5_V1';
    public const DEFAULT_AYANAMSHA = 'TRUE_CITRA_LAHIRI';
    public const DEFAULT_HOUSE_SYSTEM = 'WHOLE_SIGN_RASHI_BHAVA';

    /**
     * Classical combustion angular degree thresholds relative to the Sun.
     */
    public const COMBUSTION_THRESHOLDS = [
        'moon'    => 12.0,
        'mars'    => 17.0,
        'mercury' => 14.0, // 12° when retrograde
        'jupiter' => 11.0,
        'venus'   => 10.0, // 8° when retrograde
        'saturn'  => 15.0,
    ];

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
     */
    public const BODIES = [
        'sun'     => ['name' => 'Sun',     'name_hi' => 'सूर्य',    'abbr' => 'Su', 'abbr_hi' => 'सू', 'id' => JmeEphFFI::JME_BODY_SUN],
        'moon'    => ['name' => 'Moon',    'name_hi' => 'चन्द्र',   'abbr' => 'Mo', 'abbr_hi' => 'चं', 'id' => JmeEphFFI::JME_BODY_MOON],
        'mars'    => ['name' => 'Mars',    'name_hi' => 'मंगल',    'abbr' => 'Ma', 'abbr_hi' => 'मं', 'id' => JmeEphFFI::JME_BODY_MARS],
        'mercury' => ['name' => 'Mercury', 'name_hi' => 'बुध',     'abbr' => 'Me', 'abbr_hi' => 'बु', 'id' => JmeEphFFI::JME_BODY_MERCURY],
        'jupiter' => ['name' => 'Jupiter', 'name_hi' => 'बृहस्पति', 'abbr' => 'Ju', 'abbr_hi' => 'गु', 'id' => JmeEphFFI::JME_BODY_JUPITER],
        'venus'   => ['name' => 'Venus',   'name_hi' => 'शुक्र',    'abbr' => 'Ve', 'abbr_hi' => 'शु', 'id' => JmeEphFFI::JME_BODY_VENUS],
        'saturn'  => ['name' => 'Saturn',  'name_hi' => 'शनि',     'abbr' => 'Sa', 'abbr_hi' => 'श',  'id' => JmeEphFFI::JME_BODY_SATURN],
        'rahu'    => ['name' => 'Rahu',    'name_hi' => 'राहु',    'abbr' => 'Ra', 'abbr_hi' => 'रा', 'id' => JmeEphFFI::JME_BODY_MEAN_NODE],
        'ketu'    => ['name' => 'Ketu',    'name_hi' => 'केतु',    'abbr' => 'Ke', 'abbr_hi' => 'के', 'id' => -999], // Derived 180° opposite Rahu
    ];

    /**
     * Classical planetary exaltation and debilitation signs (1-based Rashi index, 1=Aries..12=Pisces).
     */
    public const PLANET_DIGNITY_RULES = [
        'sun'     => ['exalted' => 1,  'debilitated' => 7,  'own' => [5]],
        'moon'    => ['exalted' => 2,  'debilitated' => 8,  'own' => [4]],
        'mars'    => ['exalted' => 10, 'debilitated' => 4,  'own' => [1, 8]],
        'mercury' => ['exalted' => 6,  'debilitated' => 12, 'own' => [3, 6]],
        'jupiter' => ['exalted' => 4,  'debilitated' => 10, 'own' => [9, 12]],
        'venus'   => ['exalted' => 12, 'debilitated' => 6,  'own' => [2, 7]],
        'saturn'  => ['exalted' => 7,  'debilitated' => 1,  'own' => [10, 11]],
        'rahu'    => ['exalted' => 2,  'debilitated' => 8,  'own' => [11]], // Classical Parashara representation
        'ketu'    => ['exalted' => 8,  'debilitated' => 2,  'own' => [8]],
    ];

    protected JmeEphFFI $jme;
    protected AstronomyService $astronomy;
    protected DashaService $dashaService;
    protected KundliInterpretationService $interpretationService;

    protected CData $resultsBuffer;
    protected CData $errorBuffer;

    public function __construct(
        ?JmeEphFFI $jme = null,
        ?AstronomyService $astronomy = null,
        ?DashaService $dashaService = null,
        ?KundliInterpretationService $interpretationService = null
    ) {
        $this->jme = $jme ?? app(JmeEphFFI::class);
        $this->astronomy = $astronomy ?? app(AstronomyService::class);
        $this->dashaService = $dashaService ?? app(DashaService::class);
        $this->interpretationService = $interpretationService ?? app(KundliInterpretationService::class);

        // Enforce True Chitra Lahiri
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
        $approximateWindow = isset($options['approximate_time_window']) ? (string) $options['approximate_time_window'] : null;

        return $this->computeKundli(
            birthDate: $birthDateTime->format('Y-m-d'),
            birthTime: $birthDateTime->format('H:i:s'),
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            profileMetadata: $options['profile_metadata'] ?? []
        );
    }

    /**
     * Compute Kundli from a UserAstrologyProfile model.
     */
    public function calculateForProfile(UserAstrologyProfile $profile): array
    {
        return $this->computeKundli(
            birthDate: $profile->date_of_birth->format('Y-m-d'),
            birthTime: $profile->birth_time,
            precision: (string) $profile->birth_time_precision,
            approximateWindow: $profile->approximate_time_window,
            latitude: (float) $profile->latitude,
            longitude: (float) $profile->longitude,
            timezone: (string) $profile->timezone,
            profileMetadata: [
                'profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'name' => $profile->name,
                'relationship' => $profile->relationship,
                'birth_city' => $profile->birth_city,
            ]
        );
    }

    /**
     * Compute Kundli from a calculation job payload.
     *
     * @param array<string, mixed> $payload
     */
    public function calculateFromPayload(array $payload): array
    {
        return $this->computeKundli(
            birthDate: (string) $payload['date_of_birth'],
            birthTime: isset($payload['birth_time']) ? (string) $payload['birth_time'] : null,
            precision: (string) ($payload['birth_time_precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED),
            approximateWindow: isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : null,
            latitude: (float) $payload['latitude'],
            longitude: (float) $payload['longitude'],
            timezone: (string) ($payload['timezone'] ?? 'UTC'),
            profileMetadata: [
                'profile_id' => $payload['profile_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'name' => $payload['name'] ?? null,
                'relationship' => $payload['relationship'] ?? null,
                'birth_city' => $payload['birth_city'] ?? null,
            ]
        );
    }

    /**
     * Core computation pipeline for Janam Kundli.
     */
    public function computeKundli(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        float $latitude,
        float $longitude,
        string $timezone,
        array $profileMetadata = []
    ): array {
        // Enforce True Citra Lahiri
        JmeRuntime::setTrueCitra($this->jme);

        // 1. Resolve birth interval and midpoint
        $interval = $this->resolveBirthInterval($birthDate, $birthTime, $precision, $approximateWindow, $timezone);
        $midJd = $this->toJulianDay($interval['mid']);

        // 2. Evaluate Ascendant (Lagna) adhering to precision safeguards
        $ascendantData = $this->evaluateAscendant(
            interval: $interval,
            precision: $precision,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone
        );

        // 3. Compute Natal Planets
        $natalPlanets = $this->calculateNatalPlanets($midJd);

        // 4. Calculate Combustion based on angular distance from Sun
        $sunLon = $natalPlanets['sun']['longitude'];
        $natalPlanets = $this->applyCombustion($natalPlanets, $sunLon);

        // 5. Build Houses (Equal Rashi Bhava / Whole Sign)
        $lagnaRashiId = $ascendantData['id']; // 1..12 or null
        $lagnaHouses = null;
        if ($ascendantData['status'] === 'confirmed' && $lagnaRashiId !== null) {
            $lagnaHouses = $this->buildHouses(
                firstHouseRashiId: $lagnaRashiId,
                planets: $natalPlanets,
                ascendantData: $ascendantData
            );
        }

        // 6. Build Chandra Kundli (Moon Chart) where Moon sign = House 1
        $moonRashiId = $natalPlanets['moon']['rashi']['id'];
        $chandraHouses = $this->buildHouses(
            firstHouseRashiId: $moonRashiId,
            planets: $natalPlanets,
            ascendantData: null,
            chartType: 'chandra'
        );

        // 7. Calculate Vimshottari Dasha
        $moonLon = $natalPlanets['moon']['longitude'] ?? null;
        $birthDateTimeString = ($birthTime && $precision === UserAstrologyProfile::PRECISION_VERIFIED)
            ? "{$birthDate} {$birthTime}"
            : "{$birthDate} 12:00:00";
        $dashaData = $this->dashaService->calculateVimshottariTimeline(
            moonLongitude: $moonLon,
            birthDate: $birthDateTimeString,
            precision: $precision
        );

        // 8. Generate rule-based classical interpretations
        $rawChartData = [
            'planets' => $natalPlanets,
            'houses' => $lagnaHouses,
            'ascendant' => $ascendantData,
            'chandra_kundli' => [
                'houses' => $chandraHouses,
            ],
        ];
        $interpretationsEn = $this->interpretationService->interpretChart($rawChartData, 'en');
        $interpretationsHi = $this->interpretationService->interpretChart($rawChartData, 'hi');

        // 9. Assemble Complete Kundli Structure (M5 & M6)
        return [
            'metadata' => [
                'engine' => 'Standalone Moshier FFI (glibc 2.28)',
                'ayanamsha' => self::DEFAULT_AYANAMSHA,
                'house_system' => self::DEFAULT_HOUSE_SYSTEM,
                'ruleset' => self::RULESET_VERSION,
                'precision' => $precision,
                'calculated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'cached' => false,
            ],
            'profile' => [
                'id' => $profileMetadata['profile_id'] ?? null,
                'name' => $profileMetadata['name'] ?? null,
                'relationship' => $profileMetadata['relationship'] ?? null,
                'date_of_birth' => $birthDate,
                'birth_time' => $birthTime,
                'precision' => $precision,
                'approximate_time_window' => $approximateWindow,
                'birth_city' => $profileMetadata['birth_city'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $timezone,
            ],
            'ascendant' => $ascendantData,
            'planets' => $natalPlanets,
            'houses' => $lagnaHouses,
            'chandra_kundli' => [
                'status' => 'available',
                'moon_sign_id' => $moonRashiId,
                'moon_sign_name' => $natalPlanets['moon']['rashi']['name'],
                'moon_sign_vedic_name' => $natalPlanets['moon']['rashi']['vedic_name'],
                'houses' => $chandraHouses,
            ],
            'chart_availability' => [
                'lagna_chart_available' => ($ascendantData['status'] === 'confirmed'),
                'chandra_chart_available' => true,
                'advisory' => $ascendantData['reason'],
            ],
            'dasha' => $dashaData,
            'interpretations' => [
                'en' => $interpretationsEn,
                'hi' => $interpretationsHi,
            ],
        ];
    }

    /**
     * Resolve the birth interval (start, midpoint, end) based on precision tier.
     */
    protected function resolveBirthInterval(
        string $birthDate,
        ?string $birthTime,
        string $precision,
        ?string $approximateWindow,
        string $timezone
    ): array {
        if ($precision === UserAstrologyProfile::PRECISION_VERIFIED) {
            $timeStr = $birthTime ?: '12:00:00';
            $exact = CarbonImmutable::parse("{$birthDate} {$timeStr}", $timezone);
            return ['start' => $exact, 'mid' => $exact, 'end' => $exact];
        }

        if ($precision === UserAstrologyProfile::PRECISION_APPROXIMATE) {
            $window = self::TIME_WINDOWS[$approximateWindow ?? 'morning'] ?? self::TIME_WINDOWS['morning'];
            $start = CarbonImmutable::parse("{$birthDate} {$window[0]}", $timezone);
            $end = CarbonImmutable::parse("{$birthDate} {$window[1]}", $timezone);
            $midSec = ($start->getTimestamp() + $end->getTimestamp()) / 2;
            $mid = CarbonImmutable::createFromTimestamp((int) $midSec, $timezone);

            return ['start' => $start, 'mid' => $mid, 'end' => $end];
        }

        // Unknown precision: full 24-hour day interval
        $start = CarbonImmutable::parse("{$birthDate} 00:00:00", $timezone);
        $end = CarbonImmutable::parse("{$birthDate} 23:59:59", $timezone);
        $mid = CarbonImmutable::parse("{$birthDate} 12:00:00", $timezone);

        return ['start' => $start, 'mid' => $mid, 'end' => $end];
    }

    /**
     * Evaluate Ascendant (Lagna) adhering to strict reliability rules.
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
                'degrees' => null,
                'degrees_dms' => null,
                'nakshatra' => null,
                'reason' => 'Ascendant unavailable: birth time is unknown. Exact time is required for Lagna calculation.',
            ];
        }

        if ($precision === UserAstrologyProfile::PRECISION_APPROXIMATE) {
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
                    'degrees' => null,
                    'degrees_dms' => null,
                    'nakshatra' => null,
                    'reason' => 'Ascendant suppressed: approximate birth-time window spans multiple signs (' .
                        Rasi::from($ascRashiStart)->getEnglishName() . ' to ' . Rasi::from($ascRashiEnd)->getEnglishName() . ').',
                    'possible_signs' => [
                        Rasi::from($ascRashiStart)->getEnglishName(),
                        Rasi::from($ascRashiEnd)->getEnglishName(),
                    ],
                ];
            }

            // Window is narrow enough that Ascendant didn't cross a sign boundary
            $ascMid = $this->calculateAscendantAtCarbon($interval['mid'], $latitude, $longitude, $timezone);
            $rashiEnum = Rasi::from($ascRashiStart);
            $nakIdx = ZodiacDivision::nakshatraIndex($ascMid);
            $nakEnum = Nakshatra::from($nakIdx);
            $degInRashi = fmod($ascMid, 30.0);

            return [
                'status' => 'confirmed',
                'id' => $rashiEnum->value + 1,
                'index' => $rashiEnum->value,
                'name' => $rashiEnum->getEnglishName(),
                'vedic_name' => $rashiEnum->getName(),
                'longitude' => round($ascMid, 4),
                'degrees' => round($degInRashi, 4),
                'degrees_dms' => $this->formatDms($degInRashi),
                'nakshatra' => [
                    'id' => $nakEnum->value + 1,
                    'index' => $nakEnum->value,
                    'name' => $nakEnum->name,
                    'vedic_name' => $nakEnum->getName(),
                    'pada' => ZodiacDivision::nakshatraPada($ascMid),
                    'lord' => $nakEnum->getRulingPlanet(),
                ],
                'reason' => 'Ascendant verified: remains stable across narrow approximate window.',
            ];
        }

        // Verified precision: compute Ascendant at exact birth time
        $ascDeg = $this->calculateAscendantAtCarbon($interval['mid'], $latitude, $longitude, $timezone);
        $rashiIdx = ZodiacDivision::rashiIndex($ascDeg);
        $rashiEnum = Rasi::from($rashiIdx);
        $nakIdx = ZodiacDivision::nakshatraIndex($ascDeg);
        $nakEnum = Nakshatra::from($nakIdx);
        $degInRashi = fmod($ascDeg, 30.0);

        return [
            'status' => 'confirmed',
            'id' => $rashiEnum->value + 1,
            'index' => $rashiEnum->value,
            'name' => $rashiEnum->getEnglishName(),
            'vedic_name' => $rashiEnum->getName(),
            'longitude' => round($ascDeg, 4),
            'degrees' => round($degInRashi, 4),
            'degrees_dms' => $this->formatDms($degInRashi),
            'nakshatra' => [
                'id' => $nakEnum->value + 1,
                'index' => $nakEnum->value,
                'name' => $nakEnum->name,
                'vedic_name' => $nakEnum->getName(),
                'pada' => ZodiacDivision::nakshatraPada($ascDeg),
                'lord' => $nakEnum->getRulingPlanet(),
            ],
            'reason' => 'Calculated from verified birth time.',
        ];
    }

    /**
     * Compute sidereal longitudes and metadata for all 9 classical bodies.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function calculateNatalPlanets(float $jd): array
    {
        $planets = [];

        foreach (self::BODIES as $key => $body) {
            if ($key === 'ketu') {
                $rahuLon = $planets['rahu']['longitude'] ?? 0.0;
                $ketuLon = AstroCore::normalize($rahuLon + 180.0);
                $rashiIdx = ZodiacDivision::rashiIndex($ketuLon);
                $rashiEnum = Rasi::from($rashiIdx);
                $nakIdx = ZodiacDivision::nakshatraIndex($ketuLon);
                $nakEnum = Nakshatra::from($nakIdx);
                $degInRashi = fmod($ketuLon, 30.0);

                $dignity = $this->calculateDignity('ketu', $rashiIdx + 1);

                $planets['ketu'] = [
                    'key' => 'ketu',
                    'name' => 'Ketu',
                    'name_hi' => 'केतु',
                    'abbr' => 'Ke',
                    'abbr_hi' => 'के',
                    'longitude' => round($ketuLon, 4),
                    'rashi' => [
                        'id' => $rashiIdx + 1,
                        'index' => $rashiIdx,
                        'name' => $rashiEnum->getEnglishName(),
                        'vedic_name' => $rashiEnum->getName(),
                        'degrees' => round($degInRashi, 4),
                        'degrees_dms' => $this->formatDms($degInRashi),
                    ],
                    'nakshatra' => [
                        'id' => $nakEnum->value + 1,
                        'index' => $nakEnum->value,
                        'name' => $nakEnum->name,
                        'vedic_name' => $nakEnum->getName(),
                        'pada' => ZodiacDivision::nakshatraPada($ketuLon),
                        'lord' => $nakEnum->getRulingPlanet(),
                    ],
                    'speed' => $planets['rahu']['speed'] ?? 0.0,
                    'is_retrograde' => true,
                    'is_combust' => false,
                    'dignity' => $dignity,
                ];
                continue;
            }

            $pos = $this->calculatePlanetAtJd($jd, $body['id']);
            $rashiEnum = Rasi::from($pos['rashi_index']);
            $nakIdx = ZodiacDivision::nakshatraIndex($pos['longitude']);
            $nakEnum = Nakshatra::from($nakIdx);

            $dignity = $this->calculateDignity($key, $pos['rashi_index'] + 1);

            $planets[$key] = [
                'key' => $key,
                'name' => $body['name'],
                'name_hi' => $body['name_hi'],
                'abbr' => $body['abbr'],
                'abbr_hi' => $body['abbr_hi'],
                'longitude' => round($pos['longitude'], 4),
                'rashi' => [
                    'id' => $pos['rashi_index'] + 1,
                    'index' => $pos['rashi_index'],
                    'name' => $rashiEnum->getEnglishName(),
                    'vedic_name' => $rashiEnum->getName(),
                    'degrees' => round($pos['degree_in_rashi'], 4),
                    'degrees_dms' => $this->formatDms($pos['degree_in_rashi']),
                ],
                'nakshatra' => [
                    'id' => $nakEnum->value + 1,
                    'index' => $nakEnum->value,
                    'name' => $nakEnum->name,
                    'vedic_name' => $nakEnum->getName(),
                    'pada' => ZodiacDivision::nakshatraPada($pos['longitude']),
                    'lord' => $nakEnum->getRulingPlanet(),
                ],
                'speed' => round($pos['speed'], 4),
                'is_retrograde' => ($key === 'rahu') ? true : $pos['is_retrograde'],
                'is_combust' => false, // Applied in next pass
                'dignity' => $dignity,
            ];
        }

        return $planets;
    }

    /**
     * Compute combustion status for planets relative to the Sun.
     */
    protected function applyCombustion(array $planets, float $sunLon): array
    {
        foreach ($planets as $key => &$planet) {
            if ($key === 'sun' || $key === 'rahu' || $key === 'ketu') {
                $planet['is_combust'] = false;
                continue;
            }

            $threshold = self::COMBUSTION_THRESHOLDS[$key] ?? null;
            if ($threshold === null) {
                $planet['is_combust'] = false;
                continue;
            }

            // Adjust threshold for Mercury and Venus when retrograde
            if ($key === 'mercury' && $planet['is_retrograde']) {
                $threshold = 12.0;
            } elseif ($key === 'venus' && $planet['is_retrograde']) {
                $threshold = 8.0;
            }

            $diff = abs($planet['longitude'] - $sunLon);
            if ($diff > 180.0) {
                $diff = 360.0 - $diff;
            }

            $planet['angular_separation_from_sun'] = round($diff, 4);
            $planet['is_combust'] = ($diff <= $threshold);
        }

        return $planets;
    }

    /**
     * Determine classical dignity for a planet in a given rashi.
     */
    protected function calculateDignity(string $planetKey, int $rashiId): string
    {
        $rules = self::PLANET_DIGNITY_RULES[$planetKey] ?? null;
        if (!$rules) {
            return 'neutral';
        }

        if ($rules['exalted'] === $rashiId) {
            return 'exalted';
        }
        if ($rules['debilitated'] === $rashiId) {
            return 'debilitated';
        }
        if (in_array($rashiId, $rules['own'], true)) {
            return 'own_sign';
        }

        return 'neutral';
    }

    /**
     * Construct the 12 houses under the Equal Rashi Bhava / Whole Sign system.
     *
     * In this traditional Vedic house system:
     * - House 1 is the sign containing the Ascendant (or Moon in Chandra Kundli).
     * - House 2 is the next zodiac sign, and so on up to House 12.
     *
     * @param int $firstHouseRashiId Zodiac sign number for House 1 (1=Aries..12=Pisces)
     * @param array<string, array<string, mixed>> $planets
     * @param array<string, mixed>|null $ascendantData
     * @param string $chartType 'lagna' or 'chandra'
     * @return array<int, array<string, mixed>> Indexed 1 to 12
     */
    protected function buildHouses(
        int $firstHouseRashiId,
        array $planets,
        ?array $ascendantData = null,
        string $chartType = 'lagna'
    ): array {
        $houses = [];

        // 1. Initialize houses 1 to 12
        for ($h = 1; $h <= 12; $h++) {
            // Sign for House h: ((firstRashiId - 1 + (h - 1)) % 12) + 1
            $rashiId = (($firstHouseRashiId - 1 + ($h - 1)) % 12) + 1;
            $rashiIdx = $rashiId - 1;
            $rashiEnum = Rasi::from($rashiIdx);

            $houses[$h] = [
                'house_number' => $h,
                'sign_id' => $rashiId,
                'sign_index' => $rashiIdx,
                'sign_name' => $rashiEnum->getEnglishName(),
                'sign_vedic_name' => $rashiEnum->getName(),
                'sign_lord' => $this->getSignLord($rashiId),
                'planets' => [],
            ];
        }

        // 2. Place planets into houses
        foreach ($planets as $key => $planet) {
            $planetRashiId = $planet['rashi']['id']; // 1..12
            // House number for planet: ((planetRashiId - firstHouseRashiId + 12) % 12) + 1
            $houseNum = (($planetRashiId - $firstHouseRashiId + 12) % 12) + 1;

            $houses[$houseNum]['planets'][] = [
                'key' => $planet['key'],
                'name' => $planet['name'],
                'name_hi' => $planet['name_hi'],
                'abbr' => $planet['abbr'],
                'abbr_hi' => $planet['abbr_hi'],
                'degrees' => $planet['rashi']['degrees'],
                'degrees_dms' => $planet['rashi']['degrees_dms'],
                'is_retrograde' => $planet['is_retrograde'],
                'is_combust' => $planet['is_combust'],
                'dignity' => $planet['dignity'],
            ];
        }

        return $houses;
    }

    /**
     * Get ruling planet for a zodiac sign (1=Aries..12=Pisces).
     */
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

    /**
     * Compute Ascendant (Lagna) degrees at a given timestamp and location.
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
     * Calculate planet sidereal position at Julian Day.
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
     * Format decimal degrees into DMS string (e.g. 14° 23' 15").
     */
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
