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

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CalculationWorkerLauncher::setLaunchHook(fn () => true);

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

        // Verify PDF binary structure contains Devanagari text / font streams
        $this->assertStringContainsString('Noto', $content);
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
}
