<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Astrology\Interpretation\DetailedInterpretationService;
use Tests\TestCase;

/**
 * Test Suite for Bilingual Deterministic Interpretations in Work Package A5.
 *
 * Verifies:
 *  - 100% deterministic rule-based output (no external LLM API dependency).
 *  - Strict English ('en') and Hindi ('hi') parity.
 *  - Mandatory Purushartha ethical framing.
 *  - Mandatory Non-medical Nadi disclaimer.
 *  - Mandatory Physical Gemstone algorithmic prescription disclaimer.
 */
class A5BilingualInterpretationTest extends TestCase
{
    protected DetailedInterpretationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DetailedInterpretationService::class);
    }

    /**
     * Test mandatory disclaimers exist with full English and Hindi parity.
     */
    public function test_mandatory_disclaimers_have_full_bilingual_parity(): void
    {
        // 1. Purushartha Framing
        $this->assertNotEmpty(DetailedInterpretationService::PURUSHARTHA_FRAMING['en']);
        $this->assertNotEmpty(DetailedInterpretationService::PURUSHARTHA_FRAMING['hi']);
        $this->assertStringContainsString('Purushartha', DetailedInterpretationService::PURUSHARTHA_FRAMING['en']);
        $this->assertStringContainsString('पुरुषार्थ', DetailedInterpretationService::PURUSHARTHA_FRAMING['hi']);

        // 2. Non-Medical Nadi Disclaimer
        $this->assertNotEmpty(DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER['en']);
        $this->assertNotEmpty(DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER['hi']);
        $this->assertStringContainsString('medical', strtolower(DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER['en']));
        $this->assertStringContainsString('चिकित्सक', DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER['hi']);

        // 3. Physical Gemstone Algorithmic Prescription Disclaimer
        $this->assertNotEmpty(DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER['en']);
        $this->assertNotEmpty(DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER['hi']);
        $this->assertStringContainsString('NOT algorithmically prescribe gemstones', DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER['en']);
        $this->assertStringContainsString('कंप्यूटर-जनित या स्वचालित परामर्श प्रदान नहीं करता', DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER['hi']);

        // 4. General Disclaimer
        $this->assertNotEmpty(DetailedInterpretationService::GENERAL_DISCLAIMER['en']);
        $this->assertNotEmpty(DetailedInterpretationService::GENERAL_DISCLAIMER['hi']);
    }

    /**
     * Test planetary dignities return valid strings in both locales.
     */
    public function test_planetary_dignity_interpretations(): void
    {
        $dignities = ['exalted', 'moolatrikona', 'own_sign', 'friendly', 'neutral', 'enemy', 'debilitated'];

        foreach ($dignities as $dig) {
            $en = $this->service->getDignityInterpretation('jupiter', $dig, 'en');
            $hi = $this->service->getDignityInterpretation('jupiter', $dig, 'hi');

            $this->assertNotEmpty($en);
            $this->assertNotEmpty($hi);
            $this->assertNotEquals($en, $hi);
        }
    }

    /**
     * Test Janma Nakshatra deep dive returns deities, symbols, and guidance in both languages.
     */
    public function test_nakshatra_deep_dive_bilingual(): void
    {
        $nakshatras = ['Ashwini', 'Rohini', 'Pushya', 'Chitra', 'Revati'];

        foreach ($nakshatras as $nak) {
            $en = $this->service->getNakshatraDeepDive($nak, 2, 'en');
            $hi = $this->service->getNakshatraDeepDive($nak, 2, 'hi');

            $this->assertNotEmpty($en['deity']);
            $this->assertNotEmpty($hi['deity']);
            $this->assertNotEmpty($en['symbol']);
            $this->assertNotEmpty($hi['symbol']);
            $this->assertNotEmpty($en['nature']);
            $this->assertNotEmpty($hi['nature']);
            $this->assertNotEmpty($en['guidance']);
            $this->assertNotEmpty($hi['guidance']);
            $this->assertStringContainsString('Pada 2', $en['pada_note']);
            $this->assertStringContainsString('चरण 2', $hi['pada_note']);
        }
    }

    /**
     * Test classical Yogas interpretations.
     */
    public function test_classical_yogas_bilingual(): void
    {
        $yogas = ['ruchaka', 'bhadra', 'hamsa', 'malavya', 'sasa', 'gajakesari', 'budhaditya'];

        foreach ($yogas as $y) {
            $en = $this->service->getYogaInterpretation($y, [], 'en');
            $hi = $this->service->getYogaInterpretation($y, [], 'hi');

            $this->assertNotEmpty($en['name']);
            $this->assertNotEmpty($hi['name']);
            $this->assertNotEmpty($en['definition']);
            $this->assertNotEmpty($hi['definition']);
            $this->assertNotEmpty($en['qualities']);
            $this->assertNotEmpty($hi['qualities']);
            $this->assertNotEmpty($en['ethical_advice']);
            $this->assertNotEmpty($hi['ethical_advice']);
        }
    }

    /**
     * Test Manglik Dosha interpretations with classical cancellation and ethical advice.
     */
    public function test_manglik_dosha_interpretations(): void
    {
        // 1. Manglik absent
        $resAbsentEn = $this->service->getManglikInterpretation(['has_manglik_dosha' => false], 'en');
        $this->assertEquals('Manglik Dosha Absent', $resAbsentEn['headline']);

        // 2. Manglik present with classical cancellation
        $resCancelledEn = $this->service->getManglikInterpretation([
            'has_manglik_dosha' => true,
            'is_cancelled' => true,
            'bhangas' => [
                ['rule' => 'Functional benefic Jupiter lordship in Kendra'],
            ],
        ], 'en');
        $this->assertStringContainsString('Mitigated / Cancelled', $resCancelledEn['headline']);
        $this->assertStringContainsString('Mars in Cancer (debilitation) is explicitly NOT a valid cancellation', $resCancelledEn['cancellation_note']);

        $resCancelledHi = $this->service->getManglikInterpretation([
            'has_manglik_dosha' => true,
            'is_cancelled' => true,
            'bhangas' => [
                ['rule' => 'Functional benefic Jupiter lordship in Kendra', 'name_hi' => 'केंद्रस्थ शुभ गुरु आधिपत्य'],
            ],
        ], 'hi');
        $this->assertStringContainsString('मांगलिक दोष भंग', $resCancelledHi['headline']);
        $this->assertStringContainsString('कर्क राशि में मंगल की नीच स्थिति मांगलिक दोष का भंग नहीं मानी जाती', $resCancelledHi['cancellation_note']);
    }

    /**
     * Test Saturn transits interpretations in both locales.
     */
    public function test_saturn_transits_interpretations(): void
    {
        $phases = ['rising', 'peak', 'setting', 'kantaka', 'ashtama', 'none'];

        foreach ($phases as $ph) {
            $en = $this->service->getSaturnTransitInterpretation($ph, true, 'en');
            $hi = $this->service->getSaturnTransitInterpretation($ph, true, 'hi');

            $this->assertNotEmpty($en['title']);
            $this->assertNotEmpty($hi['title']);
            $this->assertStringContainsString('Retrograde', $en['title']);
            $this->assertStringContainsString('वक्री', $hi['title']);
            $this->assertNotEmpty($en['core']);
            $this->assertNotEmpty($hi['core']);
            $this->assertNotEmpty($en['advice']);
            $this->assertNotEmpty($hi['advice']);
        }
    }

    /**
     * Test Vedic Upay remedies with Beej mantras, charity, and lifestyle.
     */
    public function test_vedic_upay_remedies(): void
    {
        $planets = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'];

        foreach ($planets as $p) {
            $en = $this->service->getVedicRemedies($p, 'en');
            $hi = $this->service->getVedicRemedies($p, 'hi');

            $this->assertNotEmpty($en['mantra']);
            $this->assertNotEmpty($en['dana']);
            $this->assertNotEmpty($hi['dana']);
            $this->assertNotEmpty($en['lifestyle']);
            $this->assertNotEmpty($hi['lifestyle']);
            $this->assertStringContainsString('ॐ', $en['mantra']);
        }
    }
}
