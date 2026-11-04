<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Services\Astrology\Aspects\ParashariAspectService;
use PHPUnit\Framework\TestCase;

class ParashariAspectTest extends TestCase
{
    protected ParashariAspectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ParashariAspectService();
    }

    public function test_only_seven_classical_planets_cast_aspects(): void
    {
        $planets = [
            'sun' => ['rashi_id' => 1],
            'moon' => ['rashi_id' => 2],
            'mars' => ['rashi_id' => 3],
            'mercury' => ['rashi_id' => 4],
            'jupiter' => ['rashi_id' => 5],
            'venus' => ['rashi_id' => 6],
            'saturn' => ['rashi_id' => 7],
            'rahu' => ['rashi_id' => 8],
            'ketu' => ['rashi_id' => 2],
        ];

        $matrix = $this->service->computeAspectMatrix($planets, 1);

        $this->assertArrayHasKey('sun', $matrix['by_planet']);
        $this->assertArrayHasKey('moon', $matrix['by_planet']);
        $this->assertArrayHasKey('mars', $matrix['by_planet']);
        $this->assertArrayHasKey('mercury', $matrix['by_planet']);
        $this->assertArrayHasKey('jupiter', $matrix['by_planet']);
        $this->assertArrayHasKey('venus', $matrix['by_planet']);
        $this->assertArrayHasKey('saturn', $matrix['by_planet']);
        $this->assertArrayNotHasKey('rahu', $matrix['by_planet']);
        $this->assertArrayNotHasKey('ketu', $matrix['by_planet']);
    }

    public function test_universal_seventh_aspect_for_sun_moon_mercury_venus(): void
    {
        $planets = [
            'sun' => ['rashi_id' => 1], // Aries -> Libra (7)
            'moon' => ['rashi_id' => 4], // Cancer -> Capricorn (10)
            'mercury' => ['rashi_id' => 9], // Sagittarius -> Gemini (3)
            'venus' => ['rashi_id' => 12], // Pisces -> Virgo (6)
        ];

        $matrix = $this->service->computeAspectMatrix($planets);

        // Sun
        $sunAspects = $matrix['by_planet']['sun'];
        $this->assertCount(1, $sunAspects);
        $this->assertSame(7, $sunAspects[0]['target_rashi_id']);
        $this->assertSame('7th', $sunAspects[0]['aspect_type']);
        $this->assertSame(7, $sunAspects[0]['aspect_count']);

        // Moon
        $moonAspects = $matrix['by_planet']['moon'];
        $this->assertCount(1, $moonAspects);
        $this->assertSame(10, $moonAspects[0]['target_rashi_id']);
        $this->assertSame('7th', $moonAspects[0]['aspect_type']);

        // Mercury
        $mercuryAspects = $matrix['by_planet']['mercury'];
        $this->assertCount(1, $mercuryAspects);
        $this->assertSame(3, $mercuryAspects[0]['target_rashi_id']);
        $this->assertSame('7th', $mercuryAspects[0]['aspect_type']);

        // Venus
        $venusAspects = $matrix['by_planet']['venus'];
        $this->assertCount(1, $venusAspects);
        $this->assertSame(6, $venusAspects[0]['target_rashi_id']);
        $this->assertSame('7th', $venusAspects[0]['aspect_type']);
    }

    public function test_mars_special_aspects_fourth_seventh_eighth_with_circular_wrapping(): void
    {
        // Mars in Pisces (12)
        // 4th aspect: (12 + 4 - 1 - 1) % 12 + 1 = 3 (Gemini)
        // 7th aspect: (12 + 7 - 1 - 1) % 12 + 1 = 6 (Virgo)
        // 8th aspect: (12 + 8 - 1 - 1) % 12 + 1 = 7 (Libra)
        $planets = [
            'mars' => ['rashi_id' => 12],
        ];

        $matrix = $this->service->computeAspectMatrix($planets);
        $marsAspects = $matrix['by_planet']['mars'];

        $this->assertCount(3, $marsAspects);

        $targets = array_column($marsAspects, 'target_rashi_id');
        $aspectCounts = array_column($marsAspects, 'aspect_count');

        $this->assertSame([3, 6, 7], $targets);
        $this->assertSame([4, 7, 8], $aspectCounts);
    }

    public function test_jupiter_special_aspects_fifth_seventh_ninth(): void
    {
        // Jupiter in Cancer (4)
        // 5th aspect: 4 + 5 - 1 = 8 (Scorpio)
        // 7th aspect: 4 + 7 - 1 = 10 (Capricorn)
        // 9th aspect: 4 + 9 - 1 = 12 (Pisces)
        $planets = [
            'jupiter' => ['rashi_id' => 4],
        ];

        $matrix = $this->service->computeAspectMatrix($planets);
        $jupAspects = $matrix['by_planet']['jupiter'];

        $this->assertCount(3, $jupAspects);

        $targets = array_column($jupAspects, 'target_rashi_id');
        $aspectCounts = array_column($jupAspects, 'aspect_count');

        $this->assertSame([8, 10, 12], $targets);
        $this->assertSame([5, 7, 9], $aspectCounts);
    }

    public function test_saturn_special_aspects_third_seventh_tenth(): void
    {
        // Saturn in Capricorn (10)
        // 3rd aspect: (10 + 3 - 2) % 12 + 1 = 12 (Pisces)
        // 7th aspect: (10 + 7 - 2) % 12 + 1 = 4 (Cancer)
        // 10th aspect: (10 + 10 - 2) % 12 + 1 = 7 (Libra)
        $planets = [
            'saturn' => ['rashi_id' => 10],
        ];

        $matrix = $this->service->computeAspectMatrix($planets);
        $satAspects = $matrix['by_planet']['saturn'];

        $this->assertCount(3, $satAspects);

        $targets = array_column($satAspects, 'target_rashi_id');
        $aspectCounts = array_column($satAspects, 'aspect_count');

        $this->assertSame([12, 4, 7], $targets);
        $this->assertSame([3, 7, 10], $aspectCounts);
    }

    public function test_receiving_planets_and_house_mapping(): void
    {
        // Lagna = Aries (1)
        // Jupiter in Cancer (4) -> aspects 8 (Scorpio), 10 (Capricorn), 12 (Pisces)
        // Place Mars in Scorpio (8) and Saturn in Capricorn (10)
        $planets = [
            'jupiter' => ['rashi_id' => 4],
            'mars' => ['rashi_id' => 8],
            'saturn' => ['rashi_id' => 10],
            'rahu' => ['rashi_id' => 12], // Rahu is in Pisces (12)
        ];

        $matrix = $this->service->computeAspectMatrix($planets, 1);

        $jupAspects = $matrix['by_planet']['jupiter'];

        // Aspect to Scorpio (8): Mars is receiving
        $scorpioAspect = array_values(array_filter($jupAspects, fn($a) => $a['target_rashi_id'] === 8))[0];
        $this->assertSame(8, $scorpioAspect['target_house']);
        $this->assertSame(['mars'], $scorpioAspect['receiving_planets']);

        // Aspect to Capricorn (10): Saturn is receiving
        $capricornAspect = array_values(array_filter($jupAspects, fn($a) => $a['target_rashi_id'] === 10))[0];
        $this->assertSame(10, $capricornAspect['target_house']);
        $this->assertSame(['saturn'], $capricornAspect['receiving_planets']);

        // Aspect to Pisces (12): Rahu is receiving
        $piscesAspect = array_values(array_filter($jupAspects, fn($a) => $a['target_rashi_id'] === 12))[0];
        $this->assertSame(12, $piscesAspect['target_house']);
        $this->assertSame(['rahu'], $piscesAspect['receiving_planets']);

        // Check target sign view
        $this->assertArrayHasKey(8, $matrix['by_target_sign']);
        $this->assertSame(['jupiter'], $matrix['by_target_sign'][8]['aspecting_planets']);
    }

    public function test_does_planet_aspect_sign_helper(): void
    {
        $this->assertTrue($this->service->doesPlanetAspectSign('mars', 1, 4)['does_aspect']);  // 4th
        $this->assertTrue($this->service->doesPlanetAspectSign('mars', 1, 7)['does_aspect']);  // 7th
        $this->assertTrue($this->service->doesPlanetAspectSign('mars', 1, 8)['does_aspect']);  // 8th
        $this->assertFalse($this->service->doesPlanetAspectSign('mars', 1, 5)['does_aspect']); // Not aspected by Mars

        $this->assertTrue($this->service->doesPlanetAspectSign('jupiter', 1, 5)['does_aspect']); // 5th
        $this->assertTrue($this->service->doesPlanetAspectSign('jupiter', 1, 7)['does_aspect']); // 7th
        $this->assertTrue($this->service->doesPlanetAspectSign('jupiter', 1, 9)['does_aspect']); // 9th
        $this->assertFalse($this->service->doesPlanetAspectSign('jupiter', 1, 4)['does_aspect']);

        $this->assertTrue($this->service->doesPlanetAspectSign('saturn', 1, 3)['does_aspect']);  // 3rd
        $this->assertTrue($this->service->doesPlanetAspectSign('saturn', 1, 7)['does_aspect']);  // 7th
        $this->assertTrue($this->service->doesPlanetAspectSign('saturn', 1, 10)['does_aspect']); // 10th
        $this->assertFalse($this->service->doesPlanetAspectSign('saturn', 1, 5)['does_aspect']);
    }

    public function test_special_aspects_for_all_twelve_starting_signs_with_circular_wraparound(): void
    {
        for ($sign = 1; $sign <= 12; $sign++) {
            // Mars: 4th, 7th, 8th
            $expectedMars4th = (($sign - 1 + 3) % 12) + 1;
            $expectedMars7th = (($sign - 1 + 6) % 12) + 1;
            $expectedMars8th = (($sign - 1 + 7) % 12) + 1;

            $marsAspects = $this->service->computeAspectMatrix(['mars' => ['rashi_id' => $sign]])['by_planet']['mars'];
            $targetsMars = array_column($marsAspects, 'target_rashi_id');
            $this->assertSame([$expectedMars4th, $expectedMars7th, $expectedMars8th], $targetsMars, "Failed for Mars starting at sign {$sign}");

            // Jupiter: 5th, 7th, 9th
            $expectedJup5th = (($sign - 1 + 4) % 12) + 1;
            $expectedJup7th = (($sign - 1 + 6) % 12) + 1;
            $expectedJup9th = (($sign - 1 + 8) % 12) + 1;

            $jupAspects = $this->service->computeAspectMatrix(['jupiter' => ['rashi_id' => $sign]])['by_planet']['jupiter'];
            $targetsJup = array_column($jupAspects, 'target_rashi_id');
            $this->assertSame([$expectedJup5th, $expectedJup7th, $expectedJup9th], $targetsJup, "Failed for Jupiter starting at sign {$sign}");

            // Saturn: 3rd, 7th, 10th
            $expectedSat3rd = (($sign - 1 + 2) % 12) + 1;
            $expectedSat7th = (($sign - 1 + 6) % 12) + 1;
            $expectedSat10th = (($sign - 1 + 9) % 12) + 1;

            $satAspects = $this->service->computeAspectMatrix(['saturn' => ['rashi_id' => $sign]])['by_planet']['saturn'];
            $targetsSat = array_column($satAspects, 'target_rashi_id');
            $this->assertSame([$expectedSat3rd, $expectedSat7th, $expectedSat10th], $targetsSat, "Failed for Saturn starting at sign {$sign}");
        }
    }

    public function test_aspect_calculation_is_deterministic_independent_of_input_ordering(): void
    {
        $planetsForward = [
            'sun' => ['rashi_id' => 1],
            'moon' => ['rashi_id' => 2],
            'mars' => ['rashi_id' => 3],
            'mercury' => ['rashi_id' => 4],
            'jupiter' => ['rashi_id' => 5],
            'venus' => ['rashi_id' => 6],
            'saturn' => ['rashi_id' => 7],
        ];

        $planetsReverse = [
            'saturn' => ['rashi_id' => 7],
            'venus' => ['rashi_id' => 6],
            'jupiter' => ['rashi_id' => 5],
            'mercury' => ['rashi_id' => 4],
            'mars' => ['rashi_id' => 3],
            'moon' => ['rashi_id' => 2],
            'sun' => ['rashi_id' => 1],
        ];

        $planetsScrambled = [
            'jupiter' => ['rashi_id' => 5],
            'sun' => ['rashi_id' => 1],
            'saturn' => ['rashi_id' => 7],
            'venus' => ['rashi_id' => 6],
            'mars' => ['rashi_id' => 3],
            'moon' => ['rashi_id' => 2],
            'mercury' => ['rashi_id' => 4],
        ];

        $res1 = $this->service->computeAspectMatrix($planetsForward, 1);
        $res2 = $this->service->computeAspectMatrix($planetsReverse, 1);
        $res3 = $this->service->computeAspectMatrix($planetsScrambled, 1);

        $this->assertSame($res1['aspects'], $res2['aspects']);
        $this->assertSame($res1['aspects'], $res3['aspects']);
        $this->assertSame(array_keys($res1['by_planet']), array_keys($res2['by_planet']));
    }

    public function test_aspects_without_lagna_compute_sign_aspects_with_null_house_labels(): void
    {
        $planets = [
            'mars' => ['rashi_id' => 1],
            'jupiter' => ['rashi_id' => 5],
        ];

        $matrix = $this->service->computeAspectMatrix($planets, null);

        $this->assertNull($matrix['lagna_sign_id']);
        $this->assertNull($matrix['aspected_houses']);
        $this->assertCount(6, $matrix['aspects']); // 3 for Mars + 3 for Jupiter

        foreach ($matrix['aspects'] as $aspect) {
            $this->assertNotNull($aspect['aspected_sign_id']);
            $this->assertNull($aspect['aspecting_house']);
            $this->assertNull($aspect['target_house']);
        }
    }
}
