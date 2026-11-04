<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Services\Astrology\Yogas\BudhadityaYogaService;
use App\Services\Astrology\Yogas\GajaKesariYogaService;
use App\Services\Astrology\Yogas\PanchMahapurushaYogaService;
use PHPUnit\Framework\TestCase;

class ClassicalYogasTest extends TestCase
{
    protected PanchMahapurushaYogaService $panchMahapurushaService;
    protected GajaKesariYogaService $gajaKesariService;
    protected BudhadityaYogaService $budhadityaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->panchMahapurushaService = new PanchMahapurushaYogaService();
        $this->gajaKesariService = new GajaKesariYogaService();
        $this->budhadityaService = new BudhadityaYogaService();
    }

    // ==========================================
    // PANCH MAHAPURUSHA YOGAS
    // ==========================================

    public function test_ruchaka_yoga_formed_for_mars_in_own_sign_in_kendra(): void
    {
        // Lagna = Aries (1)
        // Mars in Aries (1) -> House 1 (Kendra) & own sign
        $ascendantData = [
            'id' => 1,
            'is_invariant' => true,
            'status' => 'confirmed',
        ];

        $planets = [
            'mars' => [
                'rashi_id' => 1,
                'longitude' => 15.0,
                'is_combust' => false,
                'is_retrograde' => false,
            ],
        ];

        $res = $this->panchMahapurushaService->evaluate($planets, $ascendantData);

        $this->assertTrue($res['has_any_mahapurusha']);
        $this->assertCount(1, $res['formed_yogas']);

        $ruchaka = $res['details']['ruchaka'];
        $this->assertTrue($ruchaka['is_formed']);
        $this->assertSame(1, $ruchaka['house']);
        $this->assertSame('own_sign', $ruchaka['dignity']);
        $this->assertSame('confirmed', $ruchaka['stability_status']);
    }

    public function test_ruchaka_yoga_formed_for_mars_in_capricorn_exalted_in_kendra(): void
    {
        // Lagna = Cancer (4)
        // Mars in Capricorn (10) -> House 7 (Kendra) & exalted
        $ascendantData = [
            'id' => 4,
            'is_invariant' => true,
            'status' => 'confirmed',
        ];

        $planets = [
            'mars' => [
                'rashi_id' => 10,
                'longitude' => 280.0,
                'is_combust' => false,
                'is_retrograde' => false,
            ],
        ];

        $res = $this->panchMahapurushaService->evaluate($planets, $ascendantData);

        $this->assertTrue($res['details']['ruchaka']['is_formed']);
        $this->assertSame(7, $res['details']['ruchaka']['house']);
        $this->assertSame('exalted', $res['details']['ruchaka']['dignity']);
    }

    public function test_all_five_mahapurusha_yogas_definition(): void
    {
        // Lagna = Libra (7)
        // 1. Ruchaka: Mars in Aries (1) -> House 7 (Kendra, own sign)
        // 2. Bhadra: Mercury in Virgo (6) -> House 12 (NOT Kendra -> not formed)
        // 3. Hamsa: Jupiter in Cancer (4) -> House 10 (Kendra, exalted)
        // 4. Malavya: Venus in Libra (7) -> House 1 (Kendra, own sign)
        // 5. Sasa: Saturn in Libra (7) -> House 1 (Kendra, exalted)
        $ascendantData = [
            'id' => 7,
            'is_invariant' => true,
            'status' => 'confirmed',
        ];

        $planets = [
            'mars' => ['rashi_id' => 1, 'is_combust' => false, 'is_retrograde' => false],
            'mercury' => ['rashi_id' => 6, 'is_combust' => false, 'is_retrograde' => false],
            'jupiter' => ['rashi_id' => 4, 'is_combust' => false, 'is_retrograde' => false],
            'venus' => ['rashi_id' => 7, 'is_combust' => false, 'is_retrograde' => false],
            'saturn' => ['rashi_id' => 7, 'is_combust' => false, 'is_retrograde' => false],
        ];

        $res = $this->panchMahapurushaService->evaluate($planets, $ascendantData);

        $this->assertTrue($res['details']['ruchaka']['is_formed']);
        $this->assertFalse($res['details']['bhadra']['is_formed']); // House 12, not Kendra
        $this->assertTrue($res['details']['hamsa']['is_formed']);
        $this->assertTrue($res['details']['malavya']['is_formed']);
        $this->assertTrue($res['details']['sasa']['is_formed']);
    }

    public function test_mahapurusha_yoga_quality_factors_separated_from_formation(): void
    {
        // Lagna = Aries (1)
        // Mars in Aries (1) with combustion and retrogression
        $ascendantData = [
            'id' => 1,
            'is_invariant' => true,
            'status' => 'confirmed',
        ];

        $planets = [
            'mars' => [
                'rashi_id' => 1,
                'longitude' => 15.0,
                'is_combust' => true,
                'is_retrograde' => true,
            ],
        ];

        $res = $this->panchMahapurushaService->evaluate($planets, $ascendantData);
        $ruchaka = $res['details']['ruchaka'];

        $this->assertTrue($ruchaka['is_formed'], 'Combustion and retrogression do not cancel structural formation');
        $this->assertTrue($ruchaka['is_combust']);
        $this->assertTrue($ruchaka['is_retrograde']);
        $this->assertFalse($ruchaka['is_unblemished']);
        $this->assertNotEmpty($ruchaka['quality_factors']);
    }

    public function test_mahapurusha_yoga_suppressed_when_lagna_unknown(): void
    {
        $ascendantData = [
            'id' => null,
            'status' => 'suppressed',
            'is_invariant' => false,
            'house_dependent_content_suppressed' => true,
        ];

        $planets = [
            'mars' => ['rashi_id' => 1],
        ];

        $res = $this->panchMahapurushaService->evaluate($planets, $ascendantData);

        $this->assertFalse($res['has_any_mahapurusha']);
        $this->assertSame('suppressed', $res['evaluation_status']);
    }

    // ==========================================
    // GAJA KESARI YOGA
    // ==========================================

    public function test_gaja_kesari_formed_in_all_four_kendras_from_moon(): void
    {
        // Moon in Taurus (2)
        // 1st (conjunction): Jupiter in Taurus (2) -> relative house 1
        // 4th: Jupiter in Leo (5) -> relative house 4
        // 7th: Jupiter in Scorpio (8) -> relative house 7
        // 10th: Jupiter in Aquarius (11) -> relative house 10
        $kendraSigns = [2, 5, 8, 11];

        foreach ($kendraSigns as $jupSign) {
            $planets = [
                'moon' => ['rashi_id' => 2, 'longitude' => 45.0],
                'jupiter' => ['rashi_id' => $jupSign, 'longitude' => ($jupSign - 1) * 30.0 + 10.0, 'is_combust' => false, 'is_retrograde' => false],
            ];

            $res = $this->gajaKesariService->evaluate($planets);
            $this->assertTrue($res['is_formed'], "Gaja Kesari should be formed when Jupiter is in sign {$jupSign} from Moon in sign 2");
            $this->assertContains($res['relative_house_from_moon'], [1, 4, 7, 10]);
        }
    }

    public function test_gaja_kesari_not_formed_in_non_kendra_houses(): void
    {
        // Moon in Aries (1), Jupiter in Gemini (3) -> relative house 3 (Upachaya, not Kendra)
        $planets = [
            'moon' => ['rashi_id' => 1, 'longitude' => 10.0],
            'jupiter' => ['rashi_id' => 3, 'longitude' => 70.0, 'is_combust' => false, 'is_retrograde' => false],
        ];

        $res = $this->gajaKesariService->evaluate($planets);

        $this->assertFalse($res['is_formed']);
        $this->assertSame(3, $res['relative_house_from_moon']);
    }

    public function test_gaja_kesari_formed_in_capricorn_with_debilitation_impediment(): void
    {
        // Moon in Cancer (4), Jupiter in Capricorn (10) -> relative house 7 (Kendra)
        // Jupiter is debilitated in Capricorn
        $planets = [
            'moon' => ['rashi_id' => 4, 'longitude' => 100.0],
            'jupiter' => ['rashi_id' => 10, 'longitude' => 280.0, 'is_combust' => false, 'is_retrograde' => false],
        ];

        $res = $this->gajaKesariService->evaluate($planets);

        $this->assertTrue($res['is_formed'], 'Structural formation exists despite debilitation');
        $this->assertSame('debilitated', $res['jupiter_dignity']);
        $this->assertTrue($res['has_impediment']);
        $this->assertContains('jupiter_debilitated', $res['quality_notes']);
    }

    // ==========================================
    // BUDHADITYA YOGA
    // ==========================================

    public function test_budhaditya_formed_when_sun_and_mercury_in_same_rashi(): void
    {
        $planets = [
            'sun' => ['rashi_id' => 5, 'longitude' => 130.0],
            'mercury' => ['rashi_id' => 5, 'longitude' => 138.0, 'is_retrograde' => false],
        ];

        $res = $this->budhadityaService->evaluate($planets);

        $this->assertTrue($res['is_formed']);
        $this->assertSame(5, $res['rashi_id']);
        $this->assertEqualsWithDelta(8.0, $res['angular_separation_degrees'], 0.001);
    }

    public function test_budhaditya_not_formed_when_in_different_rashis(): void
    {
        $planets = [
            'sun' => ['rashi_id' => 5, 'longitude' => 148.0],
            'mercury' => ['rashi_id' => 6, 'longitude' => 152.0, 'is_retrograde' => false],
        ];

        $res = $this->budhadityaService->evaluate($planets);

        $this->assertFalse($res['is_formed']);
        $this->assertNull($res['rashi_id']);
    }

    public function test_budhaditya_combustion_flagged_without_cancelling_formation(): void
    {
        // Mercury within 14 degrees direct -> combust
        $planets = [
            'sun' => ['rashi_id' => 1, 'longitude' => 10.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 16.0, 'is_retrograde' => false], // separation = 6° < 14°
        ];

        $res = $this->budhadityaService->evaluate($planets);

        $this->assertTrue($res['is_formed'], 'Budhaditya remains structurally formed');
        $this->assertTrue($res['is_combust'], 'Mercury is combust');
        $this->assertFalse($res['is_unblemished']);
    }

    public function test_budhaditya_combustion_threshold_boundary_precision_direct_and_retrograde(): void
    {
        // DIRECT: threshold is exactly 14.0° (inclusive <= 14.0)
        // 1. Immediately below threshold: 13.99° -> combust
        $planetsBelow = [
            'sun' => ['rashi_id' => 1, 'longitude' => 1.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 14.99, 'is_retrograde' => false],
        ];
        $resBelow = $this->budhadityaService->evaluate($planetsBelow);
        $this->assertTrue($resBelow['is_combust'], 'Direct Mercury at 13.99° must be combust');

        // 2. Exactly at threshold: 14.00° -> combust (inclusive)
        $planetsAt = [
            'sun' => ['rashi_id' => 1, 'longitude' => 1.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 15.00, 'is_retrograde' => false],
        ];
        $resAt = $this->budhadityaService->evaluate($planetsAt);
        $this->assertTrue($resAt['is_combust'], 'Direct Mercury at exact 14.00° boundary must be combust (inclusive)');

        // 3. Immediately above threshold: 14.01° -> NOT combust
        $planetsAbove = [
            'sun' => ['rashi_id' => 1, 'longitude' => 1.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 15.01, 'is_retrograde' => false],
        ];
        $resAbove = $this->budhadityaService->evaluate($planetsAbove);
        $this->assertFalse($resAbove['is_combust'], 'Direct Mercury at 14.01° must NOT be combust');

        // RETROGRADE: threshold is exactly 12.0° (inclusive <= 12.0)
        // 4. Immediately below threshold: 11.99° -> combust
        $planetsRetBelow = [
            'sun' => ['rashi_id' => 1, 'longitude' => 1.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 12.99, 'is_retrograde' => true],
        ];
        $resRetBelow = $this->budhadityaService->evaluate($planetsRetBelow);
        $this->assertTrue($resRetBelow['is_combust'], 'Retrograde Mercury at 11.99° must be combust');

        // 5. Exactly at threshold: 12.00° -> combust (inclusive)
        $planetsRetAt = [
            'sun' => ['rashi_id' => 1, 'longitude' => 1.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 13.00, 'is_retrograde' => true],
        ];
        $resRetAt = $this->budhadityaService->evaluate($planetsRetAt);
        $this->assertTrue($resRetAt['is_combust'], 'Retrograde Mercury at exact 12.00° boundary must be combust');

        // 6. Immediately above threshold: 12.01° -> NOT combust
        $planetsRetAbove = [
            'sun' => ['rashi_id' => 1, 'longitude' => 1.0],
            'mercury' => ['rashi_id' => 1, 'longitude' => 13.01, 'is_retrograde' => true],
        ];
        $resRetAbove = $this->budhadityaService->evaluate($planetsRetAbove);
        $this->assertFalse($resRetAbove['is_combust'], 'Retrograde Mercury at 12.01° must NOT be combust');
    }

    public function test_budhaditya_angular_separation_across_zero_degree_boundary(): void
    {
        // Sun at 359.5° (Pisces, sign 12)
        // Mercury at 0.5° (Aries, sign 1)
        // Different signs -> not formed
        $planetsDiff = [
            'sun' => ['rashi_id' => 12, 'longitude' => 359.5],
            'mercury' => ['rashi_id' => 1, 'longitude' => 0.5, 'is_retrograde' => false],
        ];
        $resDiff = $this->budhadityaService->evaluate($planetsDiff);
        $this->assertFalse($resDiff['is_formed']);
        $this->assertEqualsWithDelta(1.0, $resDiff['quality']['angular_separation_deg'], 0.001);

        // Both in Pisces (sign 12): Sun at 359.0°, Mercury at 345.0° -> separation = 14.0°
        $planetsSame = [
            'sun' => ['rashi_id' => 12, 'longitude' => 359.0],
            'mercury' => ['rashi_id' => 12, 'longitude' => 345.0, 'is_retrograde' => false],
        ];
        $resSame = $this->budhadityaService->evaluate($planetsSame);
        $this->assertTrue($resSame['is_formed']);
        $this->assertSame(12, $resSame['rashi_id']);
        $this->assertEqualsWithDelta(14.0, $resSame['angular_separation_degrees'], 0.001);
        $this->assertTrue($resSame['is_combust'], 'At exact 14.0° is combust');
    }

    public function test_gaja_kesari_stability_four_cases_matrix_independent_evaluation(): void
    {
        $planets = [
            'moon' => ['rashi_id' => 1, 'longitude' => 10.0],
            'jupiter' => ['rashi_id' => 4, 'longitude' => 100.0], // 4th Kendra from Moon
        ];

        // Case 1: Moon invariant, Jupiter invariant -> CONFIRMED
        $case1 = $this->gajaKesariService->evaluate($planets, [
            'is_moon_invariant' => true,
            'is_jupiter_invariant' => true,
        ]);
        $this->assertTrue($case1['is_formed'], 'Classical formation holds');
        $this->assertTrue($case1['is_confirmed'], 'Case 1 (both invariant) MUST qualify for confirmed status');
        $this->assertSame('confirmed', $case1['stability_status']);

        // Case 2: Moon invariant, Jupiter unstable -> NOT confirmed
        $case2 = $this->gajaKesariService->evaluate($planets, [
            'is_moon_invariant' => true,
            'is_jupiter_invariant' => false,
        ]);
        $this->assertTrue($case2['is_formed'], 'Classical formation holds');
        $this->assertFalse($case2['is_confirmed'], 'Case 2 (Jupiter unstable) must NOT be confirmed');
        $this->assertSame('uncertain_jupiter_sign', $case2['stability_status']);

        // Case 3: Moon unstable, Jupiter invariant -> NOT confirmed
        $case3 = $this->gajaKesariService->evaluate($planets, [
            'is_moon_invariant' => false,
            'is_jupiter_invariant' => true,
        ]);
        $this->assertTrue($case3['is_formed'], 'Classical formation holds');
        $this->assertFalse($case3['is_confirmed'], 'Case 3 (Moon unstable) must NOT be confirmed');
        $this->assertSame('uncertain_moon_sign', $case3['stability_status']);

        // Case 4: Both unstable -> NOT confirmed
        $case4 = $this->gajaKesariService->evaluate($planets, [
            'is_moon_invariant' => false,
            'is_jupiter_invariant' => false,
        ]);
        $this->assertTrue($case4['is_formed'], 'Classical formation holds');
        $this->assertFalse($case4['is_confirmed'], 'Case 4 (both unstable) must NOT be confirmed');
        $this->assertSame('uncertain_moon_and_jupiter', $case4['stability_status']);

        // Verification of distinction: Non-formed case is never confirmed even if both invariant
        $nonKendraPlanets = [
            'moon' => ['rashi_id' => 1, 'longitude' => 10.0],
            'jupiter' => ['rashi_id' => 3, 'longitude' => 70.0], // 3rd from Moon (non-kendra)
        ];
        $nonKendraRes = $this->gajaKesariService->evaluate($nonKendraPlanets, [
            'is_moon_invariant' => true,
            'is_jupiter_invariant' => true,
        ]);
        $this->assertFalse($nonKendraRes['is_formed']);
        $this->assertFalse($nonKendraRes['is_confirmed'], 'Non-formed yoga cannot be confirmed even if stable');

        // Verification of distinction: Qualitative impediment (Jupiter debilitated in Capricorn) preserved
        $debilitatedPlanets = [
            'moon' => ['rashi_id' => 4, 'longitude' => 100.0], // Cancer
            'jupiter' => ['rashi_id' => 10, 'longitude' => 280.0], // Capricorn (7th Kendra from Moon, debilitated)
        ];
        $debilitatedRes = $this->gajaKesariService->evaluate($debilitatedPlanets, [
            'is_moon_invariant' => true,
            'is_jupiter_invariant' => true,
        ]);
        $this->assertTrue($debilitatedRes['is_formed'], 'Classical formation holds');
        $this->assertTrue($debilitatedRes['is_confirmed'], 'Confirmed when interval is stable');
        $this->assertSame('debilitated', $debilitatedRes['jupiter_dignity']);
        $this->assertTrue($debilitatedRes['has_impediment'], 'Impediment preserved independently from stability');
    }

    public function test_all_five_mahapurusha_yogas_across_all_four_kendras(): void
    {
        // 1. Ruchaka: Mars in Aries (1)
        // Lagnas making Aries a Kendra: Aries (H1), Capricorn (H4), Libra (H7), Cancer (H10)
        $ruchakaLagnas = [1 => 1, 10 => 4, 7 => 7, 4 => 10];
        foreach ($ruchakaLagnas as $lagnaId => $expectedHouse) {
            $res = $this->panchMahapurushaService->evaluate(['mars' => ['rashi_id' => 1]], $lagnaId);
            $this->assertTrue($res['details']['ruchaka']['is_formed'], "Ruchaka must be formed for Mars in Aries with Lagna {$lagnaId}");
            $this->assertSame($expectedHouse, $res['details']['ruchaka']['house']);
        }

        // Non-Kendra Lagnas for Mars in Aries: Taurus (H12), Gemini (H11), Leo (H9), etc.
        $nonKendraRes = $this->panchMahapurushaService->evaluate(['mars' => ['rashi_id' => 1]], 2); // Taurus -> H12
        $this->assertFalse($nonKendraRes['details']['ruchaka']['is_formed']);

        // 2. Bhadra: Mercury in Gemini (3)
        // Kendras from Gemini: Gemini (H1), Pisces (H4), Sagittarius (H7), Virgo (H10)
        $bhadraLagnas = [3 => 1, 12 => 4, 9 => 7, 6 => 10];
        foreach ($bhadraLagnas as $lagnaId => $expectedHouse) {
            $res = $this->panchMahapurushaService->evaluate(['mercury' => ['rashi_id' => 3]], $lagnaId);
            $this->assertTrue($res['details']['bhadra']['is_formed'], "Bhadra must be formed for Mercury in Gemini with Lagna {$lagnaId}");
            $this->assertSame($expectedHouse, $res['details']['bhadra']['house']);
        }

        // 3. Hamsa: Jupiter in Cancer (4, exalted)
        // Kendras from Cancer: Cancer (H1), Aries (H4), Capricorn (H7), Libra (H10)
        $hamsaLagnas = [4 => 1, 1 => 4, 10 => 7, 7 => 10];
        foreach ($hamsaLagnas as $lagnaId => $expectedHouse) {
            $res = $this->panchMahapurushaService->evaluate(['jupiter' => ['rashi_id' => 4]], $lagnaId);
            $this->assertTrue($res['details']['hamsa']['is_formed'], "Hamsa must be formed for Jupiter in Cancer with Lagna {$lagnaId}");
            $this->assertSame($expectedHouse, $res['details']['hamsa']['house']);
        }

        // 4. Malavya: Venus in Pisces (12, exalted)
        // Kendras from Pisces: Pisces (H1), Sagittarius (H4), Virgo (H7), Gemini (H10)
        $malavyaLagnas = [12 => 1, 9 => 4, 6 => 7, 3 => 10];
        foreach ($malavyaLagnas as $lagnaId => $expectedHouse) {
            $res = $this->panchMahapurushaService->evaluate(['venus' => ['rashi_id' => 12]], $lagnaId);
            $this->assertTrue($res['details']['malavya']['is_formed'], "Malavya must be formed for Venus in Pisces with Lagna {$lagnaId}");
            $this->assertSame($expectedHouse, $res['details']['malavya']['house']);
        }

        // 5. Sasa: Saturn in Libra (7, exalted)
        // Kendras from Libra: Libra (H1), Cancer (H4), Aries (H7), Capricorn (H10)
        $sasaLagnas = [7 => 1, 4 => 4, 1 => 7, 10 => 10];
        foreach ($sasaLagnas as $lagnaId => $expectedHouse) {
            $res = $this->panchMahapurushaService->evaluate(['saturn' => ['rashi_id' => 7]], $lagnaId);
            $this->assertTrue($res['details']['sasa']['is_formed'], "Sasa must be formed for Saturn in Libra with Lagna {$lagnaId}");
            $this->assertSame($expectedHouse, $res['details']['sasa']['house']);
        }
    }
}
