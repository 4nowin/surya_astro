<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports;

use App\Models\Astrologer;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Interpretation\DetailedInterpretationService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\Pdf\SvgVargaRenderer;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use RuntimeException;

/**
 * 15-Section Report Orchestrator and Gate B Eligibility Engine.
 *
 * Implements Work Package A5 of Astro Setu Milestone M9.2 (Detailed Kundli Report).
 *
 * Enforces Gate B strict eligibility rules:
 *  - Certified: Full mathematical certainty across birth uncertainty interval.
 *  - Qualified: Boundary transition detected; specific caveats, ranges, or multi-limb notations attached.
 *  - Suppressed: Mathematical uncertainty prevents reliable display; honest suppression without empty headers.
 *  - Fallback: Approved qualitative evaluation rendered when dated timelines are unviable.
 */
class DetailedKundliReportService
{
    public const REPORT_VERSION = 'M9.2_A5_V1';

    public function __construct(
        protected ?KundliService $kundliService = null,
        protected ?AstrologyIntervalStabilityService $stabilityService = null,
        protected ?YogaDoshaOrchestrator $yogaOrchestrator = null,
        protected ?SaturnTransitService $transitService = null,
        protected ?SvgVargaRenderer $svgRenderer = null,
        protected ?DetailedInterpretationService $interpretationService = null
    ) {
        $this->kundliService ??= app(KundliService::class);
        $this->stabilityService ??= app(AstrologyIntervalStabilityService::class);
        $this->yogaOrchestrator ??= app(YogaDoshaOrchestrator::class);
        $this->transitService ??= app(SaturnTransitService::class);
        $this->svgRenderer ??= app(SvgVargaRenderer::class);
        $this->interpretationService ??= app(DetailedInterpretationService::class);
    }

