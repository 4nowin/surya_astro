<?php

declare(strict_types=1);

namespace App\Services\Astrology\Yogas;

use App\Services\Astrology\Aspects\ParashariAspectService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;

/**
 * Classical Manglik Dosha (Kuja Dosha) & Structural Bhanga Evaluation Service.
 *
 * Implements Work Package A2 of Astro Setu Milestone M9.2.
 * Classical Sources:
 * - Muhurta Chintamani, Vivaha Prakarana, Verse 32.
 * - Brihat Parashara Hora Shastra (BPHS).
 * - Jataka Parijata.
 *
 * Core Principles:
 * - Evaluated primarily from the natal Ascendant (Lagna) for Houses 1, 4, 7, 8, 12.
 * - Secondary evaluation from natal Moon (Chandra Manglik) reported separately.
 * - 5 verified classical sign-and-house structural cancellations (Dosha Bhanga).
 * - Full 12-sign Jupiter functional-lordship rules for planetary aspect cancellation:
 *   - Eligible Lagnas: Aries (1), Cancer (4), Leo (5), Scorpio (8), Sagittarius (9), Pisces (12).
 *   - Excluded Lagnas: Taurus (2), Gemini (3), Virgo (6), Libra (7), Capricorn (10), Aquarius (11).
 * - Debilitation rule: Mars in Cancer (4) in 1, 4, 7, 8, 12 is NOT a cancellation; remains formed.
 * - Traditional mitigations (Chandra-Mangala and Age > 28) classified separately from structural cancellations.
 */
class ManglikDoshaService
{
    public const MANGLIK_HOUSES = [1, 4, 7, 8, 12];

    /**
     * Complete 12-sign Jupiter Functional-Lordship Classification (BPHS / Frozen M9.2 Audit).
     */
    public const JUPITER_FUNCTIONAL_CLASSIFICATION = [
        1  => ['status' => 'functional_benefic', 'eligible' => true,  'reason' => 'Lord of 9th (Moolatrikona) & 12th; 9th dominates 12th.'],
        2  => ['status' => 'functional_malefic', 'eligible' => false, 'reason' => 'Lord of 8th (Moolatrikona) & 11th; severe Dusthana/Upachaya.'],
        3  => ['status' => 'neutral_malefic',    'eligible' => false, 'reason' => 'Lord of 7th & 10th; severe Kendradhipati Dosha.'],
        4  => ['status' => 'functional_benefic', 'eligible' => true,  'reason' => 'Lord of 9th (Trikona) & 6th; Jupiter exalted in Lagna.'],
        5  => ['status' => 'functional_benefic', 'eligible' => true,  'reason' => 'Lord of 5th (Moolatrikona/Trikona) & 8th; intimate friend of Sun.'],
        6  => ['status' => 'neutral_malefic',    'eligible' => false, 'reason' => 'Lord of 4th & 7th; Kendradhipati Dosha; enemy of Mercury.'],
        7  => ['status' => 'functional_malefic', 'eligible' => false, 'reason' => 'Lord of 3rd (Moolatrikona) & 6th (Rogasthana Dusthana).'],
        8  => ['status' => 'functional_benefic', 'eligible' => true,  'reason' => 'Lord of 2nd & 5th (Trikona); intimate friend of Mars.'],
        9  => ['status' => 'functional_benefic', 'eligible' => true,  'reason' => 'Lagna Lord (1st) & 4th; supreme benefic for own sign.'],
        10 => ['status' => 'functional_malefic', 'eligible' => false, 'reason' => 'Lord of 12th (Moolatrikona) & 3rd; bitter enemy of Saturn.'],
        11 => ['status' => 'functional_malefic', 'eligible' => false, 'reason' => 'Lord of 11th (Moolatrikona) & 2nd; major Upachaya/Dusthana.'],
        12 => ['status' => 'functional_benefic', 'eligible' => true,  'reason' => 'Lagna Lord (1st) & 10th; supreme benefic for own sign.'],
    ];

    protected ParashariAspectService $aspectService;

    public function __construct(?ParashariAspectService $aspectService = null)
    {
        $this->aspectService = $aspectService ?? new ParashariAspectService();
    }

