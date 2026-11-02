<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Handlers;

use App\Services\Astrology\Calculation\CalculationHandlerInterface;
use App\Services\Astrology\CanonicalPanchangCacheKey;
use App\Services\Astrology\DailyPanchangService;
use Illuminate\Support\Facades\Cache;

class DailyPanchangCalculationHandler implements CalculationHandlerInterface
{
    public const TYPE = 'daily_panchang';
    private const CACHE_TTL_DAYS = 30;

    public function __construct(
        protected DailyPanchangService $panchangService
    ) {}

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

    public function calculate(array $payload): array
    {
        return $this->panchangService->getDailyPanchang(
            date: (string) $payload['date'],
            latitude: (float) $payload['lat'],
            longitude: (float) $payload['lon'],
            timezone: (string) $payload['tz'],
            elevation: isset($payload['elevation']) ? (float) $payload['elevation'] : 0.0
        );
    }

    public function storeResult(string $canonicalKey, array $result, array $payload): string
    {
        $cacheKey = "panchang:daily:{$canonicalKey}";
        Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));
        return $cacheKey;
    }

    public function getCachedResult(string $canonicalKey, array $payload): ?array
    {
        $cacheKey = "panchang:daily:{$canonicalKey}";
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['metadata']['cached'] = true;
                $cached['metadata']['cache_key'] = $cacheKey;
                $cached['metadata']['calculation_ms'] = 0.0;
                return $cached;
            }
        }

        return null;
    }
}