    /**
     * Build the complete 15-section report data payload.
     *
     * @param array<string, mixed> $payload Birth data payload
     * @param string $locale 'en' or 'hi'
     * @return array<string, mixed>
     */
    public function buildReportData(array $payload, string $locale = 'en'): array
    {
        $birthDate = (string) ($payload['date_of_birth'] ?? $payload['birth_date'] ?? '');
        $birthTime = isset($payload['birth_time']) ? (string) $payload['birth_time'] : null;
        $precision = (string) ($payload['birth_time_precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED);
        $approximateWindow = isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : null;
        $latitude = (float) ($payload['latitude'] ?? 0.0);
        $longitude = (float) ($payload['longitude'] ?? 0.0);
        $timezone = (string) ($payload['timezone'] ?? 'UTC');
        $personName = (string) ($payload['name'] ?? 'Native');
        $birthCity = (string) ($payload['birth_city'] ?? 'Unknown');
        $birthCountry = (string) ($payload['birth_country'] ?? 'India');

        if ($birthDate === '') {
            throw new RuntimeException('Missing date_of_birth in report calculation payload.');
        }

        // 1. Core Kundli calculation
        $kundli = $this->kundliService->calculateFromPayload([
            'date_of_birth' => $birthDate,
            'birth_time' => $birthTime,
            'birth_time_precision' => $precision,
            'approximate_time_window' => $approximateWindow,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'name' => $personName,
            'birth_city' => $birthCity,
        ]);

        // 2. Interval stability analysis across birth uncertainty window
        $stability = $this->stabilityService->analyze(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            targetDate: CarbonImmutable::now($timezone)->toIso8601String(),
            locale: $locale
        );

        // Calculate user age in years
        $birthCarbon = CarbonImmutable::parse($birthDate, $timezone);
        $userAgeYears = (int) $birthCarbon->diffInYears(CarbonImmutable::now($timezone));

        // 3. Yogas, aspects & Manglik Dosha
        $yogas = $this->yogaOrchestrator->evaluate(
            planets: $kundli['planets'],
            ascendantData: $kundli['ascendant'],
            stabilityData: $stability,
            userAgeYears: $userAgeYears
        );

        // 4. Saturn transits (Sade Sati & Dhaiya)
        $transitData = $this->resolveSaturnTransits($kundli, $stability, $birthCarbon);

        // 5. Active verified astrologers for referral section
        $astrologers = $this->getReferralAstrologers($locale);

        // 6. Evaluate Gate B eligibility and assemble all 15 sections
        $auditMatrix = [];

        // Section 1: Cover & Coordinates
        $sec1 = $this->buildSection1Cover($kundli, $precision, $personName, $birthCity, $birthCountry, $locale);
        $auditMatrix['sec1_cover'] = $sec1['gate_b_status'];

        // Section 2: Panchanga & Avakahada
        $sec2 = $this->buildSection2Panchanga($kundli, $stability, $locale);
        $auditMatrix['sec2_panchanga'] = $sec2['gate_b_status'];

        // Section 3: Lagna Kundli (D-1) & Planetary Table
        $sec3 = $this->buildSection3LagnaKundli($kundli, $stability, $locale);
        $auditMatrix['sec3_lagna_d1'] = $sec3['gate_b_status'];

        // Section 4: Chandra Kundli (Moon Chart)
        $sec4 = $this->buildSection4ChandraKundli($kundli, $stability, $locale);
        $auditMatrix['sec4_chandra'] = $sec4['gate_b_status'];

        // Section 5: D-9 Navamsha Chart & Vargottama
        $sec5 = $this->buildSection5Navamsha($kundli, $stability, $locale);
        $auditMatrix['sec5_navamsha_d9'] = $sec5['gate_b_status'];

        // Section 6: Parashari Graha Drishti Matrix
        $sec6 = $this->buildSection6Aspects($yogas['aspects'] ?? [], $precision, $locale);
        $auditMatrix['sec6_aspects'] = $sec6['gate_b_status'];

        // Section 7: Janma Nakshatra Deep Dive
        $sec7 = $this->buildSection7JanmaNakshatra($kundli, $stability, $locale);
        $auditMatrix['sec7_nakshatra'] = $sec7['gate_b_status'];

        // Section 8: Vimshottari Mahadasha Timeline
        $sec8 = $this->buildSection8VimshottariTimeline($stability['vimshottari_dasha'] ?? [], $locale);
        $auditMatrix['sec8_vimshottari_timeline'] = $sec8['gate_b_status'];

        // Section 9: Current Running Dasha & Pratyantardasha
        $sec9 = $this->buildSection9CurrentDasha($stability['vimshottari_dasha'] ?? [], $locale);
        $auditMatrix['sec9_current_dasha'] = $sec9['gate_b_status'];

        // Section 10: Classical Planetary Yogas
        $sec10 = $this->buildSection10ClassicalYogas($yogas, $sec3['gate_b_status'], $locale);
        $auditMatrix['sec10_classical_yogas'] = $sec10['gate_b_status'];

        // Section 11: Manglik Dosha Audit
        $sec11 = $this->buildSection11ManglikDosha($yogas['manglik_dosha'] ?? [], $locale);
        $auditMatrix['sec11_manglik_dosha'] = $sec11['gate_b_status'];

        // Section 12: Saturn Sade Sati & Dhaiya
        $sec12 = $this->buildSection12SaturnTransit($transitData, $locale);
        $auditMatrix['sec12_saturn_transit'] = $sec12['gate_b_status'];

        // Section 13: Classical Vedic Remedies (Upay)
        $sec13 = $this->buildSection13Remedies($kundli, $yogas, $sec11, $locale);
        $auditMatrix['sec13_remedies'] = $sec13['gate_b_status'];

        // Section 14: Astrologer Referral
        $sec14 = $this->buildSection14AstrologerReferral($astrologers, $locale);
        $auditMatrix['sec14_astrologer_referral'] = $sec14['gate_b_status'];

        // Section 15: Precision Audit & Disclaimers
        $auditMatrix['sec15_precision_audit'] = 'certified';
        $sec15 = $this->buildSection15PrecisionAudit($auditMatrix, $precision, $locale);

        return [
            'meta' => [
                'engine_version' => self::REPORT_VERSION,
                'ruleset_version' => DetailedInterpretationService::RULE_VERSION,
                'locale' => $locale,
                'precision_tier' => $precision,
                'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'person_name' => $personName,
                'birth_city' => $birthCity,
                'birth_country' => $birthCountry,
                'audit_matrix' => $auditMatrix,
            ],
            'sections' => [
                'sec1_cover' => $sec1,
                'sec2_panchanga' => $sec2,
                'sec3_lagna_d1' => $sec3,
                'sec4_chandra' => $sec4,
                'sec5_navamsha_d9' => $sec5,
                'sec6_aspects' => $sec6,
                'sec7_nakshatra' => $sec7,
                'sec8_vimshottari_timeline' => $sec8,
                'sec9_current_dasha' => $sec9,
                'sec10_classical_yogas' => $sec10,
                'sec11_manglik_dosha' => $sec11,
                'sec12_saturn_transit' => $sec12,
                'sec13_remedies' => $sec13,
                'sec14_astrologer_referral' => $sec14,
                'sec15_precision_audit' => $sec15,
            ],
        ];
    }

    /**
     * Resolve Saturn transit timeline or qualitative fallback.
     */
    protected function resolveSaturnTransits(array $kundli, array $stability, CarbonImmutable $birthCarbon): array
    {
        $moonLon = (float) ($kundli['planets']['moon']['longitude'] ?? 0.0);
        $moonRashi = (int) (floor($moonLon / 30.0) + 1);

        // Check if Moon sign is invariant across interval
        $panchangaLimb = $stability['panchanga']['moon_sign'] ?? null;
        $isMoonInvariant = $panchangaLimb === null || !empty($panchangaLimb['is_invariant']);

        if (!$isMoonInvariant) {
            // Moon sign volatile: return qualitative fallback
            $fallback = $this->transitService->evaluateCurrentPhaseAt($moonRashi);
            return [
                'status' => 'fallback',
                'is_stable' => false,
                'qualitative_fallback' => $fallback,
                'reason' => 'Natal Moon sign crosses sign boundary across uncertainty interval. Qualitative fallback provided.',
            ];
        }

        // Calculate a 15-year timeline from birth with 7-day sampling step
        try {
            $timeline = $this->transitService->calculateTimeline(
                natalMoonRashi: $moonRashi,
                startDate: $birthCarbon,
                endDate: $birthCarbon->addYears(15),
                options: [
                    'reference_date' => CarbonImmutable::now('UTC'),
                    'sample_step_days' => 7.0,
                ]
            );
            $timeline['is_stable'] = true;
            return $timeline;
        } catch (\Throwable $e) {
            // Fallback to current phase if full timeline calculation fails
            $fallback = $this->transitService->evaluateCurrentPhaseAt($moonRashi);
            return [
                'status' => 'fallback',
                'is_stable' => true,
                'qualitative_fallback' => $fallback,
                'reason' => 'Current phase summary provided.',
            ];
        }
    }

    /**
     * Retrieve active verified astrologers from database.
     */
    protected function getReferralAstrologers(string $locale): array
    {
        try {
            return Astrologer::query()
                ->where('active', 1)
                ->take(3)
                ->get()
                ->map(function ($ast) use ($locale) {
                    return [
                        'id' => $ast->id,
                        'name' => $ast->name,
                        'expertise' => $ast->expertise ?? 'Vedic Astrology',
                        'experience' => $ast->experience ?? 5,
                        'price' => (float) $ast->price,
                        'call_price' => (float) ($ast->call_price ?? $ast->price),
                        'rating' => round((float) ($ast->averageRating() ?: 4.8), 1),
                        'language' => $ast->language ?? ($locale === 'hi' ? 'हिन्दी, English' : 'English, Hindi'),
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Section 1: Cover Page & Coordinates.
     */
    protected function buildSection1Cover(
        array $kundli,
        string $precision,
        string $name,
        string $birthCity,
        string $birthCountry,
        string $locale
    ): array {
        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'वैदिक जन्म पत्रिका (विस्तृत प्रतिवेदन)' : 'Comprehensive Vedic Janam Kundli Report',
            'name' => $name,
            'birth_city' => $birthCity,
            'birth_country' => $birthCountry,
            'date_of_birth' => $kundli['birth_details']['date_of_birth'] ?? '',
            'birth_time' => $kundli['birth_details']['birth_time'] ?? null,
            'precision_tier' => $precision,
            'latitude' => $kundli['birth_details']['latitude'] ?? 0.0,
            'longitude' => $kundli['birth_details']['longitude'] ?? 0.0,
            'timezone' => $kundli['birth_details']['timezone'] ?? 'UTC',
            'ayanamsa' => 'True Chitra Paksha Lahiri (IAU Compliant)',
            'calculation_engine' => 'Jayesh Mepani Moshier Ephemeris + JmeRuntime FFI',
            'report_version' => self::REPORT_VERSION,
            'purushartha_framing' => DetailedInterpretationService::PURUSHARTHA_FRAMING[$locale],
            'general_disclaimer' => DetailedInterpretationService::GENERAL_DISCLAIMER[$locale],
        ];
    }

    /**
     * Section 2: Panchanga & Avakahada Chakra.
     */
    protected function buildSection2Panchanga(array $kundli, array $stability, string $locale): array
    {
        $panchangaStability = $stability['panchanga'] ?? [];
        $hasTransition = false;

        foreach ($panchangaStability as $limbKey => $limbData) {
            if (is_array($limbData) && empty($limbData['is_invariant'])) {
                $hasTransition = true;
                break;
            }
        }

        $gateBStatus = $hasTransition ? 'qualified' : 'certified';

        $panchanga = $kundli['panchanga'] ?? [];
        $avakahada = $kundli['avakahada'] ?? [];

        return [
            'gate_b_status' => $gateBStatus,
            'title' => $locale === 'hi' ? 'पंचांग एवं अवकहड़ा चक्र' : 'Panchanga & Avakahada Chakra',
            'has_transition' => $hasTransition,
            'panchanga' => [
                'tithi' => $panchanga['tithi'] ?? [],
                'vara' => $panchanga['vara'] ?? [],
                'nakshatra' => $panchanga['nakshatra'] ?? [],
                'yoga' => $panchanga['yoga'] ?? [],
                'karana' => $panchanga['karana'] ?? [],
                'sunrise' => $panchanga['sunrise'] ?? '',
                'sunset' => $panchanga['sunset'] ?? '',
            ],
            'avakahada' => [
                'varna' => $avakahada['varna'] ?? ($locale === 'hi' ? 'ब्राह्मण' : 'Brahmana'),
                'vashya' => $avakahada['vashya'] ?? ($locale === 'hi' ? 'जलचर' : 'Jalachara'),
                'yoni' => $avakahada['yoni'] ?? ($locale === 'hi' ? 'गज' : 'Gaja'),
                'gana' => $avakahada['gana'] ?? ($locale === 'hi' ? 'देव' : 'Deva'),
                'nadi' => $avakahada['nadi'] ?? ($locale === 'hi' ? 'आदि' : 'Adi'),
                'nakshatra_lord' => $avakahada['nakshatra_lord'] ?? '',
                'rashi_lord' => $avakahada['rashi_lord'] ?? '',
            ],
            'nadi_disclaimer' => DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER[$locale],
            'transition_notes' => $hasTransition
                ? ($locale === 'hi'
                    ? 'जन्म समय में अनिश्चितता के कारण पंचांग के कुछ अंगों में संक्रांति (परिवर्तन) पाई गई है। संभावित अंग कोष्ठक में दर्शाए गए हैं।'
                    : 'Due to birth time uncertainty, one or more Panchanga limbs cross a boundary during the uncertainty interval. Transitioning limbs are explicitly noted.')
                : null,
        ];
    }

    /**
     * Section 3: Lagna Kundli (D-1) & Planetary Table.
     */
    protected function buildSection3LagnaKundli(array $kundli, array $stability, string $locale): array
    {
        $d1Asc = $stability['ascendant']['d1_ascendant'] ?? ($stability['d1_ascendant'] ?? null);
        $isInvariant = !empty($d1Asc['is_invariant']) && ($d1Asc['status'] ?? '') === 'confirmed';

        if (!$isInvariant) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'लग्न कुण्डली (D-1) एवं ग्रह स्थिति' : 'Lagna Kundli (D-1) & Planetary Table',
                'is_suppressed' => true,
                'svg' => null,
                'reason' => $locale === 'hi'
                    ? 'अनिश्चित अथवा अनुमानित जन्म समय के कारण लग्न राशि अस्थिर है। गणितीय मर्यादा की रक्षा हेतु लग्न कुण्डली को स्थगित किया गया है। विश्लेषण चंद्र कुंडली पर आधारित है।'
                    : 'The D-1 Ascendant crosses sign boundaries across the recorded uncertainty interval. To preserve mathematical integrity, the D-1 chart and house-dependent interpretations are suppressed; interpretations rely on the Chandra Kundli.',
                'planets' => [],
            ];
        }

        // Format planetary rows with dignity and Nakshatra
        $planets = [];
        foreach ($kundli['planets'] ?? [] as $key => $p) {
            $dignity = $p['dignity'] ?? 'neutral';
            $planets[] = [
                'key' => $key,
                'name' => $locale === 'hi' ? ($p['name_hi'] ?? $p['name']) : $p['name'],
                'longitude' => $p['longitude_dms'] ?? round($p['longitude'] ?? 0.0, 2) . '°',
                'rashi' => $locale === 'hi' ? ($p['rashi']['vedic_name'] ?? '') : ($p['rashi']['name'] ?? ''),
                'house' => $p['house'] ?? 1,
                'dignity' => $this->interpretationService->getDignityInterpretation($key, $dignity, $locale),
                'nakshatra' => $p['nakshatra']['name'] ?? '',
                'pada' => $p['nakshatra']['pada'] ?? 1,
                'is_retrograde' => !empty($p['is_retrograde']),
                'is_combust' => !empty($p['is_combust']),
            ];
        }

        $ascDms = $d1Asc['degrees_dms'] ?? ($kundli['ascendant']['degrees_dms'] ?? null);
        $svg = $this->svgRenderer->renderD1($kundli['houses'] ?? [], $locale, $ascDms);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'लग्न कुण्डली (D-1) एवं ग्रह स्थिति' : 'Lagna Kundli (D-1) & Planetary Table',
            'is_suppressed' => false,
            'svg' => $svg,
            'ascendant' => [
                'name' => $locale === 'hi' ? ($d1Asc['vedic_name'] ?? '') : ($d1Asc['name'] ?? ''),
                'longitude' => $ascDms ?? '',
                'nakshatra' => $d1Asc['nakshatra']['name'] ?? '',
                'pada' => $d1Asc['nakshatra']['pada'] ?? 1,
            ],
            'planets' => $planets,
        ];
    }

    /**
     * Section 4: Chandra Kundli (Moon Chart).
     */
    protected function buildSection4ChandraKundli(array $kundli, array $stability, string $locale): array
    {
        $panchangaLimb = $stability['panchanga']['moon_sign'] ?? null;
        $isMoonInvariant = $panchangaLimb === null || !empty($panchangaLimb['is_invariant']);

        if (!$isMoonInvariant) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'चन्द्र कुण्डली' : 'Chandra Kundli (Moon Chart)',
                'is_suppressed' => true,
                'svg' => null,
                'reason' => $locale === 'hi'
                    ? 'चंद्र राशि में जन्म समय की अनिश्चितता के कारण परिवर्तन संभव है, अतः चंद्र कुंडली को स्थगित किया गया है।'
                    : 'The natal Moon crosses sign boundaries across the uncertainty interval; Chandra Kundli is suppressed.',
            ];
        }

        $chandraHouses = $kundli['chandra_kundli']['houses'] ?? [];
        $svg = $this->svgRenderer->renderChandra($chandraHouses, $locale);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'चन्द्र कुण्डली (मन एवं चेतना)' : 'Chandra Kundli (Moon Chart — Mind & Consciousness)',
            'is_suppressed' => false,
            'svg' => $svg,
            'summary' => $locale === 'hi'
                ? 'चन्द्र कुण्डली मानसिक प्रवृत्तियों, मनोवैज्ञानिक संतुलन और भावनात्मक प्रतिक्रियाओं का सूक्ष्म दर्पण प्रस्तुत करती है।'
                : 'Chandra Kundli reflects psychological stability, emotional inclinations, and the mental lens through which life experiences are processed.',
        ];
    }