    /**
     * Comprehensive evaluation of Manglik Dosha and its verified classical Bhangas.
     *
     * @param array<string, array<string, mixed>> $planets Array of celestial positions
     * @param array<string, mixed>|int|null $lagnaRashiId Natal Ascendant sign ID (1..12) or ascendant data array
     * @param DateTimeInterface|string|int|null $birthDateOrAge YYYY-MM-DD birth date or integer age in years
     * @param DateTimeInterface|string|null $targetDate Target evaluation date (defaults to now)
     * @param array<string, mixed> $stabilityData Output from A1 AstrologyIntervalStabilityService
     * @return array<string, mixed> Structured Manglik analysis
     */
    public function evaluate(
        array $planets,
        array|int|null $lagnaRashiId = null,
        DateTimeInterface|string|int|null $birthDateOrAge = null,
        DateTimeInterface|string|null $targetDate = null,
        array $stabilityData = []
    ): array {
        if (is_array($lagnaRashiId)) {
            $ascData = $lagnaRashiId;
            $lagnaRashiId = (isset($ascData['status']) && in_array($ascData['status'], ['suppressed', 'unavailable'], true))
                ? null
                : ($ascData['id'] ?? null);
            if (empty($stabilityData) && isset($ascData['is_invariant'])) {
                $stabilityData['d1_ascendant'] = $ascData;
            }
        }

        $mars = $planets['mars'] ?? null;
        $moon = $planets['moon'] ?? null;
        $jupiter = $planets['jupiter'] ?? null;

        if (!$mars) {
            return $this->buildMissingMarsResult();
        }

        $marsSignId = $this->extractRashiId($mars);
        if ($marsSignId === null) {
            return $this->buildMissingMarsResult();
        }

        $moonSignId = $moon ? $this->extractRashiId($moon) : null;
        $jupiterSignId = $jupiter ? $this->extractRashiId($jupiter) : null;

        // 1. Primary Lagna-Based House Placement
        $marsHouse = null;
        $isLagnaManglik = false;

        if ($lagnaRashiId !== null) {
            $marsHouse = (($marsSignId - $lagnaRashiId + 12) % 12) + 1;
            $isLagnaManglik = in_array($marsHouse, self::MANGLIK_HOUSES, true);
        }

        // 2. Secondary Moon-Based House Placement (Chandra Manglik)
        $marsHouseFromMoon = null;
        $isMoonManglik = false;

        if ($moonSignId !== null) {
            $marsHouseFromMoon = (($marsSignId - $moonSignId + 12) % 12) + 1;
            $isMoonManglik = in_array($marsHouseFromMoon, self::MANGLIK_HOUSES, true);
        }

        // 3. Evaluate the 5 Verified Classical Structural Bhangas
        $bhangaResults = [];
        $appliedBhangas = [];

        if ($lagnaRashiId !== null && $marsHouse !== null) {
            $bhangaResults = $this->evaluateStructuralBhangas($marsSignId, $marsHouse);
            foreach ($bhangaResults as $b) {
                if ($b['applies']) {
                    $appliedBhangas[] = $b;
                }
            }
        }

        // 4. Evaluate Jupiter Functional Benefic Aspect Rule (Bhanga 6)
        $jupiterAspectResult = $this->evaluateJupiterAspectCancellation($jupiterSignId, $marsSignId, $lagnaRashiId);
        if ($jupiterAspectResult['cancels_dosha']) {
            $appliedBhangas[] = [
                'rule_id' => 'BHANGA_JUPITER_ASPECT',
                'name' => 'Jupiter Functional Benefic Aspect',
                'source' => 'Classical Parashari Graha Drishti Rulebook',
                'description' => "Mars is aspected by Jupiter ({$jupiterAspectResult['aspect_type']}) where Jupiter is a functional benefic for {$jupiterAspectResult['lagna_name']} Ascendant.",
                'applies' => true,
            ];
        }

        $hasClassicalCancellation = !empty($appliedBhangas);

        // 5. Evaluate Traditional Mitigations (Separate from Structural Bhangas)
        $chandraMangalaMitigation = ($moonSignId !== null && $marsSignId === $moonSignId);

        if (is_int($birthDateOrAge)) {
            $ageMitigation = [
                'applies' => ($birthDateOrAge > 28),
                'age' => $birthDateOrAge,
            ];
        } elseif (is_numeric($birthDateOrAge) && !str_contains((string) $birthDateOrAge, '-')) {
            $ageVal = (int) $birthDateOrAge;
            $ageMitigation = [
                'applies' => ($ageVal > 28),
                'age' => $ageVal,
            ];
        } else {
            $ageMitigation = $this->evaluateAgeMitigation($birthDateOrAge ? (string) $birthDateOrAge : null, $targetDate);
        }

        // 6. Final Classification
        $finalStatus = 'no_dosha';
        $finalStatusHi = 'दोष नहीं';

        if ($isLagnaManglik) {
            if ($hasClassicalCancellation) {
                $finalStatus = 'dosha_cancelled';
                $finalStatusHi = 'मांगलिक दोष भंग (रद्द)';
            } else {
                $finalStatus = 'dosha_confirmed';
                $finalStatusHi = 'मांगलिक दोष पुष्ट';
            }
        }

        // 7. Interval Stability Analysis
        $d1AscStability = $stabilityData['d1_ascendant'] ?? null;
        $isLagnaInvariant = ($d1AscStability !== null)
            ? ($d1AscStability['is_invariant'] ?? false)
            : ($lagnaRashiId !== null);

        $stabilityStatus = 'confirmed';
        $stabilityReason = 'Evaluated with confirmed birth-time coordinates.';
        $candidateEvaluations = [];

        $precision = $stabilityData['birth_time_precision']
            ?? $stabilityData['interval']['precision']
            ?? ($ascData['precision'] ?? null);

        $isUnknownPrecision = ($precision === 'unknown')
            || ($lagnaRashiId === null && empty($d1AscStability['candidate_signs']))
            || (isset($ascData['status']) && in_array($ascData['status'], ['suppressed', 'unavailable'], true) && empty($d1AscStability['candidate_signs']));

        if ($isUnknownPrecision) {
            $stabilityStatus = 'suppressed_unknown_lagna';
            $stabilityReason = 'Birth time unknown; Lagna Manglik cannot be certified.';
            $finalStatus = 'suppressed';
            $finalStatusHi = 'अज्ञात जन्म समय के कारण स्थगित';
        } elseif (!$isLagnaInvariant && $d1AscStability && !empty($d1AscStability['candidate_signs'])) {
            $candidateSigns = array_unique(array_column($d1AscStability['candidate_signs'], 'id'));
            $candidateStatuses = [];

            foreach ($candidateSigns as $candLagnaId) {
                $candLagnaId = (int) $candLagnaId;
                $candMarsHouse = (($marsSignId - $candLagnaId + 12) % 12) + 1;
                $candIsManglik = in_array($candMarsHouse, self::MANGLIK_HOUSES, true);
                $candBhangas = $candIsManglik ? $this->evaluateStructuralBhangas($marsSignId, $candMarsHouse) : [];
                $candJup = $this->evaluateJupiterAspectCancellation($jupiterSignId, $marsSignId, $candLagnaId);

                $candHasBhanga = false;
                foreach ($candBhangas as $cb) {
                    if ($cb['applies']) {
                        $candHasBhanga = true;
                        break;
                    }
                }
                if ($candJup['cancels_dosha']) {
                    $candHasBhanga = true;
                }

                $candStatus = 'no_dosha';
                if ($candIsManglik) {
                    $candStatus = $candHasBhanga ? 'dosha_cancelled' : 'dosha_confirmed';
                }

                $candidateStatuses[] = $candStatus;
                $candidateEvaluations[] = [
                    'lagna_id' => $candLagnaId,
                    'lagna_name' => Rasi::from($candLagnaId - 1)->getEnglishName(),
                    'mars_house' => $candMarsHouse,
                    'is_manglik_formed' => $candIsManglik,
                    'status' => $candStatus,
                ];
            }

            $uniqueStatuses = array_unique($candidateStatuses);
            if (count($uniqueStatuses) === 1) {
                $stabilityStatus = 'confirmed';
                $stabilityReason = 'Manglik classification invariant across all candidate Ascendants in uncertainty window.';
                $finalStatus = $uniqueStatuses[0];
            } else {
                $stabilityStatus = 'uncertain_lagna_variation';
                $stabilityReason = 'Manglik formation or cancellation varies across candidate Ascendants in uncertainty window.';
                $finalStatus = 'uncertain_variation';
                $finalStatusHi = 'विभिन्न लग्न संभावनाओं के कारण अनिश्चित';
            }
        }

        $marsSignEnum = Rasi::from($marsSignId - 1);
        $lagnaSignEnum = ($lagnaRashiId !== null) ? Rasi::from($lagnaRashiId - 1) : null;
        $moonSignEnum = ($moonSignId !== null) ? Rasi::from($moonSignId - 1) : null;

        return [
            'is_manglik' => $isLagnaManglik,
            'is_manglik_formed' => $isLagnaManglik,
            'source_house' => $marsHouse,
            'final_status' => $finalStatus,
            'final_status_hi' => $finalStatusHi,
            'has_cancellation' => $hasClassicalCancellation,
            'is_debilitated' => ($marsSignId === 4),
            'applied_bhanga_ids' => array_column($appliedBhangas, 'rule_id'),
            'bhangas' => $appliedBhangas,
            'lagna' => $lagnaSignEnum ? [
                'id' => $lagnaRashiId,
                'name' => $lagnaSignEnum->getEnglishName(),
                'vedic_name' => $lagnaSignEnum->getName(),
            ] : null,
            'mars' => [
                'sign_id' => $marsSignId,
                'sign_name' => $marsSignEnum->getEnglishName(),
                'sign_vedic_name' => $marsSignEnum->getName(),
                'house_from_lagna' => $marsHouse,
                'is_in_manglik_house' => $isLagnaManglik,
                'is_debilitated' => ($marsSignId === 4), // Cancer
                'is_own_sign' => in_array($marsSignId, [1, 8], true),
                'is_exalted' => ($marsSignId === 10),
            ],
            'chandra_manglik' => [
                'is_formed' => $isMoonManglik,
                'mars_house_from_moon' => $marsHouseFromMoon,
                'moon_sign' => $moonSignEnum ? [
                    'id' => $moonSignId,
                    'name' => $moonSignEnum->getEnglishName(),
                    'vedic_name' => $moonSignEnum->getName(),
                ] : null,
                'note' => 'Chandra Manglik evaluated as secondary diagnostic reference.',
            ],
            'cancellation' => [
                'has_classical_cancellation' => $hasClassicalCancellation,
                'applied_bhangas_count' => count($appliedBhangas),
                'applied_bhangas' => $appliedBhangas,
                'all_bhangas_evaluated' => $bhangaResults,
                'jupiter_aspect_cancellation' => $jupiterAspectResult,
            ],
            'traditional_mitigations' => [
                'chandra_mangala_yoga' => [
                    'present' => $chandraMangalaMitigation,
                    'description' => $chandraMangalaMitigation
                        ? 'Mars and Moon are conjunct in the same sign (Chandra-Mangala Yoga), mitigating financial stress (traditional behavioral mitigation, not structural cancellation).'
                        : 'No Chandra-Mangala conjunction.',
                ],
                'age_maturity' => [
                    'applies' => $ageMitigation['applies'],
                    'age' => $ageMitigation['age'],
                    'description' => $ageMitigation['applies']
                        ? "Native age is {$ageMitigation['age']} (> 28 years); classical maturity softens impulsive Martian tendencies (traditional psychological mitigation, not structural chart cancellation)."
                        : "Native age is {$ageMitigation['age']}; age maturity mitigation does not apply.",
                ],
            ],
            'stability' => [
                'status' => $stabilityStatus,
                'reason' => $stabilityReason,
                'is_lagna_invariant' => $isLagnaInvariant,
                'candidate_evaluations' => $candidateEvaluations,
            ],
        ];
    }

