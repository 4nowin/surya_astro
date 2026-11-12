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

    public function computeCanonicalKey(array $payload): string
    {
        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: (string) ($payload['date'] ?? ''),
            latitude: (float) ($payload['lat'] ?? 0.0),
            longitude: (float) ($payload['lon'] ?? 0.0),
            timezone: (string) ($payload['tz'] ?? 'UTC'),
            elevation: isset($payload['elevation']) ? (float) $payload['elevation'] : 0.0
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
        $date = (string) ($payload['date'] ?? now('Asia/Kolkata')->toDateString());
        $lat = (float) ($payload['lat'] ?? 28.6139);
        $lon = (float) ($payload['lon'] ?? 77.2090);
        $tz = (string) ($payload['tz'] ?? 'Asia/Kolkata');
        $elevation = isset($payload['elevation']) ? (float) $payload['elevation'] : 0.0;

        return $this->getService()->getDailyPanchang(
            date: $date,
            latitude: $lat,
            longitude: $lon,
            timezone: $tz,
            elevation: $elevation
        );
    }

    public function storeResult(string $canonicalKey, array $result, array $payload): string
    {
        $cacheKey = "panchang:daily:{$canonicalKey}";

        // 1. Fast cache layer
        Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));

        // 2. Persistent MySQL storage (survives cache:clear, optimize:clear, and server restarts)
        try {
            $date = (string) ($payload['date'] ?? now('Asia/Kolkata')->toDateString());
            $lat = round((float) ($payload['lat'] ?? 28.6139), 4);
            $lon = round((float) ($payload['lon'] ?? 77.2090), 4);
            $tz = (string) ($payload['tz'] ?? 'Asia/Kolkata');
            $elevation = isset($payload['elevation']) ? (float) $payload['elevation'] : 0.0;

            DailyPanchangCache::updateOrCreate(
                ['canonical_key' => $canonicalKey],
                [
                    'date' => $date,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'timezone' => $tz,
                    'elevation' => $elevation,
                    'result' => $result,
                ]
            );
        } catch (Throwable $e) {
            Log::warning("Failed to persist Daily Panchang to MySQL: {$e->getMessage()}");
        }

        return $cacheKey;
    }

    public function getCachedResult(string $canonicalKey, array $payload): ?array
    {
        $cacheKey = "panchang:daily:{$canonicalKey}";

        // 1. Check fast memory/file cache
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

        // 2. Check persistent MySQL cache table
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
                return $result;
            }
        } catch (Throwable $e) {
            Log::warning("Error querying completed job result from MySQL: {$e->getMessage()}");
        }

        return null;
    }
}
