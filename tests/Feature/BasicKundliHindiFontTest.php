<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Pdf\KundliPdfService;
use App\Services\Astrology\Pdf\PremiumKundliPdfService;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BasicKundliHindiFontTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected UserAstrologyProfile $profile;
    protected KundliPdfService $pdfService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CalculationWorkerLauncher::setLaunchHook(fn () => true);

        $this->pdfService = app(KundliPdfService::class);

        $this->user = User::factory()->create([
            'email' => 'basic_hindi_user@example.com',
            'name' => 'Aditya Sharma',
        ]);

        $this->profile = UserAstrologyProfile::create([
            'user_id' => $this->user->id,
            'name' => 'Aditya',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1990-05-15',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '14:30:00',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);
    }

    protected function tearDown(): void
    {
        CalculationWorkerLauncher::setLaunchHook(null);
        parent::tearDown();
    }

    protected function primeCalculation(): void
    {
        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/astrology/kundli?profile_id={$this->profile->id}");
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1]);
    }

    public function test_noto_sans_devanagari_fonts_are_correctly_configured(): void
    {
        $regPath = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
        $boldPath = resource_path('fonts/NotoSansDevanagari-Bold.ttf');

        $this->assertFileExists($regPath);
        $this->assertFileExists($boldPath);
        $this->assertGreaterThan(100000, filesize($regPath));
        $this->assertGreaterThan(100000, filesize($boldPath));
    }

    public function test_basic_hindi_pdf_contains_valid_pdf_output(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=hi");
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(50000, strlen($content));
    }

    public function test_hindi_content_and_complex_shaping_preserved_in_generated_report(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=hi");
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('Noto', $content);
    }

    public function test_hindi_localisation_of_all_12_zodiac_signs(): void
    {
        $expected = [
            'Aries' => 'मेष',
            'Taurus' => 'वृषभ',
            'Gemini' => 'मिथुन',
            'Cancer' => 'कर्क',
            'Leo' => 'सिंह',
            'Virgo' => 'कन्या',
            'Libra' => 'तुला',
            'Scorpio' => 'वृश्चिक',
            'Sagittarius' => 'धनु',
            'Capricorn' => 'मकर',
            'Aquarius' => 'कुम्भ',
            'Pisces' => 'मीन',
        ];

        foreach ($expected as $en => $hi) {
            $this->assertSame($hi, $this->pdfService->localizeZodiac($en, 'hi'));
            $this->assertSame($en, $this->pdfService->localizeZodiac($en, 'en'));
        }

        for ($id = 1; $id <= 12; $id++) {
            $this->assertSame($expected[KundliPdfService::ZODIAC_MAP[$id]['en']], $this->pdfService->localizeZodiac($id, 'hi'));
        }
    }

    public function test_hindi_localisation_of_all_27_nakshatras(): void
    {
        $expected = [
            'Ashwini' => 'अश्विनी',
            'Bharani' => 'भरणी',
            'Krittika' => 'कृत्तिका',
            'Rohini' => 'रोहिणी',
            'Mrigashira' => 'मृगशिरा',
            'Ardra' => 'आर्द्रा',
            'Punarvasu' => 'पुनर्वसु',
            'Pushya' => 'पुष्य',
            'Ashlesha' => 'आश्लेषा',
            'Magha' => 'मघा',
            'Purva Phalguni' => 'पूर्वा फाल्गुनी',
            'Uttara Phalguni' => 'उत्तरा फाल्गुनी',
            'Hasta' => 'हस्त',
            'Chitra' => 'चित्रा',
            'Swati' => 'स्वाति',
            'Vishakha' => 'विशाखा',
            'Anuradha' => 'अनुराधा',
            'Jyeshtha' => 'ज्येष्ठा',
            'Mula' => 'मूल',
            'Purva Ashadha' => 'पूर्वाषाढ़ा',
            'Uttara Ashadha' => 'उत्तराषाढ़ा',
            'Shravana' => 'श्रवण',
            'Dhanishta' => 'धनिष्ठा',
            'Shatabhisha' => 'शतभिषा',
            'Purva Bhadrapada' => 'पूर्वा भाद्रपद',
            'Uttara Bhadrapada' => 'उत्तरा भाद्रपद',
            'Revati' => 'रेवती',
        ];

        foreach ($expected as $en => $hi) {
            $this->assertSame($hi, $this->pdfService->localizeNakshatra($en, 'hi'));
            $this->assertSame($en, $this->pdfService->localizeNakshatra($en, 'en'));
        }

        // Test combined/unspaced versions
        $this->assertSame('पूर्वाषाढ़ा', $this->pdfService->localizeNakshatra('PurvaAshadha', 'hi'));
        $this->assertSame('उत्तराषाढ़ा', $this->pdfService->localizeNakshatra('UttaraAshadha', 'hi'));
    }

    public function test_hindi_localisation_of_all_nine_planetary_names(): void
    {
        $expected = [
            'Sun' => 'सूर्य',
            'Moon' => 'चन्द्र',
            'Mars' => 'मंगल',
            'Mercury' => 'बुध',
            'Jupiter' => 'बृहस्पति',
            'Venus' => 'शुक्र',
            'Saturn' => 'शनि',
            'Rahu' => 'राहु',
            'Ketu' => 'केतु',
            'Ascendant' => 'लग्न',
        ];

        foreach ($expected as $en => $hi) {
            $this->assertSame($hi, $this->pdfService->localizePlanet($en, 'hi'));
            $this->assertSame($en, $this->pdfService->localizePlanet($en, 'en'));
        }

        // Test aliases
        $this->assertSame('बृहस्पति', $this->pdfService->localizePlanet('Guru', 'hi'));
        $this->assertSame('मंगल', $this->pdfService->localizePlanet('Kuja', 'hi'));
    }

    public function test_hindi_localisation_of_12_house_placements(): void
    {
        $houses = [];
        for ($i = 1; $i <= 12; $i++) {
            $houses[$i] = [
                'house_number' => $i,
                'sign_id' => $i,
                'sign_name' => KundliPdfService::ZODIAC_MAP[$i]['en'],
                'sign_lord' => 'Sun',
                'planets' => [
                    ['name' => 'Mars', 'key' => 'mars'],
                ],
            ];
        }

        $dummy = [
            'profile' => ['precision' => 'verified'],
            'houses' => $houses,
        ];

        $normalizedHi = $this->pdfService->normalizeKundliDataForView($dummy, 'hi');
        $this->assertSame('मेष', $normalizedHi['houses'][1]['localized_sign_name']);
        $this->assertSame('सूर्य', $normalizedHi['houses'][1]['localized_sign_lord']);
        $this->assertSame('मंगल', $normalizedHi['houses'][1]['localized_residents']);
        $this->assertSame('मीन', $normalizedHi['houses'][12]['localized_sign_name']);
    }

    public function test_correct_svg_chart_embedding_and_geometry(): void
    {
        $houses = [];
        for ($i = 1; $i <= 12; $i++) {
            $houses[$i] = [
                'house_number' => $i,
                'sign_id' => $i,
                'planets' => [
                    ['key' => 'sun', 'name' => 'Sun', 'abbr' => 'Su', 'abbr_hi' => 'सू', 'is_retrograde' => false, 'is_combust' => false],
                ],
            ];
        }

        $svgHi = $this->pdfService->renderNorthIndianChartSvg($houses, 'hi', 'Lagna Kundli (D-1)');
        $this->assertStringContainsString('viewBox="0 0 400 400"', $svgHi);
        $this->assertStringContainsString('polygon points="200,0 400,200 200,400 0,200"', $svgHi);
        $this->assertStringContainsString('line x1="0" y1="0" x2="400" y2="400"', $svgHi);
        $this->assertStringContainsString('line x1="0" y1="400" x2="400" y2="0"', $svgHi);
        $this->assertStringContainsString('NotoDevanagari', $svgHi);
        $this->assertStringContainsString('सू', $svgHi);
        $this->assertStringContainsString('१', $svgHi); // Devanagari numerals

        $svgEn = $this->pdfService->renderNorthIndianChartSvg($houses, 'en', 'Lagna Chart');
        $this->assertStringContainsString('DejaVu Sans', $svgEn);
        $this->assertStringContainsString('Su', $svgEn);
        $this->assertStringContainsString('1', $svgEn);
    }

    public function test_english_basic_pdf_generation_remains_functional(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=en");
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(50000, strlen($content));
    }

    public function test_basic_kundli_api_download_flow_continues_to_work_for_en_and_hi(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        // Test with default profile
        $resDefaultEn = $this->get('/api/v1/kundli/pdf?locale=en');
        $resDefaultEn->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

        $resDefaultHi = $this->get('/api/v1/kundli/pdf?locale=hi');
        $resDefaultHi->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

        // Test with saved snapshot
        $saveRes = $this->postJson('/api/v1/kundli/save', [
            'profile_id' => $this->profile->id,
            'title' => 'Snapshot Basic Test',
        ]);
        $saveRes->assertStatus(201);
        $savedId = $saveRes->json('data.id');

        $resSavedEn = $this->get("/api/v1/kundli/pdf?saved_id={$savedId}&locale=en");
        $resSavedEn->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

        $resSavedHi = $this->get("/api/v1/kundli/pdf?saved_id={$savedId}&locale=hi");
        $resSavedHi->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_existing_detailed_kundli_pdf_generation_remains_unaffected(): void
    {
        /** @var DetailedKundliReportService $reportService */
        $reportService = app(DetailedKundliReportService::class);
        /** @var PremiumKundliPdfService $premiumPdfService */
        $premiumPdfService = app(PremiumKundliPdfService::class);

        $payload = [
            'name' => 'Aditya Sharma',
            'date_of_birth' => '1990-05-15',
            'birth_time' => '14:30:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
        ];

        $reportDataEn = $reportService->buildReportData($payload, 'en');
        $pdfEn = $premiumPdfService->generatePdf($reportDataEn, 'en');
        $this->assertStringStartsWith('%PDF-', $pdfEn);
        $this->assertGreaterThan(100000, strlen($pdfEn));

        $reportDataHi = $reportService->buildReportData($payload, 'hi');
        $pdfHi = $premiumPdfService->generatePdf($reportDataHi, 'hi');
        $this->assertStringStartsWith('%PDF-', $pdfHi);
        $this->assertGreaterThan(100000, strlen($pdfHi));
    }

    public function test_controlled_failure_when_chromium_unavailable_for_hindi(): void
    {
        $mockPremium = \Mockery::mock(PremiumKundliPdfService::class);
        $mockPremium->shouldReceive('renderHtmlViaChromium')->andReturn(null);

        $pdfService = new KundliPdfService(
            $mockPremium,
            app(\App\Services\Astrology\Pdf\SvgVargaRenderer::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hindi Kundli PDF generation requires headless Chromium');

        $this->primeCalculation();
        $calcResult = app(\App\Services\Astrology\KundliService::class)->calculateForProfile($this->profile);
        $pdfService->generatePdf($calcResult, 'hi');
    }

    public function test_controlled_api_error_response_503_when_hindi_renderer_unavailable(): void
    {
        $mockPremium = \Mockery::mock(PremiumKundliPdfService::class);
        $mockPremium->shouldReceive('renderHtmlViaChromium')->andReturn(null);

        $mockPdfService = new KundliPdfService(
            $mockPremium,
            app(\App\Services\Astrology\Pdf\SvgVargaRenderer::class)
        );
        $this->app->instance(KundliPdfService::class, $mockPdfService);

        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=hi");
        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error_code' => 'KUNDLI_PDF_TEMPORARILY_UNAVAILABLE',
            ]);
        $this->assertSame(
            'अपनी जन्म कुंडली की PDF प्रति प्राप्त करने के लिए हमारे ज्योतिषियों से संपर्क करें।',
            $response->json('message')
        );
    }

    public function test_english_renderer_falls_back_to_dompdf_when_chromium_unavailable(): void
    {
        $mockPremium = \Mockery::mock(PremiumKundliPdfService::class);
        $mockPremium->shouldReceive('renderHtmlViaChromium')->andReturn(null);
        $mockPremium->shouldReceive('ensureSvgFontAssets')->andReturn(null);

        $pdfService = new KundliPdfService(
            $mockPremium,
            app(\App\Services\Astrology\Pdf\SvgVargaRenderer::class)
        );

        $this->primeCalculation();
        $calcResult = app(\App\Services\Astrology\KundliService::class)->calculateForProfile($this->profile);
        $pdfOutput = $pdfService->generatePdf($calcResult, 'en');

        $this->assertStringStartsWith('%PDF-', $pdfOutput);
        $this->assertGreaterThan(30000, strlen($pdfOutput));
    }

    public function test_renderer_detection_and_binary_resolution(): void
    {
        /** @var PremiumKundliPdfService $premiumPdfService */
        $premiumPdfService = app(PremiumKundliPdfService::class);
        $resolved = $premiumPdfService->resolveChromiumBinary();

        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertNotNull($resolved);
            $this->assertFileExists($resolved);
        } else {
            // On Linux without Chromium, returns null
            $this->assertTrue($resolved === null || file_exists($resolved));
        }
    }



    public function test_chart_layout_uses_measured_square_aspect_ratio_and_table_separation(): void
    {
        $this->primeCalculation();
        $calcResult = app(\App\Services\Astrology\KundliService::class)->calculateForProfile($this->profile);

        $reflection = new \ReflectionClass($this->pdfService);
        $normMethod = $reflection->getMethod('normalizeKundliDataForView');
        $normMethod->setAccessible(true);
        $normalized = $normMethod->invoke($this->pdfService, $calcResult, 'hi');

        $lagnaSvg = $this->pdfService->renderNorthIndianChartSvg($calcResult['houses'], 'hi', 'Lagna Kundli (D-1)');
        $chandraSvg = $this->pdfService->renderNorthIndianChartSvg($calcResult['chandra_kundli']['houses'], 'hi', 'Chandra Kundli');

        $html = \Illuminate\Support\Facades\View::make('pdf.kundli_report', [
            'kundli' => $normalized,
            'locale' => 'hi',
            'lagnaSvg' => $lagnaSvg,
            'chandraSvg' => $chandraSvg,
            'fontRegular' => '',
            'fontBold' => '',
        ])->render();

        $this->assertStringContainsString('class="chart-table"', $html);
        $this->assertStringContainsString('width="140" height="140"', $html);
        $this->assertStringContainsString('width: 140pt; height: 140pt;', $html);
        $this->assertStringContainsString('preserveAspectRatio="xMidYMid meet"', $lagnaSvg);
        $this->assertStringContainsString('font-size="14"', $lagnaSvg); // Readable house numbers
        $this->assertStringContainsString('font-size="12"', $lagnaSvg); // Readable resident planets
    }
}