    /**
     * Evaluate the 5 verified classical structural Bhanga rules from BPHS & Muhurta Chintamani.
     *
     * @return list<array{rule_id: string, name: string, source: string, description: string, applies: bool}>
     */
    public function evaluateStructuralBhangas(int $marsSignId, int $marsHouse): array
    {
        return [
            [
                'rule_id' => 'BHANGA_1_ARIES_1ST',
                'name' => 'Mars in Aries in 1st House',
                'source' => 'Muhurta Chintamani, Vivaha Prakarana, Verse 32',
                'description' => 'Mars occupies its own positive sign (Aries) in the 1st house (Lagna).',
                'applies' => ($marsSignId === 1 && $marsHouse === 1),
            ],
            [
                'rule_id' => 'BHANGA_2_SCORPIO_4TH',
                'name' => 'Mars in Scorpio in 4th House',
                'source' => 'Muhurta Chintamani, Vivaha Prakarana, Verse 32',
                'description' => 'Mars occupies its own negative sign (Scorpio) in the 4th house (Sukhasthana).',
                'applies' => ($marsSignId === 8 && $marsHouse === 4),
            ],
            [
                'rule_id' => 'BHANGA_3_CAPRICORN_7TH_8TH',
                'name' => 'Mars in Capricorn in 7th or 8th House',
                'source' => 'Jataka Parijata; Muhurta Chintamani',
                'description' => 'Mars occupies its highest exaltation sign (Capricorn / Makara) in the 7th or 8th house.',
                'applies' => ($marsSignId === 10 && in_array($marsHouse, [7, 8], true)),
            ],
            [
                'rule_id' => 'BHANGA_4_JUPITER_SIGNS_8TH',
                'name' => 'Mars in Sagittarius or Pisces in 8th House',
                'source' => 'Muhurta Chintamani, Vivaha Prakarana, Verse 32',
                'description' => 'Mars occupies Jupiterian signs (Sagittarius or Pisces) in the 8th house (Ayusthana/Randhrasthana).',
                'applies' => (in_array($marsSignId, [9, 12], true) && $marsHouse === 8),
            ],
            [
                'rule_id' => 'BHANGA_5_LEO_1ST',
                'name' => 'Mars in Leo in 1st House',
                'source' => 'BPHS; Classical Yogakaraka Principles',
                'description' => 'Mars occupies the friendly sign Leo in the 1st house (Mars is Yogakaraka for Leo Ascendant).',
                'applies' => ($marsSignId === 5 && $marsHouse === 1),
            ],
        ];
    }

