<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Handlers;

use App\Services\Astrology\Calculation\CalculationHandlerInterface;
use App\Services\Astrology\KundliService;
use Illuminate\Support\Facades\Cache;

class KundliCalculationHandler implements CalculationHandlerInterface
{
    public const TYPE = 'kundli';
    private const CACHE_TTL_DAYS = 30;

    /**
     * Optional lazy-loaded Kundli computation service.
     * MUST NOT be instantiated in web SAPI processes where native FFI is restricted.
     */
    protected ?KundliService $kundliService = null;

    public function __construct(?KundliService $kundliService = null)
    {
        $this->kundliService = $kundliService;
    }

    public function getCalculationType(): string
    {
        return self::TYPE;
    }

    public function computeCanonicalKey(array $payload): string
    {
        $attributes = [
            'profile_id' => isset($payload['profile_id']) ? (string) $payload['profile_id'] : '',
            'dob' => (string) ($payload['date_of_birth'] ?? ''),
            'precision' => (string) ($payload['birth_time_precision'] ?? 'verified'),
            'birth_time' => isset($payload['birth_time']) ? (string) $payload['birth_time'] : '',
            'approx_window' => isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : '',
            'lat' => number_format((float) ($payload['latitude'] ?? 0.0), 4, '.', ''),
            'lon' => number_format((float) ($payload['longitude'] ?? 0.0), 4, '.', ''),
            'tz' => (string) ($payload['timezone'] ?? 'UTC'),
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

    public function calculate(array $payload): array
    {
        return $this->getService()->calculateFromPayload($payload);
    }

    public function storeResult(string $canonicalKey, array $result, array $payload): string
    {
        $cacheKey = "astrology:kundli:{$canonicalKey}";
        Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));

        return $cacheKey;
    }

    public function getCachedResult(string $canonicalKey, array $payload): ?array
    {
        $cacheKey = "astrology:kundli:{$canonicalKey}";
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
