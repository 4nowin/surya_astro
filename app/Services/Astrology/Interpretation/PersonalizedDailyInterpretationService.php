<?php

declare(strict_types=1);

namespace App\Services\Astrology\Interpretation;

use App\Models\DailyInsightRule;
use App\Models\UserAstrologyProfile;
use Illuminate\Support\Facades\Cache;

class PersonalizedDailyInterpretationService
{
    public const CACHE_PREFIX = 'astrology:personalized_daily_insights';
    private const CACHE_TTL_HOURS = 24;

    /**
     * Map categories to primary planetary rulers in Vedic Gochara.
     * Listed in priority order for rule matching.
     */
    protected const CATEGORY_PLANET_PRIORITIES = [
        DailyInsightRule::CATEGORY_OVERALL => ['moon', 'jupiter', 'sun'],
        DailyInsightRule::CATEGORY_CAREER => ['sun', 'saturn', 'mars', 'jupiter'],
        DailyInsightRule::CATEGORY_RELATIONSHIPS => ['venus', 'jupiter', 'moon'],
        DailyInsightRule::CATEGORY_FINANCE => ['jupiter', 'mercury', 'venus'],
        DailyInsightRule::CATEGORY_WELLBEING => ['moon', 'sun', 'mars'],
        DailyInsightRule::CATEGORY_OPPORTUNITIES_CAUTIONS => ['jupiter', 'moon', 'saturn'],
        DailyInsightRule::CATEGORY_GUIDANCE => ['moon', 'jupiter', 'sun'],
    ];

