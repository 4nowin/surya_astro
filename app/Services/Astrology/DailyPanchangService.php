<?php

declare(strict_types=1);

namespace App\Services\Astrology;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;

/**
 * Service for calculating and caching authoritative Daily Panchanga data.
 *
 * Implements M3 of the Astro Setu Product Roadmap.
 */
class DailyPanchangService
{
    private const CACHE_TTL_DAYS = 30;

    private const SANSKRIT_VARA = [
        0 => 'Ravivara',
        1 => 'Somavara',
        2 => 'Mangalavara',
        3 => 'Budhavara',
        4 => 'Guruvara',
        5 => 'Shukravara',
        6 => 'Shanivara',
    ];

    protected ?PanchangService $panchangService = null;

    public function __construct(
        ?PanchangService $panchangService = null
    ) {
        $this->panchangService = $panchangService;
    }

    protected function getPanchangService(): PanchangService
    {
        return $this->panchangService ??= app(PanchangService::class);
    }

    /**
     * Compute or retrieve cached daily Panchang for a specific date and location.
     *
     * @param string $date YYYY-MM-DD
     * @param float $latitude Decimal latitude (-90.0 to 90.0)
     * @param float $longitude Decimal longitude (-180.0 to 180.0)
     * @param string $timezone Canonical IANA timezone
     * @param float $elevation Elevation in meters above sea level
     * @return array<string, mixed>
     */
    public function getDailyPanchang(
        string $date,
        float $latitude,
        float $longitude,
        string $timezone,
        float $elevation = 0.0
    ): array {
        // Build canonical attributes and deterministic cache key
        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: $date,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            elevation: $elevation
        );