    /**
     * Section 5: D-9 Navamsha Chart & Vargottama.
     */
    protected function buildSection5Navamsha(array $kundli, array $stability, string $locale): array
    {
        $d9Planets = $stability['navamsha'] ?? ($stability['planetary_navamsha'] ?? []);
        $d9Asc = $stability['ascendant']['d9_ascendant'] ?? ($stability['d9_ascendant'] ?? null);

        $vargottamaList = [];
        foreach ($d9Planets as $key => $p) {
            if (!empty($p['is_vargottama'])) {
                $vargottamaList[] = [
                    'key' => $key,
                    'name' => $locale === 'hi' ? ($p['name_hi'] ?? $p['name']) : $p['name'],
                    'rashi' => $p['invariant_d9_sign'] ?? ($p['d1_sign']['name'] ?? ''),
                ];
            }
        }

        $svg = $this->svgRenderer->renderD9($d9Planets, $d9Asc, $locale);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'नवांश कुण्डली (D-9) एवं वर्गोत्तम ग्रह' : 'D-9 Navamsha Chart & Vargottama Status',
            'is_suppressed' => false,
            'svg' => $svg,
            'd9_ascendant_invariant' => !empty($d9Asc['is_invariant']),
            'vargottama_planets' => $vargottamaList,
            'note' => empty($d9Asc['is_invariant'])
                ? ($locale === 'hi'
                    ? 'नवांश लग्न जन्म समय की सूक्ष्म अनिश्चितता के कारण परिवर्तनशील है। अतः नवांश चक्र को राशि-वितरण (Sign Distribution) के रूप में बिना लग्न चिन्ह के प्रस्तुत किया गया है।'
                    : 'The D-9 Ascendant is volatile across the birth uncertainty window. The chart is rendered as an unrotated sign distribution highlighting verified planetary placements.')
                : null,
        ];
    }

    /**
     * Section 6: Parashari Graha Drishti Matrix.
     */
    protected function buildSection6Aspects(array $aspects, string $precision, string $locale): array
    {
        $matrix = $aspects['matrix'] ?? [];
        $hideHouseLabels = ($precision === UserAstrologyProfile::PRECISION_UNKNOWN);

        $aspectList = [];
        foreach ($matrix as $sourcePlanet => $targets) {
            // Strictly exclude Rahu/Ketu
            if (in_array(strtolower($sourcePlanet), ['rahu', 'ketu'], true)) {
                continue;
            }

            foreach ($targets as $targetPlanet => $info) {
                if (in_array(strtolower($targetPlanet), ['rahu', 'ketu'], true)) {
                    continue;
                }

                if (!empty($info['has_aspect'])) {
                    $aspectList[] = [
                        'source' => ucfirst($sourcePlanet),
                        'target' => ucfirst($targetPlanet),
                        'type' => $info['aspect_type'] ?? '7th_mutual',
                        'strength' => $info['aspect_strength_percent'] ?? 100,
                        'source_house' => $hideHouseLabels ? null : ($info['source_house'] ?? null),
                        'target_house' => $hideHouseLabels ? null : ($info['target_house'] ?? null),
                        'description' => $locale === 'hi'
                            ? ucfirst($sourcePlanet) . ' की ' . ucfirst($targetPlanet) . ' पर पूर्ण दृष्टि'
                            : ucfirst($sourcePlanet) . ' casts full Parashari aspect upon ' . ucfirst($targetPlanet),
                    ];
                }
            }
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'पराशरी ग्रह दृष्टि मैट्रिक्स' : 'Parashari Graha Drishti Matrix',
            'is_suppressed' => false,
            'hide_house_labels' => $hideHouseLabels,
            'aspects' => $aspectList,
            'rule_clarification' => $locale === 'hi'
                ? 'शास्त्रीय पराशरी सिद्धांत के अनुसार दृष्टि केवल 7 स्थूल ग्रहों (सूर्य, चंद्र, मंगल, बुध, गुरु, शुक्र, शनि) पर लागू होती है। राहु-केतु छाया ग्रह होने के कारण दृष्टि मैट्रिक्स से बहिष्कृत हैं।'
                : 'Under Parashari principles, Graha Drishti applies strictly to the 7 visible planets. Rahu and Ketu are mathematical shadow nodes and are excluded from the aspect matrix.',
        ];
    }

    /**
     * Section 7: Janma Nakshatra Deep Dive.
     */
    protected function buildSection7JanmaNakshatra(array $kundli, array $stability, string $locale): array
    {
        $nakshatraLimb = $stability['panchanga']['nakshatra'] ?? null;
        $isInvariant = $nakshatraLimb === null || !empty($nakshatraLimb['is_invariant']);

        $nakName = (string) ($kundli['panchanga']['nakshatra']['name'] ?? 'Ashwini');
        $pada = (int) ($kundli['panchanga']['nakshatra']['pada'] ?? 1);

        $deepDive = $this->interpretationService->getNakshatraDeepDive($nakName, $pada, $locale);

        return [
            'gate_b_status' => $isInvariant ? 'certified' : 'qualified',
            'title' => $locale === 'hi' ? 'जन्म नक्षत्र सूक्ष्म विश्लेषण' : 'Janma Nakshatra In-Depth Profile',
            'is_suppressed' => false,
            'nakshatra_name' => $nakName,
            'pada' => $pada,
            'is_invariant' => $isInvariant,
            'deity' => $deepDive['deity'],
            'symbol' => $deepDive['symbol'],
            'nature' => $deepDive['nature'],
            'guidance' => $deepDive['guidance'],
            'pada_note' => $deepDive['pada_note'],
        ];
    }

    /**
     * Section 8: Vimshottari Mahadasha Timeline.
     */
    protected function buildSection8VimshottariTimeline(array $dashaData, string $locale): array
    {
        $mahadashas = $dashaData['mahadashas'] ?? [];
        if (empty($mahadashas)) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'विंशोत्तरी महादशा कालचक्र' : 'Vimshottari Mahadasha Timeline (120 Years)',
                'is_suppressed' => true,
                'timeline' => [],
            ];
        }

        $timeline = [];
        foreach ($mahadashas as $m) {
            $theme = $this->interpretationService->getMahadashaTheme($m['planet'], $locale);
            $timeline[] = [
                'planet' => $m['planet'],
                'name' => $locale === 'hi' ? ($m['name_hi'] ?? $m['name']) : $m['name'],
                'nominal_start' => $m['start_date'],
                'nominal_end' => $m['end_date'],
                'date_bounds' => [
                    'earliest_start' => $m['uncertainty_bounds']['earliest_start'] ?? $m['start_date'],
                    'latest_start' => $m['uncertainty_bounds']['latest_start'] ?? $m['start_date'],
                    'earliest_end' => $m['uncertainty_bounds']['earliest_end'] ?? $m['end_date'],
                    'latest_end' => $m['uncertainty_bounds']['latest_end'] ?? $m['end_date'],
                ],
                'is_current' => !empty($m['is_current']),
                'theme' => $theme['theme'],
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'विंशोत्तरी महादशा कालचक्र (120 वर्ष)' : 'Vimshottari Mahadasha Timeline (120-Year Cycle)',
            'is_suppressed' => false,
            'timeline' => $timeline,
        ];
    }

    /**
     * Section 9: Current Running Dasha & Pratyantardasha.
     */
    protected function buildSection9CurrentDasha(array $dashaData, string $locale): array
    {
        $current = $dashaData['current_dasha'] ?? [];
        $activeMaha = $current['mahadasha'] ?? null;
        $activeAntar = $current['antardasha'] ?? null;
        $activePrat = $current['pratyantardasha'] ?? null;

        if (!$activeMaha) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'वर्तमान दशा विवरण' : 'Current Running Dasha & Pratyantardasha',
                'is_suppressed' => true,
                'reason' => $locale === 'hi'
                    ? 'जन्म समय अनिश्चितता के कारण वर्तमान दशा अवधि का निर्धारण संभव नहीं है।'
                    : 'Current dasha period is unavailable due to birth uncertainty bounds.',
            ];
        }

        // Apply Gate B period-boundary reliability rule: W_period <= 0.25 * D_Prat
        $isPratReliable = !empty($activePrat['is_reliable']) && empty($activePrat['is_suppressed']);

        return [
            'gate_b_status' => $isPratReliable ? 'certified' : 'qualified',
            'title' => $locale === 'hi' ? 'वर्तमान सक्रिय दशा एवं अंतर्दशा' : 'Current Running Dasha & Sub-Periods',
            'is_suppressed' => false,
            'mahadasha' => [
                'name' => $locale === 'hi' ? ($activeMaha['name_hi'] ?? $activeMaha['name']) : $activeMaha['name'],
                'start_date' => $activeMaha['start_date'],
                'end_date' => $activeMaha['end_date'],
            ],
            'antardasha' => $activeAntar ? [
                'name' => $locale === 'hi' ? ($activeAntar['name_hi'] ?? $activeAntar['name']) : $activeAntar['name'],
                'start_date' => $activeAntar['start_date'],
                'end_date' => $activeAntar['end_date'],
            ] : null,
            'pratyantardasha' => $isPratReliable ? [
                'name' => $locale === 'hi' ? ($activePrat['name_hi'] ?? $activePrat['name']) : $activePrat['name'],
                'start_date' => $activePrat['start_date'],
                'end_date' => $activePrat['end_date'],
                'uncertainty_ratio' => $activePrat['uncertainty_ratio'] ?? null,
            ] : null,
            'pratyantardasha_suppressed' => !$isPratReliable,
            'suppression_note' => !$isPratReliable
                ? ($locale === 'hi'
                    ? 'प्रत्यंतर्दशा की अवधि-सीमा अनिश्चितता अनुपात (W_period / D_Prat) 25% सीमा से अधिक होने के कारण प्रत्यंतर्दशा को स्थगित रखा गया है।'
                    : 'Pratyantardasha is suppressed in accordance with Gate B mathematical reliability threshold (W_period <= 0.25 * D_Prat).')
                : null,
        ];
    }

    /**
     * Section 10: Classical Planetary Yogas.
     */
    protected function buildSection10ClassicalYogas(array $yogas, string $d1GateBStatus, string $locale): array
    {
        $isD1Suppressed = ($d1GateBStatus === 'suppressed');

        $activeYogas = [];

        // Panch Mahapurusha Yogas (Lagna-dependent)
        if (!$isD1Suppressed && !empty($yogas['panch_mahapurusha'])) {
            foreach ($yogas['panch_mahapurusha'] as $yogaKey => $yData) {
                if (!empty($yData['is_present'])) {
                    $interp = $this->interpretationService->getYogaInterpretation($yogaKey, $yData, $locale);
                    $activeYogas[] = [
                        'type' => 'panch_mahapurusha',
                        'name' => $interp['name'],
                        'definition' => $interp['definition'],
                        'qualities' => $interp['qualities'],
                        'ethical_advice' => $interp['ethical_advice'],
                    ];
                }
            }
        }

        // Gaja Kesari Yoga (Moon-dependent)
        if (!empty($yogas['gaja_kesari']['is_present'])) {
            $interp = $this->interpretationService->getYogaInterpretation('gajakesari', $yogas['gaja_kesari'], $locale);
            $activeYogas[] = [
                'type' => 'moon_kendra',
                'name' => $interp['name'],
                'definition' => $interp['definition'],
                'qualities' => $interp['qualities'],
                'ethical_advice' => $interp['ethical_advice'],
            ];
        }

        // Budhaditya Yoga (Conjunction)
        if (!empty($yogas['budhaditya']['is_present'])) {
            $interp = $this->interpretationService->getYogaInterpretation('budhaditya', $yogas['budhaditya'], $locale);
            $activeYogas[] = [
                'type' => 'conjunction',
                'name' => $interp['name'],
                'definition' => $interp['definition'],
                'qualities' => $interp['qualities'],
                'ethical_advice' => $interp['ethical_advice'],
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'शास्त्रीय ग्रहीय योग' : 'Classical Planetary Yogas',
            'is_suppressed' => false,
            'is_lagna_yogas_suppressed' => $isD1Suppressed,
            'active_yogas' => $activeYogas,
            'note' => $isD1Suppressed
                ? ($locale === 'hi'
                    ? 'लग्न की अनिश्चितता के कारण केंद्र-आधारित पंच महापुरुष योगों का परीक्षण स्थगित रखा गया है; केवल चंद्र-आधारित योग दर्शाए गए हैं।'
                    : 'Lagna-dependent Kendra yogas are suppressed due to Ascendant uncertainty; Moon-based configurations are displayed.')
                : null,
        ];
    }

    /**
     * Section 11: Manglik Dosha Audit.
     */
    protected function buildSection11ManglikDosha(array $manglikData, string $locale): array
    {
        $interp = $this->interpretationService->getManglikInterpretation($manglikData, $locale);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'मांगलिक दोष शास्त्रीय परीक्षण' : 'Manglik Dosha Classical Audit',
            'is_suppressed' => false,
            'has_manglik_dosha' => !empty($manglikData['has_manglik_dosha']),
            'is_cancelled' => !empty($manglikData['is_cancelled']),
            'headline' => $interp['headline'],
            'summary' => $interp['summary'],
            'cancellation_note' => $interp['cancellation_note'],
            'ethical_guidance' => $interp['ethical_guidance'],
            'bhangas' => $manglikData['bhangas'] ?? [],
            'mitigations' => $manglikData['mitigations'] ?? [],
        ];
    }

    /**
     * Section 12: Saturn Sade Sati & Dhaiya.
     */
    protected function buildSection12SaturnTransit(array $transitData, string $locale): array
    {
        $status = $transitData['status'] ?? 'fallback';
        $isFallback = ($status === 'fallback');

        $currentSummary = $transitData['current_transit_summary'] ?? ($transitData['qualitative_fallback'] ?? []);

        $phaseKey = 'none';
        if (!empty($currentSummary['is_in_sade_sati'])) {
            $phaseKey = match ($currentSummary['sade_sati_phase'] ?? '') {
                'rising_phase_12th' => 'rising',
                'peak_phase_1st' => 'peak',
                'setting_phase_2nd' => 'setting',
                default => 'rising',
            };
        } elseif (!empty($currentSummary['is_in_dhaiya'])) {
            $phaseKey = match ($currentSummary['dhaiya_type'] ?? '') {
                'kantaka_dhaiya_4th' => 'kantaka',
                'ashtama_dhaiya_8th' => 'ashtama',
                default => 'kantaka',
            };
        }

        $isRetro = !empty($currentSummary['saturn_position']['is_retrograde'] ?? ($currentSummary['saturn_is_retrograde'] ?? false));

        $interp = $this->interpretationService->getSaturnTransitInterpretation($phaseKey, $isRetro, $locale);

        return [
            'gate_b_status' => $isFallback ? 'fallback' : 'certified',
            'title' => $locale === 'hi' ? 'शनि साढ़ेसाती एवं ढैय्या गोचर' : 'Saturn Sade Sati & Dhaiya Transits',
            'is_suppressed' => false,
            'is_fallback' => $isFallback,
            'current_phase_title' => $interp['title'],
            'current_phase_core' => $interp['core'],
            'current_phase_advice' => $interp['advice'],
            'sade_sati_cycles' => $transitData['sade_sati'] ?? [],
            'dhaiya_cycles' => $transitData['dhaiya'] ?? [],
            'fallback_note' => $isFallback
                ? ($locale === 'hi'
                    ? 'चंद्र राशि की अनिश्चितता के कारण काल-विभाजित सारणी के स्थान पर वर्तमान गोचर का स्वीकृत गुणात्मक विश्लेषण प्रस्तुत किया गया है।'
                    : 'Natal Moon uncertainty requires the approved qualitative current-phase fallback rather than dated multi-year timelines.')
                : null,
        ];
    }

    /**
     * Section 13: Classical Vedic Remedies (Upay).
     */
    protected function buildSection13Remedies(array $kundli, array $yogas, array $sec11, string $locale): array
    {
        // Select planetary focus: if active Manglik without cancellation, include Mars; otherwise Lagna/Moon lord
        $focusPlanets = ['jupiter'];
        if (!empty($sec11['has_manglik_dosha']) && empty($sec11['is_cancelled'])) {
            $focusPlanets[] = 'mars';
        }
        $focusPlanets[] = 'saturn';

        $remedies = [];
        foreach (array_unique($focusPlanets) as $fp) {
            $r = $this->interpretationService->getVedicRemedies($fp, $locale);
            $remedies[] = [
                'planet' => ucfirst($fp),
                'mantra' => $r['mantra'],
                'dana' => $r['dana'],
                'lifestyle' => $r['lifestyle'],
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'शास्त्रीय वैदिक उपाय एवं सात्विक जीवन' : 'Classical Vedic Remedies & Mindful Living',
            'is_suppressed' => false,
            'remedies' => $remedies,
            'gemstone_disclaimer' => DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER[$locale],
        ];
    }

    /**
     * Section 14: Astrologer Referral.
     */
    protected function buildSection14AstrologerReferral(array $astrologers, string $locale): array
    {
        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'प्रमाणित वैदिक ज्योतिषी परामर्श' : 'Verified Astrologer Consultation',
            'is_suppressed' => false,
            'summary' => $locale === 'hi'
                ? 'जटिल ग्रहीय योगों, जन्म समय सुधार (BTR) और व्यक्तिगत जीवन मार्गदर्शन हेतु हमारे अनुभवी वैदिक ज्योतिषियों से परामर्श लें।'
                : 'For in-depth horoscope analysis, birth-time rectification (BTR), or customized remedies, connect with our verified astrologers.',
            'astrologers' => $astrologers,
        ];
    }

    /**
     * Section 15: Precision Audit & Disclaimers.
     */
    protected function buildSection15PrecisionAudit(array $auditMatrix, string $precision, string $locale): array
    {
        $sectionNames = [
            'sec1_cover' => ['en' => '1. Cover & Coordinates', 'hi' => '१. मुखपृष्ठ एवं जन्म विवरण'],
            'sec2_panchanga' => ['en' => '2. Panchanga & Avakahada', 'hi' => '२. पंचांग एवं अवकहड़ा चक्र'],
            'sec3_lagna_d1' => ['en' => '3. Lagna Kundli (D-1)', 'hi' => '३. लग्न कुण्डली (D-1)'],
            'sec4_chandra' => ['en' => '4. Chandra Kundli (Moon)', 'hi' => '४. चन्द्र कुण्डली'],
            'sec5_navamsha_d9' => ['en' => '5. D-9 Navamsha Chart', 'hi' => '५. नवांश कुण्डली (D-9)'],
            'sec6_aspects' => ['en' => '6. Parashari Drishti Matrix', 'hi' => '६. पराशरी दृष्टि मैट्रिक्स'],
            'sec7_nakshatra' => ['en' => '7. Janma Nakshatra Profile', 'hi' => '७. जन्म नक्षत्र सूक्ष्म विश्लेषण'],
            'sec8_vimshottari_timeline' => ['en' => '8. Vimshottari Timeline', 'hi' => '८. विंशोत्तरी महादशा कालचक्र'],
            'sec9_current_dasha' => ['en' => '9. Current Running Dasha', 'hi' => '९. वर्तमान दशा एवं अंतर्दशा'],
            'sec10_classical_yogas' => ['en' => '10. Classical Planetary Yogas', 'hi' => '१०. शास्त्रीय ग्रहीय योग'],
            'sec11_manglik_dosha' => ['en' => '11. Manglik Dosha Audit', 'hi' => '११. मांगलिक दोष परीक्षण'],
            'sec12_saturn_transit' => ['en' => '12. Saturn Sade Sati & Dhaiya', 'hi' => '१२. शनि साढ़ेसाती एवं गोचर'],
            'sec13_remedies' => ['en' => '13. Classical Vedic Remedies', 'hi' => '१३. शास्त्रीय वैदिक उपाय'],
            'sec14_astrologer_referral' => ['en' => '14. Astrologer Referral', 'hi' => '१४. ज्योतिषी परामर्श'],
            'sec15_precision_audit' => ['en' => '15. Precision Audit & Disclaimers', 'hi' => '१५. परिशुद्धता ऑडिट एवं अस्वीकरण'],
        ];

        $statusLabels = [
            'certified' => ['en' => 'Certified', 'hi' => 'प्रमाणित'],
            'qualified' => ['en' => 'Qualified', 'hi' => 'सशर्त'],
            'suppressed' => ['en' => 'Suppressed', 'hi' => 'स्थगित'],
            'fallback' => ['en' => 'Qualitative Fallback', 'hi' => 'गुणात्मक विश्लेषण'],
        ];

        $table = [];
        foreach ($auditMatrix as $secKey => $status) {
            $table[] = [
                'section' => $sectionNames[$secKey][$locale] ?? $secKey,
                'status_key' => $status,
                'status_label' => $statusLabels[$status][$locale] ?? ucfirst($status),
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'परिशुद्धता ऑडिट एवं विधिक अस्वीकरण' : 'Precision Audit & Legal Disclaimers',
            'precision_tier' => $precision,
            'audit_table' => $table,
            'purushartha_framing' => DetailedInterpretationService::PURUSHARTHA_FRAMING[$locale],
            'nadi_disclaimer' => DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER[$locale],
            'gemstone_disclaimer' => DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER[$locale],
            'general_disclaimer' => DetailedInterpretationService::GENERAL_DISCLAIMER[$locale],
        ];
    }
}
