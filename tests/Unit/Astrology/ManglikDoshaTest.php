<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Services\Astrology\Yogas\ManglikDoshaService;
use App\Services\Astrology\Yogas\PanchMahapurushaYogaService;
use PHPUnit\Framework\TestCase;

class ManglikDoshaTest extends TestCase
{
    protected ManglikDoshaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ManglikDoshaService();
    }

    public function test_manglik_formation_in_houses_1_4_7_8_12(): void
    {
        // Lagna = Aries (1)
        // Mars in signs 1 (H1), 4 (H4), 7 (H7), 8 (H8), 12 (H12) -> formed
        $manglikSigns = [1, 4, 7, 8, 12];
        foreach ($manglikSigns as $marsSign) {
            $planets = ['mars' => ['rashi_id' => $marsSign]];
            $res = $this->service->evaluate($planets, 1);
            $this->assertTrue($res['is_manglik_formed'], "Mars in sign {$marsSign} for Aries Lagna must form Manglik Dosha");
        }

        // Non-Manglik houses: signs 2, 3, 5, 6, 9, 10, 11
        $nonManglikSigns = [2, 3, 5, 6, 9, 10, 11];
        foreach ($nonManglikSigns as $marsSign) {
            $planets = ['mars' => ['rashi_id' => $marsSign]];
            $res = $this->service->evaluate($planets, 1);
            $this->assertFalse($res['is_manglik_formed'], "Mars in sign {$marsSign} for Aries Lagna must NOT form Manglik Dosha");
        }
    }

    public function test_secondary_chandra_manglik_evaluated_independently(): void
    {
        // Lagna = Taurus (2), Moon = Leo (5), Mars = Scorpio (8)
        // From Lagna (2): Mars in 8 -> House 7 (Manglik from Lagna)
        // From Moon (5): Mars in 8 -> House 4 (Manglik from Moon)
        $planets = [
            'mars' => ['rashi_id' => 8],
            'moon' => ['rashi_id' => 5],
        ];

        $res = $this->service->evaluate($planets, 2);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertSame(7, $res['source_house']);
        $this->assertTrue($res['chandra_manglik']['is_formed']);
        $this->assertSame(4, $res['chandra_manglik']['mars_house_from_moon']);
    }

    // ==========================================
    // MANDATORY REGRESSION CASES (1 THROUGH 9)
    // ==========================================

    public function test_mandatory_regression_1_mars_in_aries_in_1st_house_ruchaka_formed_and_manglik_cancelled(): void
    {
        // Lagna = Aries (1), Mars in Aries (1)
        // Bhanga 1: Mars in Aries in 1st House
        // Also forms Ruchaka Yoga!
        $planets = [
            'mars' => ['rashi_id' => 1, 'longitude' => 15.0, 'is_combust' => false, 'is_retrograde' => false],
        ];

        // 1. Evaluate Manglik
        $manglikRes = $this->service->evaluate($planets, 1);
        $this->assertTrue($manglikRes['is_manglik_formed'], 'Structurally formed in 1st house');
        $this->assertTrue($manglikRes['has_cancellation'], 'Bhanga 1 applies');
        $this->assertSame('dosha_cancelled', $manglikRes['final_status']);
        $this->assertContains('BHANGA_1_ARIES_1ST', $manglikRes['applied_bhanga_ids']);

        // 2. Evaluate Ruchaka Yoga via PanchMahapurushaYogaService
        $pmpService = new PanchMahapurushaYogaService();
        $pmpRes = $pmpService->evaluate($planets, ['id' => 1, 'is_invariant' => true, 'status' => 'confirmed']);
        $this->assertTrue($pmpRes['details']['ruchaka']['is_formed'], 'Ruchaka Yoga must be formed');
    }

    public function test_mandatory_regression_2_mars_in_scorpio_in_4th_house_cancelled(): void
    {
        // Lagna = Leo (5), Mars in Scorpio (8) -> House 4
        // Bhanga 2: Mars in Scorpio in 4th House
        $planets = ['mars' => ['rashi_id' => 8]];
        $res = $this->service->evaluate($planets, 5);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertSame(4, $res['source_house']);
        $this->assertTrue($res['has_cancellation']);
        $this->assertSame('dosha_cancelled', $res['final_status']);
        $this->assertContains('BHANGA_2_SCORPIO_4TH', $res['applied_bhanga_ids']);
    }

    public function test_mandatory_regression_3_mars_in_capricorn_in_7th_and_8th_house_cancelled(): void
    {
        // Part A: Lagna = Cancer (4), Mars in Capricorn (10) -> House 7
        // Bhanga 3: Mars in Capricorn in 7th House
        $planetsA = ['mars' => ['rashi_id' => 10]];
        $resA = $this->service->evaluate($planetsA, 4);

        $this->assertTrue($resA['is_manglik_formed']);
        $this->assertSame(7, $resA['source_house']);
        $this->assertTrue($resA['has_cancellation']);
        $this->assertSame('dosha_cancelled', $resA['final_status']);
        $this->assertContains('BHANGA_3_CAPRICORN_7TH_8TH', $resA['applied_bhanga_ids']);

        // Part B: Lagna = Gemini (3), Mars in Capricorn (10) -> House 8
        // Bhanga 3: Mars in Capricorn in 8th House
        $planetsB = ['mars' => ['rashi_id' => 10]];
        $resB = $this->service->evaluate($planetsB, 3);

        $this->assertTrue($resB['is_manglik_formed']);
        $this->assertSame(8, $resB['source_house']);
        $this->assertTrue($resB['has_cancellation']);
        $this->assertSame('dosha_cancelled', $resB['final_status']);
        $this->assertContains('BHANGA_3_CAPRICORN_7TH_8TH', $resB['applied_bhanga_ids']);
    }

    public function test_mandatory_regression_4_mars_in_sagittarius_or_pisces_in_8th_house_cancelled(): void
    {
        // Part A: Lagna = Taurus (2), Mars in Sagittarius (9) -> House 8
        // Bhanga 4: Mars in Sagittarius in 8th House
        $planetsA = ['mars' => ['rashi_id' => 9]];
        $resA = $this->service->evaluate($planetsA, 2);

        $this->assertTrue($resA['is_manglik_formed']);
        $this->assertSame(8, $resA['source_house']);
        $this->assertTrue($resA['has_cancellation']);
        $this->assertSame('dosha_cancelled', $resA['final_status']);
        $this->assertContains('BHANGA_4_JUPITER_SIGNS_8TH', $resA['applied_bhanga_ids']);

        // Part B: Lagna = Leo (5), Mars in Pisces (12) -> House 8
        // Bhanga 4: Mars in Pisces in 8th House
        $planetsB = ['mars' => ['rashi_id' => 12]];
        $resB = $this->service->evaluate($planetsB, 5);

        $this->assertTrue($resB['is_manglik_formed']);
        $this->assertSame(8, $resB['source_house']);
        $this->assertTrue($resB['has_cancellation']);
        $this->assertSame('dosha_cancelled', $resB['final_status']);
        $this->assertContains('BHANGA_4_JUPITER_SIGNS_8TH', $resB['applied_bhanga_ids']);
    }

    public function test_mandatory_regression_5_mars_in_leo_in_1st_house_cancelled(): void
    {
        // Lagna = Leo (5), Mars in Leo (5) -> House 1
        // Bhanga 5: Mars in Leo in 1st House
        $planets = ['mars' => ['rashi_id' => 5]];
        $res = $this->service->evaluate($planets, 5);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertSame(1, $res['source_house']);
        $this->assertTrue($res['has_cancellation']);
        $this->assertSame('dosha_cancelled', $res['final_status']);
        $this->assertContains('BHANGA_5_LEO_1ST', $res['applied_bhanga_ids']);
    }

    public function test_mandatory_regression_6_mars_in_cancer_in_1st_house_not_cancelled(): void
    {
        // Lagna = Cancer (4), Mars in Cancer (4) -> House 1 (Debilitated)
        // Debilitation in Cancer is NOT a cancellation
        $planets = ['mars' => ['rashi_id' => 4]];
        $res = $this->service->evaluate($planets, 4);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertSame(1, $res['source_house']);
        $this->assertTrue($res['is_debilitated']);
        $this->assertFalse($res['has_cancellation']);
        $this->assertSame('dosha_confirmed', $res['final_status']);
    }

    public function test_mandatory_regression_7_mars_in_cancer_in_4th_house_not_cancelled(): void
    {
        // Lagna = Aries (1), Mars in Cancer (4) -> House 4 (Debilitated)
        // Debilitation in Cancer is NOT a cancellation
        $planets = ['mars' => ['rashi_id' => 4]];
        $res = $this->service->evaluate($planets, 1);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertSame(4, $res['source_house']);
        $this->assertTrue($res['is_debilitated']);
        $this->assertFalse($res['has_cancellation']);
        $this->assertSame('dosha_confirmed', $res['final_status']);
    }

    public function test_mandatory_regression_8_jupiter_aspect_cancellation_on_all_six_eligible_lagnas(): void
    {
        // The 6 eligible functional benefic Lagnas for Jupiter:
        // Aries (1), Cancer (4), Leo (5), Scorpio (8), Sagittarius (9), Pisces (12)
        $eligibleLagnas = [1, 4, 5, 8, 9, 12];

        foreach ($eligibleLagnas as $lagnaId) {
            // Place Mars in a Manglik house that has NO sign-based Bhanga:
            // e.g. Mars in Gemini (3) or Virgo (6) or Aquarius (11)
            // Let's dynamically calculate a Manglik house with a neutral sign:
            // e.g. House 12: sign = ((lagnaId + 12 - 2) % 12) + 1
            $marsHouse = 12;
            $marsSign = (($lagnaId - 1 + ($marsHouse - 1)) % 12) + 1;

            // Make sure this isn't a sign-based Bhanga (Aries in 1, Scorpio in 4, Cap in 7/8, Sgr/Psc in 8, Leo in 1)
            // Now place Jupiter such that it aspects Mars via 7th aspect:
            $jupiterSign = (($marsSign - 1 + 6) % 12) + 1; // 7th aspect

            $planets = [
                'mars' => ['rashi_id' => $marsSign],
                'jupiter' => ['rashi_id' => $jupiterSign],
            ];

            $res = $this->service->evaluate($planets, $lagnaId);

            $this->assertTrue($res['is_manglik_formed'], "Mars in house {$marsHouse} must be Manglik for Lagna {$lagnaId}");
            $this->assertTrue($res['has_cancellation'], "Jupiter aspect must cancel Manglik for eligible Lagna {$lagnaId}");
            $this->assertSame('dosha_cancelled', $res['final_status']);
            $this->assertContains('BHANGA_JUPITER_ASPECT', $res['applied_bhanga_ids']);
        }
    }

    public function test_mandatory_regression_9_jupiter_aspect_excluded_on_all_six_excluded_lagnas(): void
    {
        // The 6 excluded Lagnas where Jupiter is NOT a functional benefic:
        // Taurus (2), Gemini (3), Virgo (6), Libra (7), Capricorn (10), Aquarius (11)
        $excludedLagnas = [2, 3, 6, 7, 10, 11];

        foreach ($excludedLagnas as $lagnaId) {
            // Mars in House 12:
            $marsHouse = 12;
            $marsSign = (($lagnaId - 1 + ($marsHouse - 1)) % 12) + 1;

            // Place Jupiter to aspect Mars (7th aspect)
            $jupiterSign = (($marsSign - 1 + 6) % 12) + 1;

            $planets = [
                'mars' => ['rashi_id' => $marsSign],
                'jupiter' => ['rashi_id' => $jupiterSign],
            ];

            $res = $this->service->evaluate($planets, $lagnaId);

            $this->assertTrue($res['is_manglik_formed']);
            // Even though Jupiter aspects Mars, Jupiter is NOT a functional benefic -> aspect cancellation fails!
            $jupCancellation = $res['cancellation']['jupiter_aspect_cancellation'];
            $this->assertTrue($jupCancellation['does_aspect_mars']);
            $this->assertFalse($jupCancellation['is_eligible_functional_benefic']);
            $this->assertFalse($jupCancellation['cancels_dosha']);
            $this->assertNotContains('BHANGA_JUPITER_ASPECT', $res['applied_bhanga_ids']);
        }
    }

    // ==========================================
    // TRADITIONAL MITIGATIONS & STABILITY
    // ==========================================

    public function test_traditional_mitigations_classified_separately_from_structural_cancellations(): void
    {
        // Lagna = Taurus (2), Mars in Gemini (3) -> wait, Gemini is house 2 (not Manglik).
        // Let's place Mars in Leo (5) -> House 4 (Manglik, no classical bhanga for Leo in 4).
        // Moon conjunct Mars in Leo (5) -> Chandra-Mangala
        // Age = 32 (> 28)
        $planets = [
            'mars' => ['rashi_id' => 5],
            'moon' => ['rashi_id' => 5],
        ];

        $res = $this->service->evaluate($planets, 2, 32);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertFalse($res['has_cancellation'], 'Traditional mitigations do NOT cancel structural Kuja Dosha');
        $this->assertSame('dosha_confirmed', $res['final_status']);

        // Check traditional mitigations payload
        $this->assertTrue($res['traditional_mitigations']['chandra_mangala_yoga']['present']);
        $this->assertTrue($res['traditional_mitigations']['age_maturity']['applies']);
        $this->assertSame(32, $res['traditional_mitigations']['age_maturity']['age']);
    }

    public function test_interval_stability_variation_across_candidate_lagnas(): void
    {
        // Mars in Aries (1)
        // Candidate Lagnas: Aries (1) -> Mars in H1 (Manglik & Bhanga 1 applies -> cancelled)
        //                   Taurus (2) -> Mars in H12 (Manglik, no Bhanga -> confirmed)
        $stabilityData = [
            'd1_ascendant' => [
                'is_invariant' => false,
                'status' => 'uncertain_boundary',
                'candidate_signs' => [
                    ['id' => 1],
                    ['id' => 2],
                ],
            ],
        ];

        $planets = ['mars' => ['rashi_id' => 1]];
        $res = $this->service->evaluate($planets, 1, null, null, $stabilityData);

        $this->assertSame('uncertain_lagna_variation', $res['stability']['status']);
        $this->assertCount(2, $res['stability']['candidate_evaluations']);
    }

    public function test_manglik_suppressed_when_lagna_is_unknown(): void
    {
        $planets = ['mars' => ['rashi_id' => 1]];
        $res = $this->service->evaluate($planets, ['id' => null, 'status' => 'suppressed']);

        $this->assertFalse($res['is_manglik_formed']);
        $this->assertSame('suppressed', $res['final_status']);
        $this->assertSame('suppressed_unknown_lagna', $res['stability']['status']);
    }

    public function test_multiple_applicable_cancellations_represented_without_duplicate_or_contradiction(): void
    {
        // Lagna = Aries (1)
        // Mars in Aries (1) -> Bhanga 1 applies
        // Jupiter in Libra (7) -> aspects Mars in Aries via 7th aspect; Aries is eligible Lagna -> Bhanga 6 applies
        $planets = [
            'mars' => ['rashi_id' => 1],
            'jupiter' => ['rashi_id' => 7],
        ];

        $res = $this->service->evaluate($planets, 1);

        $this->assertTrue($res['is_manglik_formed']);
        $this->assertTrue($res['has_cancellation']);
        $this->assertSame('dosha_cancelled', $res['final_status']);
        $this->assertSame(2, $res['cancellation']['applied_bhangas_count']);
        $this->assertCount(2, $res['applied_bhanga_ids']);
        $this->assertContains('BHANGA_1_ARIES_1ST', $res['applied_bhanga_ids']);
        $this->assertContains('BHANGA_JUPITER_ASPECT', $res['applied_bhanga_ids']);
        $this->assertSame(array_unique($res['applied_bhanga_ids']), $res['applied_bhanga_ids'], 'No duplicate Bhangas emitted');
    }

    // =========================================================================
    // EXPLICIT POSITIVE AND NEGATIVE TESTS FOR EVERY PLACEMENT-BASED BHANGA RULE
    // =========================================================================

    public function test_capricorn_positive_in_houses_7_and_8_triggers_bhanga_3(): void
    {
        // House 7: Lagna Cancer (4), Mars in Capricorn (10)
        $res7 = $this->service->evaluate(['mars' => ['rashi_id' => 10]], 4);
        $this->assertTrue($res7['is_manglik_formed']);
        $this->assertSame(7, $res7['source_house']);
        $this->assertTrue($res7['has_cancellation']);
        $this->assertSame('dosha_cancelled', $res7['final_status']);
        $this->assertContains('BHANGA_3_CAPRICORN_7TH_8TH', $res7['applied_bhanga_ids']);

        // House 8: Lagna Gemini (3), Mars in Capricorn (10)
        $res8 = $this->service->evaluate(['mars' => ['rashi_id' => 10]], 3);
        $this->assertTrue($res8['is_manglik_formed']);
        $this->assertSame(8, $res8['source_house']);
        $this->assertTrue($res8['has_cancellation']);
        $this->assertSame('dosha_cancelled', $res8['final_status']);
        $this->assertContains('BHANGA_3_CAPRICORN_7TH_8TH', $res8['applied_bhanga_ids']);
    }

    public function test_capricorn_negative_in_houses_1_4_and_12_must_not_trigger_bhanga_3(): void
    {
        // House 1: Lagna Capricorn (10), Mars in Capricorn (10) -> House 1
        $res1 = $this->service->evaluate(['mars' => ['rashi_id' => 10]], 10);
        $this->assertTrue($res1['is_manglik_formed']);
        $this->assertSame(1, $res1['source_house']);
        $this->assertFalse($res1['has_cancellation'], 'Capricorn in 1st house must NOT trigger Bhanga 3');
        $this->assertSame('dosha_confirmed', $res1['final_status']);
        $this->assertNotContains('BHANGA_3_CAPRICORN_7TH_8TH', $res1['applied_bhanga_ids']);

        // House 4: Lagna Libra (7), Mars in Capricorn (10) -> House 4
        $res4 = $this->service->evaluate(['mars' => ['rashi_id' => 10]], 7);
        $this->assertTrue($res4['is_manglik_formed']);
        $this->assertSame(4, $res4['source_house']);
        $this->assertFalse($res4['has_cancellation'], 'Capricorn in 4th house must NOT trigger Bhanga 3');
        $this->assertSame('dosha_confirmed', $res4['final_status']);
        $this->assertNotContains('BHANGA_3_CAPRICORN_7TH_8TH', $res4['applied_bhanga_ids']);

        // House 12: Lagna Aquarius (11), Mars in Capricorn (10) -> House 12
        $res12 = $this->service->evaluate(['mars' => ['rashi_id' => 10]], 11);
        $this->assertTrue($res12['is_manglik_formed']);
        $this->assertSame(12, $res12['source_house']);
        $this->assertFalse($res12['has_cancellation'], 'Capricorn in 12th house must NOT trigger Bhanga 3');
        $this->assertSame('dosha_confirmed', $res12['final_status']);
        $this->assertNotContains('BHANGA_3_CAPRICORN_7TH_8TH', $res12['applied_bhanga_ids']);
    }

    public function test_leo_positive_in_1st_house_triggers_bhanga_5(): void
    {
        // Lagna Leo (5), Mars in Leo (5) -> House 1
        $res = $this->service->evaluate(['mars' => ['rashi_id' => 5]], 5);
        $this->assertTrue($res['is_manglik_formed']);
        $this->assertSame(1, $res['source_house']);
        $this->assertTrue($res['has_cancellation']);
        $this->assertSame('dosha_cancelled', $res['final_status']);
        $this->assertContains('BHANGA_5_LEO_1ST', $res['applied_bhanga_ids']);
    }

    public function test_leo_negative_in_houses_4_7_8_12_must_not_trigger_bhanga_5(): void
    {
        // House 4: Lagna Taurus (2), Mars in Leo (5)
        $res4 = $this->service->evaluate(['mars' => ['rashi_id' => 5]], 2);
        $this->assertTrue($res4['is_manglik_formed']);
        $this->assertSame(4, $res4['source_house']);
        $this->assertFalse($res4['has_cancellation'], 'Leo in 4th house must NOT trigger Bhanga 5');
        $this->assertSame('dosha_confirmed', $res4['final_status']);
        $this->assertNotContains('BHANGA_5_LEO_1ST', $res4['applied_bhanga_ids']);

        // House 7: Lagna Aquarius (11), Mars in Leo (5)
        $res7 = $this->service->evaluate(['mars' => ['rashi_id' => 5]], 11);
        $this->assertTrue($res7['is_manglik_formed']);
        $this->assertSame(7, $res7['source_house']);
        $this->assertFalse($res7['has_cancellation'], 'Leo in 7th house must NOT trigger Bhanga 5');
        $this->assertSame('dosha_confirmed', $res7['final_status']);
        $this->assertNotContains('BHANGA_5_LEO_1ST', $res7['applied_bhanga_ids']);

        // House 8: Lagna Capricorn (10), Mars in Leo (5)
        $res8 = $this->service->evaluate(['mars' => ['rashi_id' => 5]], 10);
        $this->assertTrue($res8['is_manglik_formed']);
        $this->assertSame(8, $res8['source_house']);
        $this->assertFalse($res8['has_cancellation'], 'Leo in 8th house must NOT trigger Bhanga 5');
        $this->assertSame('dosha_confirmed', $res8['final_status']);
        $this->assertNotContains('BHANGA_5_LEO_1ST', $res8['applied_bhanga_ids']);

        // House 12: Lagna Virgo (6), Mars in Leo (5)
        $res12 = $this->service->evaluate(['mars' => ['rashi_id' => 5]], 6);
        $this->assertTrue($res12['is_manglik_formed']);
        $this->assertSame(12, $res12['source_house']);
        $this->assertFalse($res12['has_cancellation'], 'Leo in 12th house must NOT trigger Bhanga 5');
        $this->assertSame('dosha_confirmed', $res12['final_status']);
        $this->assertNotContains('BHANGA_5_LEO_1ST', $res12['applied_bhanga_ids']);
    }

    public function test_taurus_and_libra_in_12th_house_must_not_trigger_placement_bhanga(): void
    {
        // Taurus (sign 2) in House 12: Lagna Gemini (3)
        $resTaurus = $this->service->evaluate(['mars' => ['rashi_id' => 2]], 3);
        $this->assertTrue($resTaurus['is_manglik_formed']);
        $this->assertSame(12, $resTaurus['source_house']);
        $this->assertFalse($resTaurus['has_cancellation'], 'Taurus in 12th must NOT receive a placement-based cancellation');
        $this->assertSame('dosha_confirmed', $resTaurus['final_status']);
        $this->assertEmpty($resTaurus['applied_bhanga_ids']);

        // Libra (sign 7) in House 12: Lagna Scorpio (8)
        $resLibra = $this->service->evaluate(['mars' => ['rashi_id' => 7]], 8);
        $this->assertTrue($resLibra['is_manglik_formed']);
        $this->assertSame(12, $resLibra['source_house']);
        $this->assertFalse($resLibra['has_cancellation'], 'Libra in 12th must NOT receive a placement-based cancellation');
        $this->assertSame('dosha_confirmed', $resLibra['final_status']);
        $this->assertEmpty($resLibra['applied_bhanga_ids']);
    }

    public function test_cancer_debilitation_in_all_five_manglik_houses_receives_zero_placement_cancellations(): void
    {
        // Cancer = sign 4 (Mars debilitation)
        // House 1: Lagna Cancer (4)
        // House 4: Lagna Aries (1)
        // House 7: Lagna Capricorn (10)
        // House 8: Lagna Sagittarius (9)
        // House 12: Lagna Leo (5)
        $manglikConfigurations = [
            1  => 4,  // House 1 -> Cancer Lagna (4)
            4  => 1,  // House 4 -> Aries Lagna (1)
            7  => 10, // House 7 -> Capricorn Lagna (10)
            8  => 9,  // House 8 -> Sagittarius Lagna (9)
            12 => 5,  // House 12 -> Leo Lagna (5)
        ];

        foreach ($manglikConfigurations as $expectedHouse => $lagnaId) {
            $res = $this->service->evaluate(['mars' => ['rashi_id' => 4]], $lagnaId);
            $this->assertTrue($res['is_manglik_formed'], "Mars in Cancer for Lagna {$lagnaId} must form Manglik in house {$expectedHouse}");
            $this->assertSame($expectedHouse, $res['source_house']);
            $this->assertTrue($res['is_debilitated'], 'Mars in Cancer must be marked debilitated');
            $this->assertFalse($res['has_cancellation'], "Mars in Cancer in house {$expectedHouse} must NOT be cancelled by any placement-based Bhanga");
            $this->assertSame('dosha_confirmed', $res['final_status']);
            $this->assertEmpty($res['applied_bhanga_ids'], "Applied Bhangas must be empty for debilitated Mars in house {$expectedHouse}");
        }
    }

    public function test_aries_positive_in_1st_and_negative_in_other_manglik_houses(): void
    {
        // Positive: House 1 (Lagna Aries 1)
        $pos = $this->service->evaluate(['mars' => ['rashi_id' => 1]], 1);
        $this->assertTrue($pos['is_manglik_formed']);
        $this->assertSame(1, $pos['source_house']);
        $this->assertTrue($pos['has_cancellation']);
        $this->assertSame('dosha_cancelled', $pos['final_status']);
        $this->assertContains('BHANGA_1_ARIES_1ST', $pos['applied_bhanga_ids']);

        // Negative:
        // House 4: Lagna Capricorn (10)
        $neg4 = $this->service->evaluate(['mars' => ['rashi_id' => 1]], 10);
        $this->assertSame(4, $neg4['source_house']);
        $this->assertFalse($neg4['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg4['final_status']);

        // House 7: Lagna Libra (7)
        $neg7 = $this->service->evaluate(['mars' => ['rashi_id' => 1]], 7);
        $this->assertSame(7, $neg7['source_house']);
        $this->assertFalse($neg7['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg7['final_status']);

        // House 8: Lagna Virgo (6)
        $neg8 = $this->service->evaluate(['mars' => ['rashi_id' => 1]], 6);
        $this->assertSame(8, $neg8['source_house']);
        $this->assertFalse($neg8['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg8['final_status']);

        // House 12: Lagna Taurus (2)
        $neg12 = $this->service->evaluate(['mars' => ['rashi_id' => 1]], 2);
        $this->assertSame(12, $neg12['source_house']);
        $this->assertFalse($neg12['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg12['final_status']);
    }

    public function test_scorpio_positive_in_4th_and_negative_in_other_manglik_houses(): void
    {
        // Positive: House 4 (Lagna Leo 5)
        $pos = $this->service->evaluate(['mars' => ['rashi_id' => 8]], 5);
        $this->assertTrue($pos['is_manglik_formed']);
        $this->assertSame(4, $pos['source_house']);
        $this->assertTrue($pos['has_cancellation']);
        $this->assertSame('dosha_cancelled', $pos['final_status']);
        $this->assertContains('BHANGA_2_SCORPIO_4TH', $pos['applied_bhanga_ids']);

        // Negative:
        // House 1: Lagna Scorpio (8)
        $neg1 = $this->service->evaluate(['mars' => ['rashi_id' => 8]], 8);
        $this->assertSame(1, $neg1['source_house']);
        $this->assertFalse($neg1['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg1['final_status']);

        // House 7: Lagna Taurus (2)
        $neg7 = $this->service->evaluate(['mars' => ['rashi_id' => 8]], 2);
        $this->assertSame(7, $neg7['source_house']);
        $this->assertFalse($neg7['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg7['final_status']);

        // House 8: Lagna Aries (1)
        $neg8 = $this->service->evaluate(['mars' => ['rashi_id' => 8]], 1);
        $this->assertSame(8, $neg8['source_house']);
        $this->assertFalse($neg8['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg8['final_status']);

        // House 12: Lagna Sagittarius (9)
        $neg12 = $this->service->evaluate(['mars' => ['rashi_id' => 8]], 9);
        $this->assertSame(12, $neg12['source_house']);
        $this->assertFalse($neg12['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg12['final_status']);
    }

    public function test_sagittarius_and_pisces_positive_in_8th_and_negative_in_other_manglik_houses(): void
    {
        // Positive:
        // Sagittarius in 8th: Lagna Taurus (2)
        $posSgr = $this->service->evaluate(['mars' => ['rashi_id' => 9]], 2);
        $this->assertSame(8, $posSgr['source_house']);
        $this->assertTrue($posSgr['has_cancellation']);
        $this->assertContains('BHANGA_4_JUPITER_SIGNS_8TH', $posSgr['applied_bhanga_ids']);

        // Pisces in 8th: Lagna Leo (5)
        $posPsc = $this->service->evaluate(['mars' => ['rashi_id' => 12]], 5);
        $this->assertSame(8, $posPsc['source_house']);
        $this->assertTrue($posPsc['has_cancellation']);
        $this->assertContains('BHANGA_4_JUPITER_SIGNS_8TH', $posPsc['applied_bhanga_ids']);

        // Negative:
        // Sagittarius in 1st: Lagna Sagittarius (9)
        $neg1 = $this->service->evaluate(['mars' => ['rashi_id' => 9]], 9);
        $this->assertSame(1, $neg1['source_house']);
        $this->assertFalse($neg1['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg1['final_status']);

        // Pisces in 4th: Lagna Sagittarius (9)
        $neg4 = $this->service->evaluate(['mars' => ['rashi_id' => 12]], 9);
        $this->assertSame(4, $neg4['source_house']);
        $this->assertFalse($neg4['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg4['final_status']);

        // Sagittarius in 7th: Lagna Gemini (3)
        $neg7 = $this->service->evaluate(['mars' => ['rashi_id' => 9]], 3);
        $this->assertSame(7, $neg7['source_house']);
        $this->assertFalse($neg7['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg7['final_status']);

        // Pisces in 12th: Lagna Aries (1)
        $neg12 = $this->service->evaluate(['mars' => ['rashi_id' => 12]], 1);
        $this->assertSame(12, $neg12['source_house']);
        $this->assertFalse($neg12['has_cancellation']);
        $this->assertSame('dosha_confirmed', $neg12['final_status']);
    }
}