        $hash = CanonicalPanchangCacheKey::generateHash($attributes);
        $cacheKey = "panchang:daily:{$hash}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['metadata']['cached'] = true;
                $cached['metadata']['cache_key'] = $cacheKey;
                $cached['metadata']['calculation_ms'] = 0.0;
                return $cached;
            }
        }

        $startTime = hrtime(true);

        $dateImmutable = CarbonImmutable::parse($date, $timezone);

        $sections = [
            'Basic_Details',
            'Abhijit_Muhurta',
            'Brahma_Muhurta',
            'Amrita_Kaal',
            'Rahu_Kaal_Gulika_Yamaganda',
            'Dur_Muhurta_Full_Day',
            'Varjyam',
        ];

        $raw = $this->getPanchangService()->getSelectedDetails(
            $dateImmutable,
            $latitude,
            $longitude,
            $timezone,
            $sections,
            $elevation,
            null,
            CalendarType::Amanta,
            ['skip_festivals' => true]
        );

        $calculationMs = round((hrtime(true) - $startTime) / 1e6, 2);

        $normalized = $this->formatPanchangResponse(
            raw: $raw,
            date: $date,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            elevation: $elevation,
            cacheKey: $cacheKey,
            calculationMs: $calculationMs
        );

        // Cache the formatted result
        Cache::put($cacheKey, $normalized, now()->addDays(self::CACHE_TTL_DAYS));

        return $normalized;
    }

    /**
     * Normalize the raw PanchangCore output into the authoritative response structure.
     *
     * @param array<string, mixed> $raw
     * @param string $date
     * @param float $latitude
     * @param float $longitude
     * @param string $timezone
     * @param float $elevation
     * @param string $cacheKey
     * @param float $calculationMs
     * @return array<string, mixed>
     */
    protected function formatPanchangResponse(
        array $raw,
        string $date,
        float $latitude,
        float $longitude,
        string $timezone,
        float $elevation,
        string $cacheKey,
        float $calculationMs
    ): array {
        $basic = $raw['Basic_Details'] ?? [];
        $panchanga = $basic['Panchanga'] ?? [];
        $calendar = $basic['Hindu_Calendar'] ?? [];

        $tithi = $panchanga['Tithi'] ?? [];
        $varaRaw = $panchanga['Vara'] ?? [];
        $varaIndex = (int) ($varaRaw['index'] ?? 0);
        $nakshatra = $panchanga['Nakshatra'] ?? [];
        $yoga = $panchanga['Yoga'] ?? [];
        $karana = $panchanga['Karana'] ?? [];

        // Auspicious timings
        $abhijit = $raw['Abhijit_Muhurta'] ?? [];
        $brahma = $raw['Brahma_Muhurta'] ?? [];
        $amrit = $raw['Amrita_Kaal'] ?? [];

        // Inauspicious timings
        $badTimes = $raw['Rahu_Kaal_Gulika_Yamaganda'] ?? [];
        $rahu = $badTimes['Rahu Kaal'] ?? [];
        $yamaganda = $badTimes['Yamaganda'] ?? [];
        $gulika = $badTimes['Gulika'] ?? [];
        $durmuhurtaRaw = $raw['Dur_Muhurta_Full_Day'] ?? [];
        $varjyam = $raw['Varjyam'] ?? [];

        $durmuhurtaList = [];
        if (is_array($durmuhurtaRaw)) {
            foreach ($durmuhurtaRaw as $dm) {
                if (is_array($dm)) {
                    $durmuhurtaList[] = [
                        'name' => $dm['name'] ?? 'Durmuhurta',
                        'start' => $dm['start'] ?? '',
                        'end' => $dm['end'] ?? '',
                        'start_iso' => $dm['start_iso'] ?? null,
                        'end_iso' => $dm['end_iso'] ?? null,
                    ];
                }
            }
        }

        return [
            'metadata' => [
                'date' => $date,
                'latitude' => round($latitude, 4),
                'longitude' => round($longitude, 4),
                'timezone' => $timezone,
                'elevation_m' => round($elevation, 1),
                'cached' => false,
                'cache_key' => $cacheKey,
                'calculation_ms' => $calculationMs,
            ],
            'sun_moon' => [
                'sunrise' => $panchanga['Sunrise']['display'] ?? null,
                'sunrise_iso' => $panchanga['Sunrise']['iso'] ?? null,
                'sunset' => $panchanga['Sunset']['display'] ?? null,
                'sunset_iso' => $panchanga['Sunset']['iso'] ?? null,
                'moonrise' => $panchanga['Moonrise']['display'] ?? null,
                'moonrise_iso' => $panchanga['Moonrise']['iso'] ?? null,
                'moonset' => $panchanga['Moonset']['display'] ?? null,
                'moonset_iso' => $panchanga['Moonset']['iso'] ?? null,
            ],
            'panchanga' => [
                'tithi' => [
                    'index' => (int) ($tithi['index'] ?? 0),
                    'name' => $tithi['name'] ?? '',
                    'paksha' => $tithi['paksha'] ?? '',
                    'paksha_name' => $tithi['paksha_name'] ?? '',
                    'sunrise_tithi' => $panchanga['Tithi_At_Sunrise'] ?? $tithi,
                    'current_tithi' => $panchanga['Current_Tithi_At_Input_Now'] ?? $tithi,
                    'start' => $panchanga['tithi_start_dt'] ?? null,
                    'end' => $panchanga['tithi_end_dt'] ?? null,
                ],
                'vara' => [
                    'index' => $varaIndex,
                    'name' => $varaRaw['name'] ?? (Vara::tryFrom($varaIndex)?->getEnglishName() ?? ''),
                    'sanskrit_name' => self::SANSKRIT_VARA[$varaIndex] ?? (Vara::tryFrom($varaIndex)?->getName('hi') ?? ''),
                    'ruling_planet' => Vara::tryFrom($varaIndex)?->getRulingPlanet() ?? '',
                ],
                'nakshatra' => [
                    'index' => (int) ($panchanga['nak_index'] ?? 0),
                    'name' => $nakshatra['name'] ?? '',
                    'pada' => (int) ($nakshatra['pada'] ?? 1),
                    'lord' => $nakshatra['lord'] ?? '',
                    'end' => $panchanga['nakshatra_end_dt'] ?? null,
                ],
                'yoga' => [
                    'index' => (int) ($yoga['index'] ?? 0),
                    'name' => $yoga['name'] ?? '',
                    'end' => $panchanga['yoga_end_dt'] ?? null,
                ],
                'karana' => [
                    'index' => (int) ($karana['index'] ?? 0),
                    'name' => $karana['name'] ?? '',
                    'end' => $panchanga['karana_end_dt'] ?? null,
                ],
            ],
            'auspicious_timings' => [
                'abhijit' => [
                    'start' => $abhijit['abhijit_start'] ?? null,
                    'end' => $abhijit['abhijit_end'] ?? null,
                    'start_iso' => $abhijit['abhijit_start_iso'] ?? null,
                    'end_iso' => $abhijit['abhijit_end_iso'] ?? null,
                    'is_available' => !empty($abhijit['abhijit_start']),
                ],
                'brahma_muhurta' => [
                    'start' => $brahma['brahma_muhurta_start'] ?? null,
                    'end' => $brahma['brahma_muhurta_end'] ?? null,
                    'start_iso' => $brahma['brahma_muhurta_start_iso'] ?? null,
                    'end_iso' => $brahma['brahma_muhurta_end_iso'] ?? null,
                    'is_available' => !empty($brahma['brahma_muhurta_start']),
                ],
                'amrit_kaal' => [
                    'start' => $amrit['amrita_kaal_start'] ?? null,
                    'end' => $amrit['amrita_kaal_end'] ?? null,
                    'start_iso' => $amrit['amrita_kaal_start_iso'] ?? null,
                    'end_iso' => $amrit['amrita_kaal_end_iso'] ?? null,
                    'is_available' => (bool) ($amrit['is_available'] ?? (!empty($amrit['amrita_kaal_start']))),
                ],
            ],
            'inauspicious_timings' => [
                'rahu_kaal' => [
                    'start' => $rahu['start'] ?? null,
                    'end' => $rahu['end'] ?? null,
                    'start_iso' => $rahu['start_iso'] ?? null,
                    'end_iso' => $rahu['end_iso'] ?? null,
                ],
                'yamaganda' => [
                    'start' => $yamaganda['start'] ?? null,
                    'end' => $yamaganda['end'] ?? null,
                    'start_iso' => $yamaganda['start_iso'] ?? null,
                    'end_iso' => $yamaganda['end_iso'] ?? null,
                ],
                'gulika_kaal' => [
                    'start' => $gulika['start'] ?? null,
                    'end' => $gulika['end'] ?? null,
                    'start_iso' => $gulika['start_iso'] ?? null,
                    'end_iso' => $gulika['end_iso'] ?? null,
                ],
                'durmuhurta' => $durmuhurtaList,
                'varjyam' => [
                    'start' => $varjyam['varjyam_start'] ?? null,
                    'end' => $varjyam['varjyam_end'] ?? null,
                    'start_iso' => $varjyam['varjyam_start_iso'] ?? null,
                    'end_iso' => $varjyam['varjyam_end_iso'] ?? null,
                    'is_available' => (bool) ($varjyam['is_available'] ?? (!empty($varjyam['varjyam_start']))),
                ],
            ],
            'calendar' => [
                'vikram_samvat' => $calendar['Vikram_Samvat'] ?? null,
                'saka_samvat' => $calendar['Saka_Samvat'] ?? null,
                'gujarati_samvat' => $calendar['Gujarati_Samvat'] ?? null,
                'samvatsara' => $calendar['Samvatsara'] ?? null,
                'ayana' => $calendar['Ayana'] ?? null,
                'ritu' => $calendar['Ritu'] ?? null,
                'lunar_month_amanta' => $calendar['Month_Amanta'] ?? null,
                'lunar_month_purnimanta' => $calendar['Month_Purnimanta'] ?? null,
                'paksha' => $tithi['paksha'] ?? null,
                'is_adhika' => (bool) ($calendar['Is_Adhika'] ?? false),
            ],
        ];
    }
}