    /**
     * Evaluate Jupiter aspect cancellation with complete 12-sign functional-lordship rules.
     *
     * @return array{
     *     does_aspect_mars: bool,
     *     aspect_type: ?string,
     *     lagna_id: ?int,
     *     lagna_name: ?string,
     *     is_eligible_functional_benefic: bool,
     *     functional_status: ?string,
     *     cancels_dosha: bool,
     *     reason: string
     * }
     */
    public function evaluateJupiterAspectCancellation(
        ?int $jupiterSignId,
        int $marsSignId,
        ?int $lagnaRashiId
    ): array {
        if ($jupiterSignId === null) {
            return [
                'does_aspect_mars' => false,
                'aspect_type' => null,
                'lagna_id' => $lagnaRashiId,
                'lagna_name' => $lagnaRashiId ? Rasi::from($lagnaRashiId - 1)->getEnglishName() : null,
                'is_eligible_functional_benefic' => false,
                'functional_status' => null,
                'cancels_dosha' => false,
                'reason' => 'Jupiter position not available.',
            ];
        }

        // Check if Jupiter casts 5th, 7th, or 9th aspect on Mars
        $aspectCheck = $this->aspectService->doesPlanetAspectSign('jupiter', $jupiterSignId, $marsSignId);
        $doesAspect = $aspectCheck['does_aspect'];
        $aspectType = $aspectCheck['aspect_type'];

        $lagnaName = $lagnaRashiId ? Rasi::from($lagnaRashiId - 1)->getEnglishName() : null;
        $funcInfo = $lagnaRashiId ? (self::JUPITER_FUNCTIONAL_CLASSIFICATION[$lagnaRashiId] ?? null) : null;

        $isEligible = (bool) ($funcInfo['eligible'] ?? false);
        $funcStatus = $funcInfo['status'] ?? 'unknown';

        if (!$doesAspect) {
            return [
                'does_aspect_mars' => false,
                'aspect_type' => null,
                'lagna_id' => $lagnaRashiId,
                'lagna_name' => $lagnaName,
                'is_eligible_functional_benefic' => $isEligible,
                'functional_status' => $funcStatus,
                'cancels_dosha' => false,
                'reason' => 'Jupiter does not cast a full Parashari aspect (5th, 7th, or 9th) upon Mars.',
            ];
        }

        // Jupiter aspects Mars: Check functional lordship eligibility
        if ($isEligible) {
            return [
                'does_aspect_mars' => true,
                'aspect_type' => $aspectType,
                'lagna_id' => $lagnaRashiId,
                'lagna_name' => $lagnaName,
                'is_eligible_functional_benefic' => true,
                'functional_status' => $funcStatus,
                'cancels_dosha' => true,
                'reason' => "Jupiter casts full {$aspectType} aspect upon Mars and is a verified Functional Benefic for {$lagnaName} Ascendant ({$funcInfo['reason']}). Structural Kuja Dosha is cancelled.",
            ];
        }

        return [
            'does_aspect_mars' => true,
            'aspect_type' => $aspectType,
            'lagna_id' => $lagnaRashiId,
            'lagna_name' => $lagnaName,
            'is_eligible_functional_benefic' => false,
            'functional_status' => $funcStatus,
            'cancels_dosha' => false,
            'reason' => "Jupiter casts full {$aspectType} aspect upon Mars, but Jupiter is NOT a functional benefic for {$lagnaName} Ascendant ({$funcInfo['reason']}). Aspect cancellation is excluded.",
        ];
    }

