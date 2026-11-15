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

        if ($matchedRule) {
            $headline = $matchedRule->getHeadline($locale);
            $interpretation = $matchedRule->getInterpretation($locale);
            $tone = $matchedRule->tone;
        } else {
            // Authentic dynamic Vedic Gochara synthesis when no DB rule is present
            $dynamic = $this->synthesizeDynamicVedicInsight(
                $catKey,
                $catMeta,
                $candidatePlanets,
                $transitPlanets,
                $locale
            );
            $headline = $dynamic['headline'];
            $interpretation = $dynamic['interpretation'];
            $tone = $dynamic['tone'];

            if (empty($factors) && !empty($dynamic['factor'])) {
                $factors[] = $dynamic['factor'];
            }
        }

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
     * Synthesize authentic Vedic Gochara insight dynamically when database rules are absent.
     */
    protected function synthesizeDynamicVedicInsight(
        string $catKey,
        array $catMeta,
        array $candidatePlanets,
        array $transitPlanets,
        string $locale
    ): array {
        // Identify primary transit planet and its Gochara house from Janma Rashi
        $selectedPlanetKey = $candidatePlanets[0] ?? 'moon';
        $planetData = null;
        foreach ($candidatePlanets as $pk) {
            if (isset($transitPlanets[$pk]) && isset($transitPlanets[$pk]['gochara_house'])) {
                $selectedPlanetKey = $pk;
                $planetData = $transitPlanets[$pk];
                break;
            }
        }

        if (!$planetData && isset($transitPlanets['moon'])) {
            $selectedPlanetKey = 'moon';
            $planetData = $transitPlanets['moon'];
        }

        $house = $planetData['gochara_house'] ?? 1;
        $planetName = $planetData['planet'] ?? ucfirst($selectedPlanetKey);
        $rashiName = $planetData['rashi']['name'] ?? '';
        $rashiVedic = $planetData['rashi']['vedic_name'] ?? '';
        $isRetro = (bool) ($planetData['is_retrograde'] ?? false);

        // Classical Vedic Gochara tone determination
        $benefics = ['jupiter', 'venus', 'mercury', 'moon'];
        $isBenefic = in_array($selectedPlanetKey, $benefics, true);

        if ($isBenefic) {
            $favourableHouses = [1, 2, 5, 7, 9, 11];
            $cautiousHouses = [6, 8, 12];
        } else {
            $favourableHouses = [3, 6, 10, 11];
            $cautiousHouses = [1, 8, 12];
        }

        if (in_array($house, $favourableHouses, true) && !$isRetro) {
            $tone = DailyInsightRule::TONE_FAVOURABLE;
        } elseif (in_array($house, $cautiousHouses, true) || ($isRetro && in_array($house, [6, 8, 12], true))) {
            $tone = DailyInsightRule::TONE_CAUTIOUS;
        } else {
            $tone = DailyInsightRule::TONE_MIXED;
        }

        $data = $this->getDynamicVedicNarrative($catKey, $selectedPlanetKey, $house, $planetName, $rashiName, $rashiVedic, $tone, $locale);

        return [
            'headline' => $data['headline'],
            'interpretation' => $data['interpretation'],
            'tone' => $tone,
            'factor' => [
                'planet' => $planetName,
                'planet_key' => $selectedPlanetKey,
                'house' => $house,
                'tone' => $tone,
                'is_retrograde' => $isRetro,
                'rashi' => $rashiName,
                'rashi_vedic' => $rashiVedic,
                'description' => $data['headline'],
            ],
        ];
    }

    /**
     * Provide authentic Gochara narrative matrices for categories.
     */
    protected function getDynamicVedicNarrative(
        string $catKey,
        string $planetKey,
        int $house,
        string $planetName,
        string $rashiName,
        string $rashiVedic,
        string $tone,
        string $locale
    ): array {
        $isHi = ($locale === 'hi');

        switch ($catKey) {
            case DailyInsightRule::CATEGORY_OVERALL:
                $moonHouseHeadlinesEn = [
                    1 => 'Chandra in Janma Rashi: Heightened Sensitivity & Clear Intentions',
                    2 => 'Chandra in 2nd House: Focus on Material Resources & Calm Speech',
                    3 => 'Chandra in 3rd House: Bold Initiative & Productive Daily Interactions',
                    4 => 'Chandra in 4th House: Domestic Comfort & Finding Emotional Grounding',
                    5 => 'Chandra in 5th House: Creative Spark, Learning & Intellectual Focus',
                    6 => 'Chandra in 6th House: Disciplined Problem Solving & Steady Diligence',
                    7 => 'Chandra in 7th House: Harmonious Partnerships & Mutual Understanding',
                    8 => 'Chandra in 8th House: Inward Contemplation & Measured Deliberation',
                    9 => 'Chandra in 9th House: Favourable Guidance & Expansive Philosophical Clarity',
                    10 => 'Chandra in 10th House: Professional Drive, Prestige & Constructive Action',
                    11 => 'Chandra in 11th House: Rewarding Gains, Aspirations & Social Harmony',
                    12 => 'Chandra in 12th House: Thoughtful Restraint, Peace & Spiritual Solitude',
                ];
                $moonHouseHeadlinesHi = [
                    1 => 'जन्म राशि में चन्द्रमा: आत्म-जागरूकता और स्पष्ट संकल्प',
                    2 => 'द्वितीय भाव में चन्द्रमा: आर्थिक चिंतन और सौम्य संवाद',
                    3 => 'तृतीय भाव में चन्द्रमा: पराक्रम, साहस और सफल संचार',
                    4 => 'चतुर्थ भाव में चन्द्रमा: पारिवारिक सुख और मानसिक शांति',
                    5 => 'पंचम भाव में चन्द्रमा: रचनात्मक ऊर्जा, विद्या और विवेक',
                    6 => 'षष्ठ भाव में चन्द्रमा: बाधा निवारण और कर्मनिष्ठा',
                    7 => 'सप्तम भाव में चन्द्रमा: सहयोगात्मक संबंध और सामंजस्य',
                    8 => 'अष्टम भाव में चन्द्रमा: धैर्यपूर्वक विचार और अंतर्मुखता',
                    9 => 'नवम भाव में चन्द्रमा: भाग्योदय और आध्यात्मिक स्पष्टता',
                    10 => 'दशम भाव में चन्द्रमा: कर्म क्षेत्र में प्रभाव और प्रतिष्ठा',
                    11 => 'एकादश भाव में चन्द्रमा: लाभ योग और अभीष्ट सिद्धि',
                    12 => 'द्वादश भाव में चन्द्रमा: व्यय नियंत्रण और आत्म-अवलोकन',
                ];

                $headline = $isHi ? ($moonHouseHeadlinesHi[$house] ?? "गोचर प्रभाव: भाव {$house} में चन्द्र संचरण")
                                 : ($moonHouseHeadlinesEn[$house] ?? "Gochara Transit: Moon Moving Through House {$house}");

                $interpretation = $isHi
                    ? "आज चन्द्रमा आपकी जन्म राशि से भाव {$house} में गोचर कर रहा है। यह स्थिति आपके मन और दिनचर्या को दिशा देती है। धैर्यपूर्वक निर्णय लें और सकारात्मक दृष्टिकोण बनाए रखें।"
                    : "The Moon currently transits house {$house} relative to your natal Moon sign. This cosmic placement influences your emotional rhythm and mental focus for the day. Align your decisions with calm deliberation.";
                break;

            case DailyInsightRule::CATEGORY_CAREER:
                $headline = $isHi
                    ? "व्यावसायिक संरेखण: कर्म क्षेत्र में केंद्रित प्रयास और कर्तव्यबोध"
                    : "Professional Alignment: Structured Execution & Steady Progress";
                $interpretation = $isHi
                    ? "कार्यक्षेत्र में आज आपकी कार्यकुशलता और निर्णय क्षमता महत्वपूर्ण रहेगी। प्रमुख जिम्मेदारियों को प्राथमिकता दें और वरिष्ठों व सहयोगियों के साथ स्पष्ट संवाद बनाए रखें।"
                    : "Your professional sector invites focused execution and clear communication. Prioritize core obligations, maintain cooperative relationships with colleagues, and avoid hasty workplace confrontations.";
                break;

            case DailyInsightRule::CATEGORY_RELATIONSHIPS:
                $headline = $isHi
                    ? "संबंध व सामंजस्य: संवेदनशीलता, सौहार्द और परस्पर विश्वास"
                    : "Relationship Dynamics: Mutual Understanding & Warmth";
                $interpretation = $isHi
                    ? "व्यक्तिगत और पारिवारिक संबंधों में आज सहृदयता और धैर्य शुभ फल देगा। दूसरों के दृष्टिकोण को सुनकर समझें और अनावश्यक विवादों से दूर रहें।"
                    : "Personal connections flourish when approached with patience and active listening. Offer warmth to loved ones, honor commitments, and seek harmony in mutual conversations.";
                break;

            case DailyInsightRule::CATEGORY_FINANCE:
                $headline = $isHi
                    ? "आर्थिक संतुलन: विवेकपूर्ण व्यय और वित्तीय स्थिरता"
                    : "Financial Stewardship: Prudent Allocation & Stability";
                $interpretation = $isHi
                    ? "वित्तीय मामलों में आज सतर्कता और योजनाबद्ध चलना लाभदायक रहेगा। अनावश्यक खर्चों पर नियंत्रण रखें और महत्वपूर्ण आर्थिक निर्णयों में विवेक से काम लें।"
                    : "Planetary currents advise steady budgeting and cautious financial moves today. Postpone impulsive purchases, safeguard existing resources, and focus on long-term monetary security.";
                break;

            case DailyInsightRule::CATEGORY_WELLBEING:
                $headline = $isHi
                    ? "स्वास्थ्य व ऊर्जा: संतुलित दिनचर्या और मानसिक शांति"
                    : "Vitality & Balance: Restorative Rhythms & Mindful Health";
                $interpretation = $isHi
                    ? "शारीरिक स्फूर्ति और मानसिक संतुलन बनाए रखने के लिए समय पर विश्राम और सात्विक आहार लें। तनाव को दूर रखने के लिए प्राणायाम या ध्यान लाभकारी होगा।"
                    : "Support your physical vitality through balanced nutrition, timely hydration, and adequate rest. A mindful pause or gentle walk will restore clarity and release tension.";
                break;

            case DailyInsightRule::CATEGORY_OPPORTUNITIES_CAUTIONS:
                $headline = $isHi
                    ? "अवसर व सजगता: सकारात्मक संभावनाओं की पहचान और सावधानी"
                    : "Strategic Openings: Measured Initiative & Prudent Caution";
                $interpretation = $isHi
                    ? "आज कुछ रचनात्मक अवसर सामने आ सकते हैं, किंतु जल्दबाजी में निर्णय न लें। दस्तावेजों और नई योजनाओं की भली-भांति जांच-पड़ताल के बाद ही कदम बढ़ाएं।"
                    : "Constructive avenues may open up today for those who remain observant. Exercise diligence before signing agreements or committing resources, and proceed with measured confidence.";
                break;

            case DailyInsightRule::CATEGORY_GUIDANCE:
            default:
                $headline = $isHi
                    ? "दैनिक वैदिक मार्गदर्शन: सम्यक चिंतन और उद्देश्यपूर्ण कर्म"
                    : "Practical Guidance: Centered Action & Ethical Clarity";
                $interpretation = $isHi
                    ? "अपने आंतरिक विवेक को मार्गदर्शक बनाएं। दिन भर की गतिविधियों में शांत रहकर कर्म करें और सत्य व न्याय का मार्ग अपनाएं।"
                    : "Ground your actions in inner composure and clarity of purpose. Move through the day with integrity, trusting your inner wisdom to navigate subtle challenges.";
                break;
        }

        return [
            'headline' => $headline,
            'interpretation' => $interpretation,
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
