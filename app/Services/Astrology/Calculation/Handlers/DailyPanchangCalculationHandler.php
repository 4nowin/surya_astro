<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Handlers;

use App\Models\AstrologyCalculationJob;
use App\Models\DailyPanchangCache;
use App\Services\Astrology\Calculation\CalculationHandlerInterface;
use App\Services\Astrology\CanonicalPanchangCacheKey;
use App\Services\Astrology\DailyPanchangService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailyPanchangCalculationHandler implements CalculationHandlerInterface
{
    public const TYPE = 'daily_panchang';
    private const CACHE_TTL_DAYS = 30;

    /**
     * Optional lazy-loaded Panchang computation service.
     * MUST NOT be instantiated in web SAPI processes where native FFI is restricted.
     */
    protected ?DailyPanchangService $panchangService = null;

    public function __construct(?DailyPanchangService $panchangService = null)
    {
        $this->panchangService = $panchangService;
    }

    public function setPanchangService(DailyPanchangService $panchangService): void
    {
        $this->panchangService = $panchangService;
    }

    public function getCalculationType(): string
    {
        return self::TYPE;
    }

    /**
     * Standardize and normalize Panchang input parameters across all consumer and professional endpoints.
     * Guarantees 100% identical canonical cache keys and shared persistent MySQL records.
     *
     * @param array<string, mixed> $payload
     * @return array{date: string, lat: float, lon: float, tz: string, elevation: float}
     */
    public static function normalizePayload(array $payload): array
    {
        $date = isset($payload['date']) && is_string($payload['date']) && trim($payload['date']) !== ''
            ? trim($payload['date'])
            : now('Asia/Kolkata')->toDateString();

        $lat = isset($payload['lat']) ? round((float) $payload['lat'], 4) : 28.6139;
        $lon = isset($payload['lon']) ? round((float) $payload['lon'], 4) : 77.2090;
        $tz = isset($payload['tz']) && is_string($payload['tz']) && trim($payload['tz']) !== ''
            ? trim($payload['tz'])
            : 'Asia/Kolkata';
        $elevation = isset($payload['elevation']) ? round((float) $payload['elevation'], 2) : 0.0;

        return [
            'date' => $date,
            'lat' => $lat,
            'lon' => $lon,
            'tz' => $tz,
            'elevation' => $elevation,
        ];
    }

    public function computeCanonicalKey(array $payload): string
    {
        $normalized = self::normalizePayload($payload);

        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: $normalized['date'],
            latitude: $normalized['lat'],
            longitude: $normalized['lon'],
            timezone: $normalized['tz'],
            elevation: $normalized['elevation']
        );

        return CanonicalPanchangCacheKey::generateHash($attributes);
    }

    /**
     * Resolve the Panchang calculation service lazily on demand.
     * This is ONLY called within the background CLI worker (where FFI is supported).
     */
    protected function getService(): DailyPanchangService
    {
        return $this->panchangService ??= app(DailyPanchangService::class);
    }

    public function calculate(array $payload): array
    {
        $normalized = self::normalizePayload($payload);

        return $this->getService()->getDailyPanchang(
            date: $normalized['date'],
            latitude: $normalized['lat'],
            longitude: $normalized['lon'],
            timezone: $normalized['tz'],
            elevation: $normalized['elevation']
        );
    }

    public function storeResult(string $canonicalKey, array $result, array $payload): string
    {
        $tStart = microtime(true);
        $cacheKey = "panchang:daily:{$canonicalKey}";

        // 1. Fast cache layer
        $tCache0 = microtime(true);
        Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));
        $cacheMs = round((microtime(true) - $tCache0) * 1000, 2);

        // 2. Persistent MySQL storage (survives cache:clear, optimize:clear, and server restarts)
        $tDb0 = microtime(true);
        try {
            $normalized = self::normalizePayload($payload);

            DailyPanchangCache::updateOrCreate(
                ['canonical_key' => $canonicalKey],
                [
                    'date' => $normalized['date'],
                    'latitude' => $normalized['lat'],
                    'longitude' => $normalized['lon'],
                    'timezone' => $normalized['tz'],
                    'elevation' => $normalized['elevation'],
                    'result' => $result,
                ]
            );
        } catch (Throwable $e) {
            Log::warning("Failed to persist Daily Panchang to MySQL: {$e->getMessage()}");
        }
        $dbMs = round((microtime(true) - $tDb0) * 1000, 2);
        $totalMs = round((microtime(true) - $tStart) * 1000, 2);

        Log::info("Panchang result stored in {$totalMs}ms (fast_cache: {$cacheMs}ms, mysql: {$dbMs}ms) for [{$canonicalKey}]");

        return $cacheKey;
    }

    public function getCachedResult(string $canonicalKey, array $payload): ?array
    {
        $tStart = microtime(true);
        $cacheKey = "panchang:daily:{$canonicalKey}";

        // 1. Fast memory/file cache
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['metadata']['cached'] = true;
                $cached['metadata']['cache_key'] = $cacheKey;
                $cached['metadata']['source'] = 'fast_cache';
                $cached['metadata']['calculation_ms'] = 0.0;
                $durationMs = round((microtime(true) - $tStart) * 1000, 2);
                Log::debug("Panchang cache hit [fast_cache] in {$durationMs}ms for [{$canonicalKey}]");
                return $cached;
            }
        }

        // 2. Persistent MySQL cache table
        try {
            $dbCache = DailyPanchangCache::where('canonical_key', $canonicalKey)->first();
            if ($dbCache && is_array($dbCache->result)) {
                $result = $dbCache->result;
                $result['metadata']['cached'] = true;
                $result['metadata']['cache_key'] = $cacheKey;
                $result['metadata']['source'] = 'mysql_persistent';
                $result['metadata']['calculation_ms'] = 0.0;

                // Re-seed fast cache
                Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));
                $durationMs = round((microtime(true) - $tStart) * 1000, 2);
                Log::info("Panchang cache hit [mysql_persistent] in {$durationMs}ms for [{$canonicalKey}]");
                return $result;
            }
        } catch (Throwable $e) {
            Log::warning("Error querying DailyPanchangCache from MySQL: {$e->getMessage()}");
        }

        // 3. Fallback: check completed calculation job record with result column in MySQL
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

                Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));
                $durationMs = round((microtime(true) - $tStart) * 1000, 2);
                Log::info("Panchang cache hit [mysql_job_result] in {$durationMs}ms for [{$canonicalKey}]");
                return $result;
            }
        } catch (Throwable $e) {
            Log::warning("Error querying completed job result from MySQL: {$e->getMessage()}");
        }

        $durationMs = round((microtime(true) - $tStart) * 1000, 2);
        Log::debug("Panchang cache miss in {$durationMs}ms for [{$canonicalKey}]");
        return null;
    }
}
