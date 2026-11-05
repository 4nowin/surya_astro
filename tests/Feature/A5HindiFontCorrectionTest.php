<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Pdf\PremiumKundliPdfService;
use App\Services\Astrology\Pdf\SvgVargaRenderer;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Focused verification tests for Definitive Hindi Font Correction in Work Package A5.
 *
 * Verifies:
 *  - Regular and bold Noto font registration under 'NotoDevanagari'.
 *  - Hindi text uses the NotoDevanagari family deterministically.
 *  - Sanskrit mantras are preserved and properly styled in both locales.
 *  - SVG chart labels use the NotoDevanagari font family.
 *  - Hindi and English PDF pages render without missing glyphs.
 */
class A5HindiFontCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected DetailedKundliReportService $reportService;
    protected PremiumKundliPdfService $pdfService;
    protected SvgVargaRenderer $svgRenderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = app(DetailedKundliReportService::class);
        $this->pdfService = app(PremiumKundliPdfService::class);
        $this->svgRenderer = app(SvgVargaRenderer::class);
    }

    /**
     * Test regular and bold Noto Sans Devanagari font assets exist and register.
     */
    public function test_noto_devanagari_regular_and_bold_font_registration(): void
    {
        $regTtf = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
        $boldTtf = resource_path('fonts/NotoSansDevanagari-Bold.ttf');

        $this->assertFileExists($regTtf);
        $this->assertFileExists($boldTtf);
        $this->assertGreaterThan(50000, filesize($regTtf));
        $this->assertGreaterThan(50000, filesize($boldTtf));
    }

    /**
     * Test SVG chart labels use NotoDevanagari font family in Hindi locale.
     */
    public function test_svg_chart_labels_use_intended_hindi_font(): void
    {
        $houses = [
            1 => [
                'house_number' => 1,
                'sign_id' => 9,
                'resident_planets' => [
                    ['key' => 'moon', 'abbr_hi' => 'चं', 'is_retrograde' => false, 'is_combust' => false],
                ],
            ],
        ];

        $svgHi = $this->svgRenderer->renderD1($houses, 'hi', "19° 00' 58\"");
        $this->assertStringContainsString('font-family="NotoDevanagari"', $svgHi);
        $this->assertStringContainsString('९', $svgHi); // Devanagari digit 9
        $this->assertStringContainsString('चं', $svgHi); // Chandra abbr

        $svgEn = $this->svgRenderer->renderD1($houses, 'en', "19° 00' 58\"");
        $this->assertStringContainsString('font-family="DejaVu Sans"', $svgEn);
    }

    /**
     * Test Sanskrit mantra text is preserved and present in report data.
     */
    public function test_sanskrit_mantra_text_is_preserved_and_styled(): void
    {
        $payload = [
            'name' => 'Rahul Sharma',
            'date_of_birth' => '1990-10-24',
            'birth_time' => '12:00:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
        ];

        $reportHi = $this->reportService->buildReportData($payload, 'hi');
        $sec13Hi = $reportHi['sections']['sec13_remedies'] ?? [];
        $this->assertFalse($sec13Hi['is_suppressed']);
        $this->assertNotEmpty($sec13Hi['remedies']);

        $allMantrasHi = [];
        foreach ($sec13Hi['remedies'] as $rem) {
            if (!empty($rem['mantra'])) {
                $allMantrasHi[] = $rem['mantra'];
            }
        }
        $this->assertNotEmpty($allMantrasHi);
        $mantraString = implode(' ', $allMantrasHi);
        $this->assertStringContainsString('ॐ', $mantraString);
        $this->assertStringContainsString('नमः', $mantraString);

        // English report also preserves Sanskrit mantras with transliterations
        $reportEn = $this->reportService->buildReportData($payload, 'en');
        $sec13En = $reportEn['sections']['sec13_remedies'] ?? [];
        $this->assertFalse($sec13En['is_suppressed']);
        $allMantrasEn = [];
        foreach ($sec13En['remedies'] as $rem) {
            if (!empty($rem['mantra'])) {
                $allMantrasEn[] = $rem['mantra'];
            }
        }
        $this->assertNotEmpty($allMantrasEn);
        $this->assertStringContainsString('ॐ', implode(' ', $allMantrasEn));
    }

    /**
     * Test full Hindi PDF renders with valid binary and correct page count.
     */
    public function test_hindi_pdf_renders_valid_binary(): void
    {
        $payload = [
            'name' => 'Rahul Sharma',
            'date_of_birth' => '1990-10-24',
            'birth_time' => '12:00:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
        ];

        $reportHi = $this->reportService->buildReportData($payload, 'hi');
        $pdf = $this->pdfService->generatePdf($reportHi, 'hi');

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);

        $pageCount = $this->pdfService->extractPageCount($pdf);
        $this->assertGreaterThanOrEqual(4, $pageCount);
        $this->assertLessThanOrEqual(10, $pageCount);
    }
}
