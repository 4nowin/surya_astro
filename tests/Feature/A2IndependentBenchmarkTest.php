<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Astrology\KundliService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use Tests\TestCase;

/**
 * Independent Classical Benchmark Validation Test Suite for Work Package A2.
 *
 * Validates Astro Setu's classical calculation engines (Parashari Drishti,
 * Panch Mahapurusha, Gaja Kesari, Budhaditya, and Manglik Dosha & Bhangas)
 * against published, traceable historical horoscopes from Dr. B.V. Raman's
 * seminal works:
 *  - "Notable Horoscopes" (Motilal Banarsidass / Raman Publications)
 *  - "Three Hundred Important Combinations" (Motilal Banarsidass / Raman Publications)
 *
 * Each test executes the entire pipeline:
 *  1. Raw birth date/time/location -> Native Ephemeris (libjme/jme.dll, True Citra Lahiri).
 *  2. Evaluates Lagna and planetary positions.
 *  3. Feeds positions into YogaDoshaOrchestrator.
 *  4. Asserts against independently published classifications.
 */
class A2IndependentBenchmarkTest extends TestCase
{
    protected KundliService $kundliService;
    protected YogaDoshaOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kundliService = app(KundliService::class);
        $this->orchestrator = app(YogaDoshaOrchestrator::class);
    }

    /**
     * Load fixture data from tests/Fixtures/Astrology/A2/Independent/.
     *
     * @return array<string, mixed>
     */
    protected function loadFixture(string $filename): array
    {
        $path = base_path("tests/Fixtures/Astrology/A2/Independent/{$filename}");
        $this->assertFileExists($path, "Fixture file {$filename} must exist.");
        $json = file_get_contents($path);
        $this->assertIsString($json);
        $data = json_decode($json, true);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * Benchmark Chart 1: Adolf Hitler (Notable Horoscopes Ch. 58; 300 Combinations Chart 23).
     * Validates: Ruchaka Yoga (Mars in Aries in 7th Kendra) & Manglik Dosha confirmed (no cancellation).
     */
    public function test_benchmark_hitler_ruchaka_yoga_and_confirmed_manglik(): void
    {
        $fixture = $this->loadFixture('hitler_adolf.json');
        $prov = $fixture['provenance'];
        $expected = $fixture['astro_setu_benchmark_assertions'];

        // 1. Calculate chart via native ephemeris
        $kundli = $this->kundliService->computeKundli(
            birthDate: $prov['birth_date'],
            birthTime: $prov['birth_time'],
            precision: 'verified',
            approximateWindow: null,
            latitude: $prov['coordinates']['latitude'],
            longitude: $prov['coordinates']['longitude'],
            timezone: $prov['timezone']
        );

        // 2. Lagna check: Libra (7)
        $this->assertSame($expected['lagna_rashi_id'], $kundli['ascendant']['id']);
        $this->assertSame($expected['lagna_name'], $kundli['ascendant']['name']);

        // 3. Evaluate A2 Yogas and Doshas
        $a2 = $this->orchestrator->evaluate($kundli['planets'], $kundli['ascendant']);

        // 4. Assert Ruchaka Yoga (Documented in Notable Horoscopes & 300 Combinations Chart 23)
        $pm = $a2['yogas']['panch_mahapurusha'];
        $this->assertTrue($pm['has_any_mahapurusha']);
        $this->assertTrue($pm['details']['ruchaka']['is_formed'], 'Ruchaka Yoga must be formed for Mars in Aries in 7th Kendra');
        $this->assertSame(7, $pm['details']['ruchaka']['house']);
        $this->assertSame(1, $pm['details']['ruchaka']['sign']['id']);

        // 5. Assert Manglik Dosha: Formed in 7th house and confirmed without cancellation
        $manglik = $a2['manglik_dosha'];
        $this->assertTrue($manglik['is_manglik_formed']);
        $this->assertSame(7, $manglik['source_house']);
        $this->assertSame('dosha_confirmed', $manglik['final_status']);
        $this->assertEmpty($manglik['applied_bhanga_ids']);

        // 6. Assert Budhaditya Yoga formed in Aries
        $this->assertTrue($a2['yogas']['budhaditya']['is_formed']);
    }

    /**
     * Benchmark Chart 2: Albert Einstein (Notable Horoscopes Ch. 45).
     * Validates: Malavya Yoga (Venus in Pisces in 10th Kendra) & Manglik Bhanga 3 (Capricorn in 8th house).
     */
    public function test_benchmark_einstein_malavya_yoga_and_cancelled_manglik_bhanga_3(): void
    {
        $fixture = $this->loadFixture('einstein_albert.json');
        $prov = $fixture['provenance'];
        $expected = $fixture['astro_setu_benchmark_assertions'];

        $kundli = $this->kundliService->computeKundli(
            birthDate: $prov['birth_date'],
            birthTime: $prov['birth_time'],
            precision: 'verified',
            approximateWindow: null,
            latitude: $prov['coordinates']['latitude'],
            longitude: $prov['coordinates']['longitude'],
            timezone: $prov['timezone']
        );

        // Lagna: Gemini (3)
        $this->assertSame($expected['lagna_rashi_id'], $kundli['ascendant']['id']);
        $this->assertSame($expected['lagna_name'], $kundli['ascendant']['name']);

        $a2 = $this->orchestrator->evaluate($kundli['planets'], $kundli['ascendant']);

        // Assert Malavya Yoga (Documented in Notable Horoscopes Ch. 58)
        $pm = $a2['yogas']['panch_mahapurusha'];
        $this->assertTrue($pm['has_any_mahapurusha']);
        $this->assertTrue($pm['details']['malavya']['is_formed'], 'Malavya Yoga formed by exalted Venus in Pisces in 10th Kendra');
        $this->assertSame(10, $pm['details']['malavya']['house']);

        // Assert Manglik Dosha: Formed in 8th house in Capricorn -> Cancelled by Bhanga 3!
        $manglik = $a2['manglik_dosha'];
        $this->assertTrue($manglik['is_manglik_formed']);
        $this->assertSame(8, $manglik['source_house']);
        $this->assertSame('dosha_cancelled', $manglik['final_status']);
        $this->assertContains('BHANGA_3_CAPRICORN_7TH_8TH', $manglik['applied_bhanga_ids']);

        // Assert Budhaditya Yoga formed in Pisces
        $this->assertTrue($a2['yogas']['budhaditya']['is_formed']);
    }

    /**
     * Benchmark Chart 3: King George VI (Notable Horoscopes Ch. 66; 300 Combinations Chart 21).
     * Validates: Sasa Yoga (Saturn in Libra in 1st Kendra), Hamsa Yoga, and non-Manglik status.
     */
    public function test_benchmark_george_vi_sasa_yoga_and_non_manglik(): void
    {
        $fixture = $this->loadFixture('george_vi.json');
        $prov = $fixture['provenance'];
        $expected = $fixture['astro_setu_benchmark_assertions'];

        $kundli = $this->kundliService->computeKundli(
            birthDate: $prov['birth_date'],
            birthTime: $prov['birth_time'],
            precision: 'verified',
            approximateWindow: null,
            latitude: $prov['coordinates']['latitude'],
            longitude: $prov['coordinates']['longitude'],
            timezone: $prov['timezone']
        );

        $this->assertSame($expected['lagna_rashi_id'], $kundli['ascendant']['id']);
        $this->assertSame($expected['lagna_name'], $kundli['ascendant']['name']);

        $a2 = $this->orchestrator->evaluate($kundli['planets'], $kundli['ascendant']);

        // Assert Sasa Yoga (Documented in 300 Combinations Chart 21 and Notable Horoscopes Ch. 66)
        $pm = $a2['yogas']['panch_mahapurusha'];
        $this->assertTrue($pm['has_any_mahapurusha']);
        $this->assertTrue($pm['details']['sasa']['is_formed'], 'Sasa Yoga formed by exalted Saturn in Libra in 1st Kendra');
        $this->assertSame(1, $pm['details']['sasa']['house']);

        // Assert Hamsa Yoga (Jupiter exalted in Cancer in 10th Kendra)
        $this->assertTrue($pm['details']['hamsa']['is_formed']);
        $this->assertSame(10, $pm['details']['hamsa']['house']);

        // Assert Malavya Yoga (Venus in Libra in 1st Kendra)
        $this->assertTrue($pm['details']['malavya']['is_formed']);
        $this->assertSame(1, $pm['details']['malavya']['house']);

        // Assert non-Manglik (Mars in Scorpio in 2nd house)
        $manglik = $a2['manglik_dosha'];
        $this->assertFalse($manglik['is_manglik_formed']);
        $this->assertSame('no_dosha', $manglik['final_status']);
    }

    /**
     * Benchmark Chart 4: Dr. B.V. Raman (Autobiography & Notable Horoscopes Preface).
     * Validates: Gaja Kesari Yoga (Jupiter in Scorpio in 7th Kendra from Moon in Taurus).
     */
    public function test_benchmark_raman_gaja_kesari_yoga(): void
    {
        $fixture = $this->loadFixture('raman_bv.json');
        $prov = $fixture['provenance'];
        $expected = $fixture['astro_setu_benchmark_assertions'];

        $kundli = $this->kundliService->computeKundli(
            birthDate: $prov['birth_date'],
            birthTime: $prov['birth_time'],
            precision: 'verified',
            approximateWindow: null,
            latitude: $prov['coordinates']['latitude'],
            longitude: $prov['coordinates']['longitude'],
            timezone: $prov['timezone']
        );

        $this->assertSame($expected['lagna_rashi_id'], $kundli['ascendant']['id']);
        $this->assertSame($expected['lagna_name'], $kundli['ascendant']['name']);

        $a2 = $this->orchestrator->evaluate($kundli['planets'], $kundli['ascendant']);

        // Assert Gaja Kesari Yoga (Documented by Raman as key foundation of his chart)
        $gk = $a2['yogas']['gaja_kesari'];
        $this->assertTrue($gk['is_formed'], 'Gaja Kesari formed along 4th/10th axis (Jupiter 7th from Moon)');
        $this->assertSame(7, $gk['house_from_moon']);

        // Assert Manglik Dosha: Mars in 7th house in Leo -> confirmed without cancellation
        $manglik = $a2['manglik_dosha'];
        $this->assertTrue($manglik['is_manglik_formed']);
        $this->assertSame(7, $manglik['source_house']);
        $this->assertSame('dosha_confirmed', $manglik['final_status']);
        $this->assertEmpty($manglik['applied_bhanga_ids']);
    }

    /**
     * Benchmark Chart 5: Rabindranath Tagore (Notable Horoscopes Ch. 35).
     * Validates: Budhaditya Yoga (Sun and Mercury conjunct in Aries).
     *
     * Note: Lagna is classified as Category C (unreliable / unsuitable for Lagna-dependent
     * assertions) due to historical birth-time ambiguity (2:38 AM vs 2:51 AM vs 4:02 AM).
     * However, the Sun and Mercury conjunction in Aries is invariant throughout the civil day
     * (Category B independent derivation).
     */
    public function test_benchmark_tagore_budhaditya_yoga(): void
    {
        $fixture = $this->loadFixture('tagore_rabindranath.json');
        $prov = $fixture['provenance'];
        $expected = $fixture['astro_setu_benchmark_assertions'];

        $kundli = $this->kundliService->computeKundli(
            birthDate: $prov['birth_date'],
            birthTime: $prov['birth_time'],
            precision: 'verified',
            approximateWindow: null,
            latitude: $prov['coordinates']['latitude'],
            longitude: $prov['coordinates']['longitude'],
            timezone: $prov['timezone']
        );

        $a2 = $this->orchestrator->evaluate($kundli['planets'], $kundli['ascendant']);

        // Assert Budhaditya Yoga (Sun & Mercury in Aries)
        $by = $a2['yogas']['budhaditya'];
        $this->assertTrue($by['is_formed'], 'Budhaditya Yoga formed with Sun and Mercury in Aries');
        $this->assertSame($expected['yogas']['budhaditya']['sign_id'], $by['rashi_id']);
    }

    /**
     * Benchmark Chart 6: B.V. Raman's Three Hundred Important Combinations, Chart 24.
     * Validates: Bhadra Yoga (Mercury in Virgo in 10th Kendra) and Cancer debilitation in 8th house.
     */
    public function test_benchmark_chart_24_bhadra_yoga_and_cancer_debilitation(): void
    {
        $fixture = $this->loadFixture('raman_300_chart_24.json');
        $prov = $fixture['provenance'];
        $expected = $fixture['astro_setu_benchmark_assertions'];

        $kundli = $this->kundliService->computeKundli(
            birthDate: $prov['birth_date'],
            birthTime: $prov['birth_time'],
            precision: 'verified',
            approximateWindow: null,
            latitude: $prov['coordinates']['latitude'],
            longitude: $prov['coordinates']['longitude'],
            timezone: $prov['timezone']
        );

        $this->assertSame($expected['lagna_rashi_id'], $kundli['ascendant']['id']);
        $this->assertSame($expected['lagna_name'], $kundli['ascendant']['name']);

        $a2 = $this->orchestrator->evaluate($kundli['planets'], $kundli['ascendant']);

        // Assert Bhadra Yoga (Documented in 300 Combinations Chart 24)
        $pm = $a2['yogas']['panch_mahapurusha'];
        $this->assertTrue($pm['has_any_mahapurusha']);
        $this->assertTrue($pm['details']['bhadra']['is_formed'], 'Bhadra Yoga formed by Mercury in Virgo in 10th Kendra');
        $this->assertSame(10, $pm['details']['bhadra']['house']);
        $this->assertSame(6, $pm['details']['bhadra']['sign']['id']);

        // Assert Manglik Dosha: Mars in Cancer (debilitated) in 8th house -> confirmed with ZERO cancellations
        $manglik = $a2['manglik_dosha'];
        $this->assertTrue($manglik['is_manglik_formed']);
        $this->assertSame(8, $manglik['source_house']);
        $this->assertTrue($manglik['is_debilitated']);
        $this->assertSame('dosha_confirmed', $manglik['final_status']);
        $this->assertEmpty($manglik['applied_bhanga_ids']);
    }
}
