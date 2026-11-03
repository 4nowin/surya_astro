<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Handlers;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationHandlerInterface;
use App\Services\Astrology\Interpretation\PersonalizedDailyInterpretationService;
use App\Services\Astrology\PersonalizedAstrologyService;
use Illuminate\Support\Facades\Cache;

class PersonalizedInsightsCalculationHandler implements CalculationHandlerInterface
{
    public const TYPE = 'personalized_insights';
    private const CACHE_TTL_DAYS = 14;

    /**
     * Optional lazy-loaded astrology computation service.
     * MUST NOT be instantiated in web SAPI processes where native FFI is restricted.
     */
    protected ?PersonalizedAstrologyService $astrologyService = null;

    public function __construct(?PersonalizedAstrologyService $astrologyService = null)
    {
        $this->astrologyService = $astrologyService;
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
            'target_date' => (string) ($payload['target_date'] ?? ''),
            'ayanamsha' => PersonalizedAstrologyService::DEFAULT_AYANAMSHA,
            'ruleset' => PersonalizedAstrologyService::RULESET_VERSION,
        ];

        return hash('sha256', (string) json_encode($attributes));
    }

    /**
     * Resolve the astrology calculation service lazily on demand.
     * This is ONLY called within the background CLI worker (where FFI is supported).
     */
    protected function getService(): PersonalizedAstrologyService
    {
        return $this->astrologyService ??= app(PersonalizedAstrologyService::class);
    }

    public function calculate(array $payload): array
    {
        return $this->getService()->calculateFromPayload($payload);
    }

    public function storeResult(string $canonicalKey, array $result, array $payload): string
    {
        $cacheKey = "astrology:personalized_insights:{$canonicalKey}";
        Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));

        // Pre-warm interpretation caches for en and hi if profile is available
        $profileId = $payload['profile_id'] ?? null;
        if ($profileId) {
            try {
                $profile = UserAstrologyProfile::find($profileId);
                if ($profile) {
                    /** @var PersonalizedDailyInterpretationService $interpretationService */
                    $interpretationService = app(PersonalizedDailyInterpretationService::class);
                    $interpretationService->getOrGenerateDailyInsights($result, $canonicalKey, $profile, 'en');
                    $interpretationService->getOrGenerateDailyInsights($result, $canonicalKey, $profile, 'hi');
                }
            } catch (\Throwable $e) {
                // Pre-warming failure should not fail the base calculation job
            }
        }

        return $cacheKey;
    }

    public function getCachedResult(string $canonicalKey, array $payload): ?array
    {
        $cacheKey = "astrology:personalized_insights:{$canonicalKey}";
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
