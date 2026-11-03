<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SavedKundli;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\DashaService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\Pdf\KundliPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KundliDetailsAndSharingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected UserAstrologyProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CalculationWorkerLauncher::setLaunchHook(function () {
            return true;
        });

        $this->user = User::factory()->create([
            'email' => 'm6user@example.com',
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

    public function test_vimshottari_dasha_calculation_covers_120_years(): void
    {
        /** @var DashaService $dashaService */
        $dashaService = app(DashaService::class);

        // Test with known Moon longitude (e.g., 280° = Shravana Nakshatra, Moon Lord)
        $result = $dashaService->calculateVimshottariTimeline(
            moonLongitude: 280.5,
            birthDate: '1990-05-15 14:30:00',
            precision: 'verified',
            targetDate: '2026-11-03 12:00:00'
        );

        $this->assertEquals('available', $result['status']);
        $this->assertCount(9, $result['mahadashas']);

        $totalYears = array_sum(array_column($result['mahadashas'], 'years'));
        $this->assertEquals(120, $totalYears);

        // Assert active dasha is identified
        $this->assertNotNull($result['current_dasha']);
        $this->assertArrayHasKey('mahadasha', $result['current_dasha']);
        $this->assertArrayHasKey('antardasha', $result['current_dasha']);
        $this->assertArrayHasKey('remaining_formatted', $result['current_dasha']);
    }

    public function test_dasha_api_endpoint_returns_success_for_authenticated_user(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        $res = $this->getJson("/api/v1/kundli/{$this->profile->id}/dashas");

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'available')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'current_dasha',
                    'mahadashas',
                ],
            ]);
    }

    public function test_saving_and_retrieving_kundli_snapshot(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        // 1. Save snapshot
        $saveRes = $this->postJson('/api/v1/kundli/save', [
            'profile_id' => $this->profile->id,
            'title' => 'My Natal Chart 1990',
            'notes' => 'Saved for personal record',
        ]);

        $saveRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'My Natal Chart 1990');

        $savedId = $saveRes->json('data.id');

        // 2. List saved
        $listRes = $this->getJson('/api/v1/kundli/saved');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $savedId)
            ->assertJsonPath('data.0.is_outdated', false);

        // 3. Update profile to trigger outdated detection
        $this->profile->update([
            'date_of_birth' => '1990-05-16', // Changed DOB
        ]);

        $listRes2 = $this->getJson('/api/v1/kundli/saved');
        $listRes2->assertStatus(200)
            ->assertJsonPath('data.0.is_outdated', true);

        // 4. Delete saved
        $delRes = $this->deleteJson("/api/v1/kundli/saved/{$savedId}");
        $delRes->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseMissing('saved_kundlis', ['id' => $savedId]);
    }

    public function test_another_user_cannot_access_or_delete_saved_kundli(): void
    {
        $otherUser = User::factory()->create();

        $saved = SavedKundli::create([
            'user_id' => $this->user->id,
            'profile_id' => $this->profile->id,
            'title' => 'Private Chart',
            'person_name' => 'Aditya',
            'canonical_key' => $this->profile->computeCanonicalKey(),
            'date_of_birth' => $this->profile->date_of_birth,
            'birth_time' => $this->profile->birth_time,
            'birth_time_precision' => $this->profile->birth_time_precision,
            'birth_city' => $this->profile->birth_city,
            'latitude' => $this->profile->latitude,
            'longitude' => $this->profile->longitude,
            'timezone' => $this->profile->timezone,
            'chart_data' => ['test' => true],
        ]);

        Sanctum::actingAs($otherUser);

        // Other user attempts to view
        $viewRes = $this->getJson("/api/v1/kundli/saved/{$saved->id}");
        $viewRes->assertStatus(404);

        // Other user attempts to delete
        $delRes = $this->deleteJson("/api/v1/kundli/saved/{$saved->id}");
        $delRes->assertStatus(404);

        $this->assertDatabaseHas('saved_kundlis', ['id' => $saved->id]);
    }

    public function test_pdf_report_generates_valid_pdf_content_for_english_and_hindi(): void
    {
        $this->primeCalculation();
        Sanctum::actingAs($this->user);

        // 1. English PDF
        $resEn = $this->get("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=en");
        $resEn->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $contentEn = $resEn->getContent();
        $this->assertStringStartsWith('%PDF-', $contentEn);
        $this->assertGreaterThan(5000, strlen($contentEn));

        // 2. Hindi PDF
        $resHi = $this->get("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=hi");
        $resHi->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $contentHi = $resHi->getContent();
        $this->assertStringStartsWith('%PDF-', $contentHi);
        $this->assertGreaterThan(5000, strlen($contentHi));
    }

    public function test_uncomputed_profile_returns_pending_status_without_triggering_native_ephemeris(): void
    {
        Sanctum::actingAs($this->user);

        // Dashas endpoint without prior calculation
        $dashaRes = $this->getJson("/api/v1/kundli/{$this->profile->id}/dashas");
        $dashaRes->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // Save endpoint without prior calculation
        $saveRes = $this->postJson('/api/v1/kundli/save', [
            'profile_id' => $this->profile->id,
            'title' => 'Pending Chart',
        ]);
        $saveRes->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // PDF endpoint without prior calculation
        $pdfRes = $this->get("/api/v1/kundli/pdf?profile_id={$this->profile->id}&locale=en");
        $pdfRes->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');
    }
}
