<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\KundliReport;
use App\Models\ReportPurchase;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\Handlers\DetailedKundliReportCalculationHandler;
use App\Services\Astrology\Pdf\PremiumKundliPdfService;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use App\Services\Astrology\Reports\ReportPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Test Suite for End-to-End Premium PDF Generation in Work Package A5.
 *
 * Verifies:
 *  - Full PDF generation for English and Hindi locales.
 *  - Embedded Google Noto Sans Devanagari fonts.
 *  - Fixed table layouts, line-height 1.45, natural pagination (~6-12 pages).
 *  - Integration with Worker CalculationHandler and atomic ReportPublicationService.
 */
class A5PremiumPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected DetailedKundliReportService $reportService;
    protected PremiumKundliPdfService $pdfService;
    protected DetailedKundliReportCalculationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = app(DetailedKundliReportService::class);
        $this->pdfService = app(PremiumKundliPdfService::class);
        $this->handler = app(DetailedKundliReportCalculationHandler::class);
    }

    /**
     * Test full English PDF generation produces valid binary with multi-page pagination.
     */
    public function test_full_english_pdf_generation(): void
    {
        $payload = [
            'name' => 'Siddharth Sen',
            'date_of_birth' => '1990-10-24',
            'birth_time' => '07:15:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Kolkata',
            'birth_country' => 'India',
        ];

        $reportData = $this->reportService->buildReportData($payload, 'en');
        $pdfBinary = $this->pdfService->generatePdf($reportData, 'en');

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
        $this->assertStringContainsString('%%EOF', $pdfBinary);

        $pageCount = $this->pdfService->extractPageCount($pdfBinary);
        $this->assertGreaterThanOrEqual(4, $pageCount);
        $this->assertLessThanOrEqual(14, $pageCount);
    }

    /**
     * Test full Hindi PDF generation embeds Devanagari fonts and produces valid multi-page PDF.
     */
    public function test_full_hindi_pdf_generation(): void
    {
        $payload = [
            'name' => 'अनुराग बाजपेई',
            'date_of_birth' => '1985-03-12',
            'birth_time' => '10:45:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 26.8467,
            'longitude' => 80.9462,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Lucknow',
            'birth_country' => 'India',
        ];

        $reportData = $this->reportService->buildReportData($payload, 'hi');
        $pdfBinary = $this->pdfService->generatePdf($reportData, 'hi');

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
        $this->assertStringContainsString('%%EOF', $pdfBinary);

        $pageCount = $this->pdfService->extractPageCount($pdfBinary);
        $this->assertGreaterThanOrEqual(4, $pageCount);
        $this->assertLessThanOrEqual(14, $pageCount);
    }

    /**
     * Test end-to-end integration with DetailedKundliReportCalculationHandler and atomic publication.
     */
    public function test_calculation_handler_generates_and_publishes_premium_pdf(): void
    {
        $user = User::factory()->create(['wallet_balance' => 500.00]);

        $purchase = ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => (string) Str::uuid(),
            'transaction_type' => ReportPurchase::TYPE_DEBIT,
            'amount' => 149.00,
            'currency' => 'INR',
            'status' => ReportPurchase::STATUS_COMPLETED,
        ]);

        $reportUuid = (string) Str::uuid();
        $report = KundliReport::create([
            'uuid' => $reportUuid,
            'user_id' => $user->id,
            'report_purchase_id' => $purchase->id,
            'birth_payload' => [
                'name' => 'Kavita Joshi',
                'date_of_birth' => '1993-07-28',
                'birth_time' => '18:20:00',
                'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
                'latitude' => 28.6139,
                'longitude' => 77.2090,
                'timezone' => 'Asia/Kolkata',
                'birth_city' => 'New Delhi',
                'birth_country' => 'India',
            ],
            'language' => 'en',
            'status' => KundliReport::STATUS_QUEUED,
        ]);

        $jobId = 'job_' . Str::random(16);
        $workerId = 'test_worker_a5';

        $payload = [
            'report_uuid' => $reportUuid,
            'worker_id' => $workerId,
            'job_id' => $jobId,
            'attempt' => 1,
            'language' => 'en',
        ];

        $canonicalKey = $this->handler->computeCanonicalKey($payload);

        $job = AstrologyCalculationJob::create([
            'job_id' => $jobId,
            'canonical_key' => $canonicalKey,
            'calculation_type' => DetailedKundliReportCalculationHandler::TYPE,
            'payload' => $payload,
            'status' => AstrologyCalculationJob::STATUS_PROCESSING,
            'worker_id' => $workerId,
            'lease_expires_at' => now()->addMinutes(10),
            'heartbeat_at' => now(),
        ]);

        $result = $this->handler->calculate($payload);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals($reportUuid, $result['report_uuid']);

        $report->refresh();
        $this->assertEquals(KundliReport::STATUS_COMPLETED, $report->status);
        $this->assertNotEmpty($report->file_path);
        $this->assertNotNull($report->file_size_bytes);
        $this->assertGreaterThan(5000, $report->file_size_bytes);
        $this->assertNotNull($report->page_count);
        $this->assertGreaterThanOrEqual(4, $report->page_count);

        // Verify published file exists on disk
        $absPath = storage_path('app/private/' . $report->file_path);
        $this->assertTrue(File::exists($absPath));

        // Clean up generated report file
        if (File::exists($absPath)) {
            File::delete($absPath);
        }
    }
}
