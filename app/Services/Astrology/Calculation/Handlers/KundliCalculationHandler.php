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

    public function setKundliService(KundliService $kundliService): void
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
        $kundli = $this->getService()->calculateFromPayload($payload);

        try {
            if (isset($kundli['planets']) && isset($kundli['ascendant'])) {
                if (class_exists(\App\Services\Astrology\Yogas\ManglikDoshaService::class)) {
                    $manglikService = app(\App\Services\Astrology\Yogas\ManglikDoshaService::class);
                    $kundli['manglik_dosha'] = $manglikService->evaluate(
                        planets: $kundli['planets'],
                        lagnaRashiId: $kundli['ascendant']['id'] ?? $kundli['ascendant']
                    );
                }

                if (class_exists(\App\Services\Astrology\Yogas\YogaDoshaOrchestrator::class)) {
                    $yogaOrchestrator = app(\App\Services\Astrology\Yogas\YogaDoshaOrchestrator::class);
                    $yogas = $yogaOrchestrator->evaluate(
                        planets: $kundli['planets'],
                        ascendantData: $kundli['ascendant'],
                        stabilityData: ['is_lagna_invariant' => true],
                        userAgeYears: 30
                    );
                    $kundli['yogas'] = $yogas['yogas'] ?? [];
                    $kundli['aspects'] = $yogas['aspects'] ?? [];
                }

                if (class_exists(\App\Services\Astrology\Transits\SaturnTransitService::class)) {
                    $moonSignId = $kundli['planets']['moon']['rashi']['id'] ?? 1;
                    $tz = (string) ($payload['timezone'] ?? 'Asia/Kolkata');
                    $saturnService = app(\App\Services\Astrology\Transits\SaturnTransitService::class);
                    $kundli['saturn_transit'] = $saturnService->evaluateCurrentPhaseAt($moonSignId, now($tz)->toDateString());
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Kundli calculation enrichment warning: {$e->getMessage()}");
        }

        return $kundli;
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