    /**
     * Traditional Age Mitigation (> 28 Years).
     *
     * @return array{applies: bool, age: ?int}
     */
    protected function evaluateAgeMitigation(?string $birthDate, DateTimeInterface|string|null $targetDate): array
    {
        if (!$birthDate) {
            return ['applies' => false, 'age' => null];
        }

        try {
            $birth = CarbonImmutable::parse($birthDate);
            $target = ($targetDate === null)
                ? CarbonImmutable::now('UTC')
                : (($targetDate instanceof DateTimeInterface)
                    ? CarbonImmutable::instance($targetDate)
                    : CarbonImmutable::parse((string) $targetDate));

            $age = (int) $birth->diffInYears($target);
            return [
                'applies' => ($age > 28),
                'age' => $age,
            ];
        } catch (\Throwable) {
            return ['applies' => false, 'age' => null];
        }
    }

    protected function buildMissingMarsResult(): array
    {
        return [
            'is_manglik_formed' => false,
            'source_house' => null,
            'final_status' => 'missing_data',
            'final_status_hi' => 'डेटा अनुपलब्ध',
            'lagna' => null,
            'mars' => null,
            'chandra_manglik' => [
                'is_formed' => false,
                'mars_house_from_moon' => null,
                'moon_sign' => null,
            ],
            'cancellation' => [
                'has_classical_cancellation' => false,
                'applied_bhangas_count' => 0,
                'applied_bhangas' => [],
                'all_bhangas_evaluated' => [],
                'jupiter_aspect_cancellation' => null,
            ],
            'traditional_mitigations' => [
                'chandra_mangala_yoga' => ['present' => false],
                'age_maturity' => ['applies' => false, 'age' => null],
            ],
            'stability' => [
                'status' => 'missing_data',
                'reason' => 'Mars planetary coordinates not available.',
                'is_lagna_invariant' => false,
                'candidate_evaluations' => [],
            ],
        ];
    }

    protected function extractRashiId(array $planet): ?int
    {
        if (isset($planet['rashi']['id'])) {
            return (int) $planet['rashi']['id'];
        }
        if (isset($planet['rashi_id'])) {
            return (int) $planet['rashi_id'];
        }
        if (isset($planet['rashi']['index'])) {
            return ((int) $planet['rashi']['index']) + 1;
        }
        if (isset($planet['longitude'])) {
            return ((int) floor((float) $planet['longitude'] / 30.0)) + 1;
        }
        return null;
    }
}
