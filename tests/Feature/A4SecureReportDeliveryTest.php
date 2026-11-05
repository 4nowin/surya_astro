<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KundliReport;
use App\Models\ReportPurchase;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Reports\ReportPublicationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class A4SecureReportDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected ReportPublicationService $publicationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicationService = app(ReportPublicationService::class);
        CalculationWorkerLauncher::setLaunchHook(null);
    }

    protected function tearDown(): void
    {
        CalculationWorkerLauncher::setLaunchHook(null);
        parent::tearDown();
    }

    protected function createReportForUser(User $user, string $status = 'completed'): KundliReport
    {
        $debit = ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'debit_' . Str::random(12),
            'transaction_type' => 'debit',
            'amount' => 149.00,
            'currency' => 'INR',
            'status' => 'completed',
        ]);

        $reportUuid = (string) Str::uuid();
        $paths = $this->publicationService->getFinalStoragePath($reportUuid);

        if ($status === 'completed') {
            File::ensureDirectoryExists(dirname($paths['absolute']), 0700);
            File::put($paths['absolute'], "%PDF-1.4 Test Report Content for {$user->name}");
        }

        return KundliReport::create([
            'uuid' => $reportUuid,
            'user_id' => $user->id,
            'report_purchase_id' => $debit->id,
            'birth_payload' => ['name' => $user->name],
            'status' => $status,
            'language' => 'en',
            'file_path' => $status === 'completed' ? $paths['relative'] : null,
            'file_hash' => $status === 'completed' ? hash('sha256', "test") : null,
            'file_size_bytes' => $status === 'completed' ? 1024 : null,
            'download_count' => 0,
        ]);
    }

    /**
     * Test authenticated owner can access status and generate download URL.
     */
    public function test_authenticated_owner_can_access_status_and_download_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $report = $this->createReportForUser($user, 'completed');

        // 1. Status endpoint
        $statusRes = $this->getJson("/api/v1/kundli/reports/{$report->uuid}/status");
        $statusRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report_uuid', $report->uuid)
            ->assertJsonPath('data.status', 'completed');

        // 2. Download URL endpoint
        $urlRes = $this->getJson("/api/v1/kundli/reports/{$report->uuid}/download-url");
        $urlRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report_uuid', $report->uuid)
            ->assertJsonPath('data.ttl_seconds', 1200);

        $signedUrl = $urlRes->json('data.download_url');
        $this->assertNotEmpty($signedUrl);
        $this->assertStringContainsString('/api/v1/kundli/reports/download/', $signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
    }

    /**
     * Test unauthenticated requests are rejected with 401.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $user = User::factory()->create();
        $report = $this->createReportForUser($user, 'completed');

        // Status without auth
        $this->getJson("/api/v1/kundli/reports/{$report->uuid}/status")
            ->assertStatus(401);

        // Download URL without auth
        $this->getJson("/api/v1/kundli/reports/{$report->uuid}/download-url")
            ->assertStatus(401);

        // Purchase without auth
        $this->postJson("/api/v1/kundli/reports/purchase", [
            'idempotency_key' => 'unauth_key',
        ])->assertStatus(401);
    }

    /**
     * Test user cannot access another user's report (403 Forbidden).
     */
    public function test_user_cannot_access_another_users_report(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $report = $this->createReportForUser($owner, 'completed');

        // Act as otherUser
        Sanctum::actingAs($otherUser);

        // Attempt to access status
        $this->getJson("/api/v1/kundli/reports/{$report->uuid}/status")
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized access to report.');

        // Attempt to obtain download URL
        $this->getJson("/api/v1/kundli/reports/{$report->uuid}/download-url")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Test valid signed URL downloads PDF attachment and increments download_count.
     */
    public function test_valid_signed_url_downloads_pdf(): void
    {
        $user = User::factory()->create(['name' => 'Kavita Das']);
        $report = $this->createReportForUser($user, 'completed');

        $signedUrl = URL::temporarySignedRoute(
            'kundli.reports.download',
            now()->addMinutes(20),
            ['reportUuid' => $report->uuid]
        );

        $this->assertSame(0, $report->download_count);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('AstroSetu_Kundli_Kavita_Das.pdf', (string) $response->headers->get('Content-Disposition'));

        // Assert download count incremented
        $this->assertSame(1, $report->fresh()->download_count);
    }

    /**
     * Test invalid or tampered signature is rejected (403).
     */
    public function test_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $report = $this->createReportForUser($user, 'completed');

        $tamperedUrl = "/api/v1/kundli/reports/download/{$report->uuid}?expires=" . now()->addMinutes(20)->getTimestamp() . "&signature=invalid_hash_signature";

        $response = $this->getJson($tamperedUrl);
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired report download link.');
    }

    /**
     * Test expired signed URL is rejected (403).
     */
    public function test_expired_signed_url_is_rejected(): void
    {
        $user = User::factory()->create();
        $report = $this->createReportForUser($user, 'completed');

        // Signed URL that expired 5 minutes ago
        $expiredUrl = URL::temporarySignedRoute(
            'kundli.reports.download',
            now()->subMinutes(5),
            ['reportUuid' => $report->uuid]
        );

        $response = $this->getJson($expiredUrl);
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired report download link.');
    }

    /**
     * Test missing file is handled safely (404).
     */
    public function test_missing_report_file_handled_safely(): void
    {
        $user = User::factory()->create();
        $report = $this->createReportForUser($user, 'completed');

        // Delete the physical file from storage
        $fullPath = storage_path("app/private/{$report->file_path}");
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $signedUrl = URL::temporarySignedRoute(
            'kundli.reports.download',
            now()->addMinutes(20),
            ['reportUuid' => $report->uuid]
        );

        $response = $this->getJson($signedUrl);
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Report PDF is missing from storage or retention period has expired.');
    }

    /**
     * Test purchase API endpoint with detached worker launcher integration.
     */
    public function test_purchase_api_endpoint_triggers_detached_worker(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200.00]);
        Sanctum::actingAs($user);

        // Create default profile for user
        UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Meera Nair',
            'gender' => 'female',
            'date_of_birth' => '1992-04-10',
            'birth_time' => '08:15:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_city' => 'Bengaluru',
            'birth_country' => 'India',
            'latitude' => 12.9716,
            'longitude' => 77.5946,
            'timezone' => 'Asia/Kolkata',
            'is_default' => true,
        ]);

        $workerHookCalled = false;
        CalculationWorkerLauncher::setLaunchHook(function ($bin, $artisan) use (&$workerHookCalled) {
            $workerHookCalled = true;
            return true;
        });

        $response = $this->postJson('/api/v1/kundli/reports/purchase', [
            'idempotency_key' => 'api_purchase_test_' . Str::random(10),
            'language' => 'hi',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued');

        $this->assertEquals(149.00, (float) $response->json('data.amount'));
        $this->assertEquals(51.00, (float) $response->json('data.wallet_balance'));

        $this->assertTrue($workerHookCalled, "Detached worker launcher must be triggered upon report purchase");

        $reportUuid = $response->json('data.report_uuid');
        $this->assertNotEmpty($reportUuid);

        $this->assertDatabaseHas('kundli_reports', [
            'uuid' => $reportUuid,
            'language' => 'hi',
            'status' => 'queued',
        ]);
    }

    /**
     * Test tampering with the report identifier in a signed URL invalidates access (403).
     * Proves that changing the report UUID in a signed link causes signature validation to fail.
     */
    public function test_tampering_with_report_identifier_in_signed_url_is_rejected(): void
    {
        $user1 = User::factory()->create(['name' => 'Report One User']);
        $user2 = User::factory()->create(['name' => 'Report Two User']);

        $report1 = $this->createReportForUser($user1, 'completed');
        $report2 = $this->createReportForUser($user2, 'completed');

        // Legitimate signed URL for report1
        $signedUrl1 = URL::temporarySignedRoute(
            'kundli.reports.download',
            now()->addMinutes(20),
            ['reportUuid' => $report1->uuid]
        );

        // Attacker tampers with URL by replacing report1's UUID with report2's UUID
        $tamperedUrl = str_replace($report1->uuid, $report2->uuid, $signedUrl1);

        $response = $this->getJson($tamperedUrl);
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired report download link.');
    }
}
