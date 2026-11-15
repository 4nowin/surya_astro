<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Handlers;

use App\Models\AstrologyCalculationJob;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationHandlerInterface;
use App\Services\Astrology\Calculation\Drivers\CalculationDriverInterface;
use App\Services\Astrology\Calculation\Drivers\RemoteHttpCalculationDriver;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use App\Services\Astrology\Yogas\ManglikDoshaService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class KundliCalculationHandler implements CalculationHandlerInterface
{
    public const TYPE = 'kundli';
    private const CACHE_TTL_DAYS = 30;

    /**
     * Optional lazy-loaded Kundli computation service.
     * MUST NOT be instantiated in web SAPI processes where native FFI is restricted.
     */
    protected ?KundliService $kundliService = null;
    protected ?CalculationDriverInterface $driver = null;

    public function __construct(
        ?KundliService $kundliService = null,
        ?CalculationDriverInterface $driver = null
    ) {
        $this->kundliService = $kundliService;
        $this->driver = $driver;
    }

    public function setKundliService(KundliService $kundliService): void
    {
        $this->kundliService = $kundliService;
    }

    public function setDriver(CalculationDriverInterface $driver): void
    {
        $this->driver = $driver;
    }

    public function getCalculationType(): string
    {
        return self::TYPE;
    }

    /**
     * Standardize and normalize Kundli input parameters across consumer and professional endpoints.
     * Guarantees 100% identical canonical cache keys and shared persistent MySQL calculations
     * across Astro Setu and Pandit Setu workspaces.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload): array
    {
        $dob = isset($payload['date_of_birth']) && is_string($payload['date_of_birth']) && trim($payload['date_of_birth']) !== ''
            ? trim($payload['date_of_birth'])
            : (isset($payload['dob']) && is_string($payload['dob']) ? trim($payload['dob']) : now('Asia/Kolkata')->toDateString());

        $birthTime = isset($payload['birth_time']) && is_string($payload['birth_time']) && trim($payload['birth_time']) !== ''
            ? trim($payload['birth_time'])
            : (isset($payload['time']) && is_string($payload['time']) ? trim($payload['time']) : null);

        $precision = isset($payload['birth_time_precision']) && is_string($payload['birth_time_precision']) && trim($payload['birth_time_precision']) !== ''
            ? trim($payload['birth_time_precision'])
            : (isset($payload['precision']) && is_string($payload['precision']) ? trim($payload['precision']) : (!empty($birthTime) ? UserAstrologyProfile::PRECISION_VERIFIED : UserAstrologyProfile::PRECISION_UNKNOWN));

        $approxWindow = isset($payload['approximate_time_window']) && is_string($payload['approximate_time_window']) && trim($payload['approximate_time_window']) !== ''
            ? trim($payload['approximate_time_window'])
            : (isset($payload['approx_window']) && is_string($payload['approx_window']) ? trim($payload['approx_window']) : null);

        $lat = isset($payload['latitude']) ? round((float) $payload['latitude'], 4) : (isset($payload['lat']) ? round((float) $payload['lat'], 4) : 28.6139);
        $lon = isset($payload['longitude']) ? round((float) $payload['longitude'], 4) : (isset($payload['lon']) ? round((float) $payload['lon'], 4) : 77.2090);

        $tz = isset($payload['timezone']) && is_string($payload['timezone']) && trim($payload['timezone']) !== ''
            ? trim($payload['timezone'])
            : (isset($payload['tz']) && is_string($payload['tz']) ? trim($payload['tz']) : 'Asia/Kolkata');

        return [
            'date_of_birth' => $dob,
            'birth_time' => $birthTime,
            'birth_time_precision' => $precision,
            'approximate_time_window' => $approxWindow,
            'latitude' => $lat,
            'longitude' => $lon,
            'timezone' => $tz,
            'name' => isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : 'Native',
            'birth_city' => isset($payload['birth_city']) && is_string($payload['birth_city']) ? trim($payload['birth_city']) : 'Unknown',
        ];
    }

    /**
     * Compute canonical SHA-256 calculation key based strictly on astronomical inputs.
     * Excludes user IDs, profile IDs, Jajman IDs, and names so results are shared universally.
     */
    public function computeCanonicalKey(array $payload): string
    {
        $normalized = self::normalizePayload($payload);

        $attributes = [
            'dob' => $normalized['date_of_birth'],
            'time' => (string) ($normalized['birth_time'] ?? ''),
            'precision' => (string) ($normalized['birth_time_precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED),
            'approx_window' => (string) ($normalized['approximate_time_window'] ?? ''),
            'lat' => number_format((float) $normalized['latitude'], 4, '.', ''),
            'lon' => number_format((float) $normalized['longitude'], 4, '.', ''),
            'tz' => (string) $normalized['timezone'],
            'ayanamsha' => KundliService::DEFAULT_AYANAMSHA,
            'house_system' => KundliService::DEFAULT_HOUSE_SYSTEM,
            'ruleset' => KundliService::RULESET_VERSION,
        ];

        return hash('sha256', (string) json_encode($attributes));
    }

    /**
     * Resolve the Kundli calculation service lazily on demand.
     * This is ONLY called within the background CLI worker (where FFI is supported).
     */
    protected function getService(): KundliService
    {
        return $this->kundliService ??= app(KundliService::class);
    }

    /**
     * Perform the comprehensive Kundli calculation in background CLI worker.
     * Generates Core planetary positions, dignities, Rashi/Bhava, ascendant/lagna,
     * Vimshottari dasha, Manglik dosha, Yogas, D-9 Navamsha chart & houses, and Saturn transits.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function calculate(array $payload): array
    {
        $normalized = self::normalizePayload($payload);

        $driver = $this->driver ?? (app()->bound(CalculationDriverInterface::class) ? app(CalculationDriverInterface::class) : null);

        if ($driver !== null && (config('astrology.driver') === 'remote' || $this->driver instanceof RemoteHttpCalculationDriver)) {
            return $driver->calculateKundliComplete($normalized);
        }

        $birthDate = $normalized['date_of_birth'];
        $birthTime = $normalized['birth_time'];
        $precision = $normalized['birth_time_precision'];
        $approximateWindow = $normalized['approximate_time_window'];
        $latitude = $normalized['latitude'];
        $longitude = $normalized['longitude'];
        $timezone = $normalized['timezone'];
        $name = $normalized['name'];
        $birthCity = $normalized['birth_city'];

        // 1. Core Kundli calculation (planets, ascendant, houses, Vimshottari dasha)
        $kundli = $this->getService()->computeKundli(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            profileMetadata: [
                'name' => $name,
                'birth_city' => $birthCity,
            ]
        );

        // 2. Enrich with additional Vedic modules lazily inside the CLI worker
        try {
            if (isset($kundli['planets']) && isset($kundli['ascendant'])) {
                // Manglik Dosha
                if (class_exists(ManglikDoshaService::class)) {
                    $manglikService = app(ManglikDoshaService::class);
                    $kundli['manglik_dosha'] = $manglikService->evaluate(
                        planets: $kundli['planets'],
                        lagnaRashiId: $kundli['ascendant']['id'] ?? $kundli['ascendant']
                    );
                }

                // Classical Yogas & Planetary Aspects
                if (class_exists(YogaDoshaOrchestrator::class)) {
                    $yogaOrchestrator = app(YogaDoshaOrchestrator::class);
                    $yogas = $yogaOrchestrator->evaluate(
                        planets: $kundli['planets'],
                        ascendantData: $kundli['ascendant'],
                        stabilityData: ['is_lagna_invariant' => true],
                        userAgeYears: 30
                    );
                    $kundli['yogas'] = $yogas['yogas'] ?? [];
                    $kundli['aspects'] = $yogas['aspects'] ?? [];
                }

                // D-9 Navamsha Chart & Houses
                if (class_exists(AstrologyIntervalStabilityService::class)) {
                    $stabilityService = app(AstrologyIntervalStabilityService::class);
                    $stability = $stabilityService->analyze(
                        birthDate: $birthDate,
                        birthTime: $birthTime,
                        precision: $precision,
                        approximateWindow: $approximateWindow,
                        latitude: $latitude,
                        longitude: $longitude,
                        timezone: $timezone
                    );

                    $d9Asc = $stability['d9_ascendant'] ?? null;
                    $d9Planets = $stability['planetary_navamshas'] ?? [];
                    $d9Houses = null;
                    if ($d9Asc && !empty($d9Asc['id'])) {
                        $d9Houses = self::buildNavamshaHouses((int) $d9Asc['id'], $d9Planets);
                    }

                    $kundli['navamsha'] = [
                        'status' => $d9Asc['status'] ?? 'available',
                        'ascendant' => $d9Asc,
                        'planets' => $d9Planets,
                        'houses' => $d9Houses,
                    ];
                    $kundli['stability'] = $stability;
                }

                // Saturn Transit Status
                if (class_exists(SaturnTransitService::class)) {
                    $moonSignId = $kundli['planets']['moon']['rashi']['id'] ?? 1;
                    $saturnService = app(SaturnTransitService::class);
                    $kundli['saturn_transit'] = $saturnService->evaluateCurrentPhaseAt($moonSignId, now($timezone)->toDateString());
                    try {
                        $birthCarbon = CarbonImmutable::parse($birthDate, $timezone);
                        $timeline = $saturnService->calculateTimeline(
                            natalMoonRashi: $moonSignId - 1,
                            startDate: $birthCarbon,
                            endDate: $birthCarbon->addYears(15),
                            options: [
                                'reference_date' => CarbonImmutable::now('UTC'),
                                'sample_step_days' => 7.0,
                            ]
                        );
                        $timeline['is_stable'] = true;
                        $kundli['saturn_transit_timeline'] = $timeline;
                    } catch (\Throwable $e) {
                        $fallback = $saturnService->evaluateCurrentPhaseAt($moonSignId);
                        $kundli['saturn_transit_timeline'] = [
                            'status' => 'fallback',
                            'is_stable' => true,
                            'qualitative_fallback' => $fallback,
                            'reason' => 'Current phase summary provided.',
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning("Kundli calculation enrichment warning: {$e->getMessage()}", [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $kundli;
    }

    /**
     * Map D-9 Navamsha planets into equal whole-sign houses for North/South Indian chart rendering.
     *
     * @param int $d9AscSignId 1-based zodiac sign ID (1=Aries..12=Pisces)
     * @param array<string, array<string, mixed>> $d9Planets
     * @return array<int, array<string, mixed>> Keyed 1..12
     */
    public static function buildNavamshaHouses(int $d9AscSignId, array $d9Planets): array
    {
        $zodiacSigns = [
            1 => ['name' => 'Aries', 'vedic_name' => 'Mesha', 'lord' => 'Mars'],
            2 => ['name' => 'Taurus', 'vedic_name' => 'Vrishabha', 'lord' => 'Venus'],
            3 => ['name' => 'Gemini', 'vedic_name' => 'Mithuna', 'lord' => 'Mercury'],
            4 => ['name' => 'Cancer', 'vedic_name' => 'Karka', 'lord' => 'Moon'],
            5 => ['name' => 'Leo', 'vedic_name' => 'Simha', 'lord' => 'Sun'],
            6 => ['name' => 'Virgo', 'vedic_name' => 'Kanya', 'lord' => 'Mercury'],
            7 => ['name' => 'Libra', 'vedic_name' => 'Tula', 'lord' => 'Venus'],
            8 => ['name' => 'Scorpio', 'vedic_name' => 'Vrischika', 'lord' => 'Mars'],
            9 => ['name' => 'Sagittarius', 'vedic_name' => 'Dhanu', 'lord' => 'Jupiter'],
            10 => ['name' => 'Capricorn', 'vedic_name' => 'Makara', 'lord' => 'Saturn'],
            11 => ['name' => 'Aquarius', 'vedic_name' => 'Kumbha', 'lord' => 'Saturn'],
            12 => ['name' => 'Pisces', 'vedic_name' => 'Meena', 'lord' => 'Jupiter'],
        ];

        $houses = [];
        for ($h = 1; $h <= 12; $h++) {
            $signId = (($d9AscSignId - 1 + ($h - 1)) % 12) + 1;
            $signMeta = $zodiacSigns[$signId];
            $houses[$h] = [
                'house_number' => $h,
                'sign_id' => $signId,
                'rashi' => [
                    'id' => $signId,
                    'name' => $signMeta['name'],
                    'vedic_name' => $signMeta['vedic_name'],
                    'lord' => $signMeta['lord'],
                ],
                'planets' => [],
            ];
        }

        foreach ($d9Planets as $key => $p) {
            $pSignId = (int) ($p['d9_navamsha']['id'] ?? ($p['invariant_d9_sign_id'] ?? 1));
            $houseNum = (($pSignId - $d9AscSignId + 12) % 12) + 1;

            if (isset($houses[$houseNum])) {
                $houses[$houseNum]['planets'][] = [
                    'key' => $key,
                    'name' => $p['name'] ?? ucfirst($key),
                    'name_hi' => $p['name_hi'] ?? '',
                    'sign_id' => $pSignId,
                    'is_vargottama' => !empty($p['is_vargottama']),
                    'is_retrograde' => false,
                    'is_combust' => false,
                ];
            }
        }

        return $houses;
    }

    /**
     * Store calculation result in fast cache and MySQL job storage.
     */
    public function storeResult(string $canonicalKey, array $result, array $payload): string
    {
        $cacheKey = "astrology:kundli:{$canonicalKey}";
        Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));

        try {
            AstrologyCalculationJob::where('calculation_type', self::TYPE)
                ->where('canonical_key', $canonicalKey)
                ->where('status', AstrologyCalculationJob::STATUS_COMPLETED)
                ->update(['result' => $result]);
        } catch (Throwable $e) {
            Log::warning("Failed to persist Kundli result to MySQL: {$e->getMessage()}");
        }

        return $cacheKey;
    }

    /**
     * Retrieve cached Kundli result. Checks fast cache first, then falls back to persistent
     * MySQL storage so results survive optimize:clear, cache:clear, and server restarts.
     */
    public function getCachedResult(string $canonicalKey, array $payload): ?array
    {
        $cacheKey = "astrology:kundli:{$canonicalKey}";

        // 1. Fast memory / file cache layer
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['metadata']['cached'] = true;
                $cached['metadata']['cache_key'] = $cacheKey;
                $cached['metadata']['source'] = 'fast_cache';
                $cached['metadata']['calculation_ms'] = 0.0;
                return $cached;
            }
        }

        // 2. Persistent MySQL storage (survives optimize:clear, cache:flush, and restarts)
        try {
            $completedJob = AstrologyCalculationJob::where('calculation_type', self::TYPE)
                ->where('canonical_key', $canonicalKey)
                ->where('status', AstrologyCalculationJob::STATUS_COMPLETED)
                ->whereNotNull('result')
                ->latest('completed_at')
                ->first();

            if ($completedJob && is_array($completedJob->result)) {
                $result = $completedJob->result;
                $result['metadata']['cached'] = true;
                $result['metadata']['cache_key'] = $cacheKey;
                $result['metadata']['source'] = 'mysql_job_result';
                $result['metadata']['calculation_ms'] = 0.0;

                // Re-seed fast cache
                Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));
                Log::info("Kundli cache hit [mysql_job_result] for [{$canonicalKey}]");
                return $result;
            }
        } catch (Throwable $e) {
            Log::warning("Error querying completed Kundli job result from MySQL: {$e->getMessage()}");
        }

        return null;
    }
}