    /**
     * Retrieve cached daily interpretation or generate and cache a new one.
     *
     * @param array<string, mixed> $calculationResult M4B Stage 1 calculation result
     */
    public function getOrGenerateDailyInsights(
        array $calculationResult,
        string $canonicalKey,
        UserAstrologyProfile $profile,
        string $locale = 'en'
    ): array {
        $cacheKey = self::CACHE_PREFIX . ":{$canonicalKey}:{$locale}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['metadata']['cached'] = true;
                $cached['metadata']['cache_key'] = $cacheKey;
                return $cached;
            }
        }

        $insights = $this->interpret($calculationResult, $profile, $locale);
        $insights['metadata']['cache_key'] = $cacheKey;
        $insights['metadata']['cached'] = false;

        Cache::put($cacheKey, $insights, now()->addHours(self::CACHE_TTL_HOURS));

        return $insights;
    }

    /**
     * Interpret the calculated birth chart and transits into structured daily guidance.
     */
    public function interpret(
        array $calculationResult,
        UserAstrologyProfile $profile,
        string $locale = 'en'
    ): array {
        $meta = $calculationResult['metadata'] ?? [];
        $chart = $calculationResult['birth_chart_reference'] ?? [];
        $transits = $calculationResult['current_transits'] ?? [];
        $transitPlanets = $transits['planets'] ?? [];

        $precision = (string) ($meta['birth_time_precision'] ?? $profile->birth_time_precision);
        $targetDate = (string) ($meta['target_date'] ?? now()->toDateString());
        $moonSignData = $chart['moon_sign'] ?? [];
        $isMoonStable = ($moonSignData['status'] ?? '') === 'confirmed';

        // 1. Evaluate Personalisation Availability
        $availability = $this->evaluateAvailability($precision, $isMoonStable, $moonSignData, $locale);

        $transitsSummary = $this->buildTransitsSummary($transitPlanets);

        if (!$availability['is_personalized']) {
            return [
                'target_date' => $targetDate,
                'locale' => $locale,
                'profile' => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'relationship' => $profile->relationship,
                    'birth_city' => $profile->birth_city,
                    'birth_time_precision' => $precision,
                ],
                'personalization' => $availability,
                'summary' => [
                    'overall_tone' => DailyInsightRule::TONE_MIXED,
                    'headline' => $locale === 'hi' ? 'व्यक्तिगत मार्गदर्शन अनुपलब्ध' : 'Personalized Insights Unavailable',
                    'primary_influences' => [],
                ],
                'categories' => [],
                'birth_chart_reference' => $chart,
                'transits_summary' => $transitsSummary,
                'metadata' => [
                    'ruleset_version' => $meta['ruleset_version'] ?? 'M4B_V1',
                    'engine_version' => $meta['engine_version'] ?? 'JME_2.0.0_MOSHIER',
                    'ayanamsha' => $meta['ayanamsha'] ?? [],
                    'generated_at' => now()->toIso8601String(),
                ],
            ];
        }

        // 2. Generate Insights for each of the 7 Categories
        $categoriesOutput = [];
        $tonesCount = [
            DailyInsightRule::TONE_FAVOURABLE => 0,
            DailyInsightRule::TONE_MIXED => 0,
            DailyInsightRule::TONE_CAUTIOUS => 0,
        ];
        $primaryInfluences = [];

        foreach (DailyInsightRule::CATEGORIES as $catKey => $catMeta) {
            $catResult = $this->generateCategoryInsight($catKey, $catMeta, $transitPlanets, $locale);
            $categoriesOutput[$catKey] = $catResult;
            $tonesCount[$catResult['tone']]++;

            if (!empty($catResult['headline']) && count($primaryInfluences) < 3) {
                $primaryInfluences[] = $catResult['headline'];
            }
        }

        // 3. Determine Overall Tone & Summary
        // Classical rule: Ashtama Chandra (8th Moon) carries cautious weight
        $moonGocharaHouse = $transitPlanets['moon']['gochara_house'] ?? null;
        if ($moonGocharaHouse === 8) {
            $overallTone = DailyInsightRule::TONE_CAUTIOUS;
        } elseif ($tonesCount[DailyInsightRule::TONE_FAVOURABLE] >= 4) {
            $overallTone = DailyInsightRule::TONE_FAVOURABLE;
        } elseif ($tonesCount[DailyInsightRule::TONE_CAUTIOUS] >= 3) {
            $overallTone = DailyInsightRule::TONE_CAUTIOUS;
        } else {
            $overallTone = DailyInsightRule::TONE_MIXED;
        }

        return [
            'target_date' => $targetDate,
            'locale' => $locale,
            'profile' => [
                'id' => $profile->id,
                'name' => $profile->name,
                'relationship' => $profile->relationship,
                'birth_city' => $profile->birth_city,
                'birth_time_precision' => $precision,
            ],
            'personalization' => $availability,
            'summary' => [
                'overall_tone' => $overallTone,
                'overall_tone_label' => DailyInsightRule::TONES[$overallTone]["label_{$locale}"] ?? ucfirst($overallTone),
                'headline' => $categoriesOutput[DailyInsightRule::CATEGORY_OVERALL]['headline'] ?? '',
                'primary_influences' => $primaryInfluences,
            ],
            'categories' => $categoriesOutput,
            'birth_chart_reference' => $chart,
            'transits_summary' => $transitsSummary,
            'metadata' => [
                'ruleset_version' => $meta['ruleset_version'] ?? 'M4B_V1',
                'engine_version' => $meta['engine_version'] ?? 'JME_2.0.0_MOSHIER',
                'ayanamsha' => $meta['ayanamsha'] ?? [],
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Check birth-time reliability and determine personalization status.
     */
    protected function evaluateAvailability(
        string $precision,
        bool $isMoonStable,
        array $moonSignData,
        string $locale
    ): array {
        if (!$isMoonStable) {
            $possibleSigns = array_map(function ($sign) use ($locale) {
                return $locale === 'hi' ? ($sign['vedic_name'] ?? $sign['name']) : $sign['name'];
            }, $moonSignData['possible_signs'] ?? []);

            $signsList = implode(', ', $possibleSigns);

            return [
                'is_personalized' => false,
                'status' => 'uncertain_moon_sign',
                'reason' => $locale === 'hi'
                    ? "आपकी जन्म तिथि पर चन्द्रमा ने राशि परिवर्तन किया था ({$signsList})। व्यक्तिगत गोचर अंतर्दृष्टि के लिए कृपया अपनी जन्म प्रोफ़ाइल में अनुमानित या सटीक जन्म समय जोड़ें।"
                    : "The Moon changed zodiac signs on your birth date ({$signsList}). Please update your birth profile with an approximate or verified birth time to receive personalized insights.",
                'natal_moon_sign' => [
                    'status' => 'uncertain',
                    'possible_signs' => $moonSignData['possible_signs'] ?? [],
                ],
            ];
        }

        $note = match ($precision) {
            UserAstrologyProfile::PRECISION_UNKNOWN => $locale === 'hi'
                ? 'यह मार्गदर्शन आपके स्थिर जन्म चंद्र राशि पर आधारित है। जन्म समय अज्ञात होने से लग्न से संबंधित कारक सम्मिलित नहीं हैं।'
                : 'Based on your confirmed Janma Rashi (Moon sign). Ascendant-specific factors excluded due to unknown birth time.',
            UserAstrologyProfile::PRECISION_APPROXIMATE => $locale === 'hi'
                ? 'यह मार्गदर्शन आपके अनुमानित जन्म समय अंतराल में पुष्ट चंद्र राशि पर आधारित है।'
                : 'Based on confirmed natal Moon sign across your approximate birth time window.',
            default => null,
        };

        return [
            'is_personalized' => true,
            'status' => 'available',
            'reason' => null,
            'note' => $note,
            'natal_moon_sign' => [
                'index' => $moonSignData['index'] ?? null,
                'name' => $moonSignData['name'] ?? null,
                'vedic_name' => $moonSignData['vedic_name'] ?? null,
                'status' => 'confirmed',
            ],
        ];
    }

    /**
     * Generate insight for a single category by matching active Gochara rules.
     */
    protected function generateCategoryInsight(
        string $catKey,
        array $catMeta,
        array $transitPlanets,
        string $locale
    ): array {
        $candidatePlanets = self::CATEGORY_PLANET_PRIORITIES[$catKey] ?? ['moon', 'jupiter', 'sun'];
        $factors = [];
        $matchedRule = null;

        foreach ($candidatePlanets as $planetKey) {
            if (!isset($transitPlanets[$planetKey])) {
                continue;
            }

            $planetData = $transitPlanets[$planetKey];
            $house = $planetData['gochara_house'] ?? null;
            $isRetrograde = (bool) ($planetData['is_retrograde'] ?? false);

            if ($house === null) {
                continue;
            }

            $rule = $this->findMatchingRule($catKey, $planetKey, $house, $isRetrograde);

            if ($rule) {
                if (!$matchedRule) {
                    $matchedRule = $rule;
                }

                $factors[] = [
                    'planet' => $planetData['planet'] ?? ucfirst($planetKey),
                    'planet_key' => $planetKey,
                    'house' => $house,
                    'tone' => $rule->tone,
                    'is_retrograde' => $isRetrograde,
                    'rashi' => $planetData['rashi']['name'] ?? '',
                    'rashi_vedic' => $planetData['rashi']['vedic_name'] ?? '',
                    'description' => $rule->getHeadline($locale),
                ];
            }
        }

        // If no specific rule matched from DB, look for default category fallback
        if (!$matchedRule) {
            $matchedRule = DailyInsightRule::active()
                ->forCategory($catKey)
                ->orderBy('priority', 'asc')
                ->first();
        }

        $headline = $matchedRule ? $matchedRule->getHeadline($locale) : ($catMeta["name_{$locale}"] ?? $catKey);
        $interpretation = $matchedRule ? $matchedRule->getInterpretation($locale) : '';
        $tone = $matchedRule ? $matchedRule->tone : DailyInsightRule::TONE_MIXED;

        return [
            'category_key' => $catKey,
            'category_name' => $catMeta["name_{$locale}"] ?? ucfirst($catKey),
            'icon' => $catMeta['icon'] ?? 'sparkles',
            'headline' => $headline,
            'interpretation' => $interpretation,
            'tone' => $tone,
            'tone_label' => DailyInsightRule::TONES[$tone]["label_{$locale}"] ?? ucfirst($tone),
            'factors' => $factors,
        ];
    }

    /**
     * Match a rule in the database matching category, planet, house, and retrograde status.
     */
    protected function findMatchingRule(string $category, string $planet, int $house, bool $isRetrograde): ?DailyInsightRule
    {
        // 1. Try exact match including retrograde status
        $rule = DailyInsightRule::active()
            ->forCategory($category)
            ->forPlanetHouse($planet, $house)
            ->where(function ($q) use ($isRetrograde) {
                $q->where('is_retrograde', $isRetrograde)
                    ->orWhereNull('is_retrograde');
            })
            ->orderBy('priority', 'desc')
            ->first();

        return $rule;
    }

    /**
     * Build clean summary of current transits relative to natal Moon sign.
     */
    protected function buildTransitsSummary(array $transitPlanets): array
    {
        $summary = [];

        foreach ($transitPlanets as $key => $p) {
            $summary[] = [
                'planet' => $p['planet'] ?? ucfirst($key),
                'key' => $key,
                'house_from_moon' => $p['gochara_house'] ?? null,
                'gochara_status' => $p['gochara_status'] ?? 'confirmed',
                'rashi' => $p['rashi']['name'] ?? '',
                'rashi_vedic' => $p['rashi']['vedic_name'] ?? '',
                'degrees' => $p['rashi']['degrees'] ?? 0.0,
                'is_retrograde' => (bool) ($p['is_retrograde'] ?? false),
            ];
        }

        return $summary;
    }
}
