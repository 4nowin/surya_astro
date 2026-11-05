<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Suite for Gate B Strict Calculation Eligibility in Work Package A5.
 *
 * Verifies:
 *  - Certified vs Qualified vs Suppressed vs Qualitative Fallback handling.
 *  - Verified birth time produces certified D-1, D-9, and Vimshottari sections.
 *  - Volatile birth time (approximate/unknown) suppresses D-1 Lagna and Pratyantardashas exceeding threshold.
 *  - D-9 Navamsha Lagna glyph suppression and unrotated sign distribution when volatile.
 *  - Parashari aspect matrix strictly restricted to 7 classical Grahas (Rahu/Ketu excluded).
 */
class A5GateBEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected DetailedKundliReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = app(DetailedKundliReportService::class);
    }

    /**
     * Test verified birth time achieves certified status for deterministic core sections.
     */
    public function test_verified_birth_time_achieves_certified_gate_b_status(): void
    {
        $payload = [
            'name' => 'Aditi Sharma',
            'date_of_birth' => '1992-05-15',
            'birth_time' => '14:30:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
        ];

        $report = $this->reportService->buildReportData($payload, 'en');

        // Verify meta
        $this->assertEquals('M9.2_A5_V1', $report['meta']['engine_version']);
        $this->assertEquals(UserAstrologyProfile::PRECISION_VERIFIED, $report['meta']['precision_tier']);

        $sections = $report['sections'];

        // Section 1: Cover
        $this->assertEquals('certified', $sections['sec1_cover']['gate_b_status']);

        // Section 2: Panchanga
        $this->assertContains($sections['sec2_panchanga']['gate_b_status'], ['certified', 'qualified']);
        $this->assertNotEmpty($sections['sec2_panchanga']['nadi_disclaimer']);

        // Section 3: Lagna Kundli D-1
        $this->assertEquals('certified', $sections['sec3_lagna_d1']['gate_b_status']);
        $this->assertFalse($sections['sec3_lagna_d1']['is_suppressed']);
        $this->assertNotEmpty($sections['sec3_lagna_d1']['svg']);
        $this->assertCount(9, $sections['sec3_lagna_d1']['planets']);

        // Section 4: Chandra Kundli
        $this->assertEquals('certified', $sections['sec4_chandra']['gate_b_status']);
        $this->assertFalse($sections['sec4_chandra']['is_suppressed']);

        // Section 5: D-9 Navamsha
        $this->assertEquals('certified', $sections['sec5_navamsha_d9']['gate_b_status']);
        $this->assertNotEmpty($sections['sec5_navamsha_d9']['svg']);

        // Section 6: Parashari Graha Drishti Matrix (strictly 7 Grahas, no Rahu/Ketu)
        $this->assertEquals('certified', $sections['sec6_aspects']['gate_b_status']);
        foreach ($sections['sec6_aspects']['aspects'] as $asp) {
            $this->assertNotEquals('Rahu', $asp['source']);
            $this->assertNotEquals('Ketu', $asp['source']);
            $this->assertNotEquals('Rahu', $asp['target']);
            $this->assertNotEquals('Ketu', $asp['target']);
        }

        // Section 7: Janma Nakshatra
        $this->assertContains($sections['sec7_nakshatra']['gate_b_status'], ['certified', 'qualified']);

        // Section 8: Vimshottari Timeline
        $this->assertEquals('certified', $sections['sec8_vimshottari_timeline']['gate_b_status']);
        $this->assertNotEmpty($sections['sec8_vimshottari_timeline']['timeline']);

        // Section 11: Manglik Dosha Audit
        $this->assertEquals('certified', $sections['sec11_manglik_dosha']['gate_b_status']);
        $this->assertNotEmpty($sections['sec11_manglik_dosha']['headline']);

        // Section 13: Remedies
        $this->assertEquals('certified', $sections['sec13_remedies']['gate_b_status']);
        $this->assertNotEmpty($sections['sec13_remedies']['gemstone_disclaimer']);

        // Section 15: Precision Audit
        $this->assertEquals('certified', $sections['sec15_precision_audit']['gate_b_status']);
        $this->assertCount(15, $sections['sec15_precision_audit']['audit_table']);
    }

    /**
     * Test unknown birth time suppresses D-1 Lagna Kundli and hides house labels in aspects.
     */
    public function test_unknown_birth_time_suppresses_d1_lagna_and_hides_aspect_house_labels(): void
    {
        $payload = [
            'name' => 'Rohan Verma',
            'date_of_birth' => '1988-11-20',
            'birth_time' => null,
            'birth_time_precision' => UserAstrologyProfile::PRECISION_UNKNOWN,
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Mumbai',
        ];

        $report = $this->reportService->buildReportData($payload, 'en');
        $sections = $report['sections'];

        // Section 3: Lagna Kundli must be suppressed on 24h unknown interval
        $this->assertEquals('suppressed', $sections['sec3_lagna_d1']['gate_b_status']);
        $this->assertTrue($sections['sec3_lagna_d1']['is_suppressed']);
        $this->assertNull($sections['sec3_lagna_d1']['svg']);
        $this->assertNotEmpty($sections['sec3_lagna_d1']['reason']);

        // Section 6: Aspect matrix must hide house labels on unknown precision
        $this->assertTrue($sections['sec6_aspects']['hide_house_labels']);
        foreach ($sections['sec6_aspects']['aspects'] as $asp) {
            $this->assertNull($asp['source_house']);
            $this->assertNull($asp['target_house']);
        }

        // Section 10: Classical yogas should have lagna-dependent yogas suppressed
        $this->assertTrue($sections['sec10_classical_yogas']['is_lagna_yogas_suppressed']);
    }

    /**
     * Test volatile D-9 Navamsha Lagna causes D-9 to render as unrotated sign distribution.
     */
    public function test_volatile_d9_lagna_renders_unrotated_sign_distribution(): void
    {
        $payload = [
            'name' => 'Vikram Rao',
            'date_of_birth' => '1995-08-10',
            'birth_time' => null,
            'birth_time_precision' => UserAstrologyProfile::PRECISION_APPROXIMATE,
            'approximate_time_window' => 'afternoon', // 4-hour window
            'latitude' => 12.9716,
            'longitude' => 77.5946,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Bengaluru',
        ];

        $report = $this->reportService->buildReportData($payload, 'en');
        $sections = $report['sections'];

        $sec5 = $sections['sec5_navamsha_d9'];
        $this->assertEquals('certified', $sec5['gate_b_status']);
        $this->assertFalse($sec5['d9_ascendant_invariant']);
        $this->assertNotEmpty($sec5['note']);
        // The SVG should contain "D-9 Navamsha (Sign Distribution)"
        $this->assertStringContainsString('D-9 Navamsha (Sign Distribution)', $sec5['svg']);
    }
}
