<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CustomerAstrologerConsent;
use App\Models\DailyInsightRule;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanKundliReport;
use App\Models\ProfessionalDocumentSetting;
use App\Models\ProfessionalPublicProfile;
use App\Models\User;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use Database\Seeders\DailyInsightRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FreeReportTemplatesAndUnifiedHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $astrologer;
    protected Admin $otherAstrologer;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['astrology.driver' => 'local']);
        $this->app->instance(
            \App\Contracts\Astrology\CalculationDriverInterface::class,
            new \App\Services\Astrology\Calculation\Drivers\LocalCalculationDriver()
        );
        $this->app->instance(
            \App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler::class,
            new \App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler(
                null,
                new \App\Services\Astrology\Calculation\Drivers\LocalCalculationDriver()
            )
        );
        Storage::fake('local');
        CalculationWorkerLauncher::setLaunchHook(fn () => true);

        // Seed DailyInsightRules for personalized insights
        $this->seed(DailyInsightRulesSeeder::class);

        Role::firstOrCreate(['name' => 'Astrologer']);

        $this->astrologer = Admin::create([
            'name' => 'Pandit Devrat',
            'email' => 'devrat_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologer->assignRole('Astrologer');

        // Create branding profile
        ProfessionalPublicProfile::create([
            'admin_id' => $this->astrologer->id,
            'slug' => 'pandit-devrat',
            'display_name' => 'Pt. Devrat Shastri',
            'headline' => 'Jyotish Acharya & Vastu Expert',
            'city' => 'Varanasi',
            'public_phone' => '+919876543210',
            'public_email' => 'contact@devratjyotish.com',
            'website' => 'https://devratjyotish.com',
        ]);

        $this->otherAstrologer = Admin::create([
            'name' => 'Acharya Sharma',
            'email' => 'sharma_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('password123'),
        ]);
        $this->otherAstrologer->assignRole('Astrologer');
    }

    protected function tearDown(): void
    {
        CalculationWorkerLauncher::setLaunchHook(null);
        parent::tearDown();
    }

    protected function createOfflineJajman(Admin $astrologer, string $name = 'Rohan Verma'): Jajman
    {
        $jajman = Jajman::create([
            'name' => $name,
            'phone' => '+919876543210',
            'city' => 'Varanasi',
            'user_id' => null,
        ]);

        $jajman->professionals()->attach($astrologer->id, [
            'status' => 'active',
            'source' => 'manual',
        ]);

        JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'is_primary' => true,
            'date_of_birth' => '1992-08-20',
            'birth_time' => '10:15:00',
            'time_unknown' => false,
            'birth_place' => 'Varanasi',
            'latitude' => 25.3176,
            'longitude' => 82.9739,
            'timezone' => 'Asia/Kolkata',
        ]);

        return $jajman;
    }

    protected function createLinkedJajman(Admin $astrologer, User $customer, string $name = 'Sunil Mehta'): Jajman
    {
        $jajman = Jajman::create([
            'name' => $name,
            'phone' => $customer->phone ?? '+919876500000',
            'city' => 'New Delhi',
            'user_id' => $customer->id,
        ]);

        $jajman->professionals()->attach($astrologer->id, [
            'status' => 'active',
            'source' => 'consultation',
        ]);

        JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'is_primary' => true,
            'date_of_birth' => '1988-11-15',
            'birth_time' => '14:30:00',
            'time_unknown' => false,
            'birth_place' => 'New Delhi',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);

        return $jajman;
    }

    public function test_generate_and_store_daily_panchang_pdf_english(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/panchang/reports", [
            'date' => '2026-11-20',
            'language' => 'en',
            'city' => 'Varanasi',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Daily Panchang PDF generated and stored successfully.',
            ])
            ->assertJsonPath('data.report_category', 'panchang')
            ->assertJsonPath('data.tier', 'free')
            ->assertJsonPath('data.person_name', $jajman->name);

        $reportId = $response->json('data.id');
        $report = JajmanKundliReport::findOrFail($reportId);

        $this->assertEquals('panchang', $report->report_category);
        $this->assertEquals('free', $report->tier);
        $this->assertNotNull($report->branding_snapshot);
        $this->assertEquals('Pt. Devrat Shastri', $report->branding_snapshot['practitioner_name']);
        $this->assertEquals('Astro Setu', $report->branding_snapshot['platform_brand']);
        $this->assertStringContainsString('Powered by Astro Setu', $report->branding_snapshot['platform_footer']);

        Storage::disk('local')->assertExists($report->file_path);
        $this->assertGreaterThan(500, $report->file_size_bytes);
    }

    public function test_generate_and_store_daily_panchang_pdf_hindi(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/panchang-report", [
            'date' => '2026-11-20',
            'language' => 'hi',
            'city' => 'काशी',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.report_category', 'panchang')
            ->assertJsonPath('data.language', 'hi');

        $reportId = $response->json('data.id');
        $report = JajmanKundliReport::findOrFail($reportId);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_daily_insights_consent_enforcement_returns_403_when_unconsented(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::factory()->create([
            'name' => 'Linked Customer',
            'phone' => '+919811122233',
        ]);
        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        // No consent recorded
        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/daily-insights/reports", [
            'target_date' => '2026-11-20',
            'language' => 'en',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'status' => 'consent_required',
            ]);
    }

    public function test_daily_insights_succeeds_when_customer_grants_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::factory()->create([
            'name' => 'Consented Customer',
            'phone' => '+919811122244',
        ]);
        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        CustomerAstrologerConsent::create([
            'user_id' => $customer->id,
            'admin_id' => $this->astrologer->id,
            'status' => CustomerAstrologerConsent::STATUS_GRANTED,
            'granted_at' => now(),
        ]);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/daily-insights-report", [
            'target_date' => '2026-11-20',
            'language' => 'en',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Daily Personal Insights PDF generated and stored successfully.',
            ])
            ->assertJsonPath('data.report_category', 'daily_insights')
            ->assertJsonPath('data.tier', 'free');

        $reportId = $response->json('data.id');
        $report = JajmanKundliReport::findOrFail($reportId);
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertEquals('daily_insights', $report->report_category);
        $this->assertNotNull($report->meta_payload['overall_tone']);
    }

    public function test_daily_insights_manual_birth_details_override_allowed_without_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::factory()->create(['phone' => '+919811122255']);
        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        // Explicit manual birth overrides
        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/daily-insights/reports", [
            'target_date' => '2026-11-20',
            'name' => 'Custom Native',
            'date_of_birth' => '1995-03-25',
            'birth_time' => '09:00:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Delhi',
            'language' => 'en',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.report_category', 'daily_insights')
            ->assertJsonPath('data.person_name', 'Custom Native');

        $reportId = $response->json('data.id');
        $report = JajmanKundliReport::findOrFail($reportId);
        $this->assertTrue($report->birth_snapshot['is_manual_override']);
    }

    public function test_unified_reports_listing_and_filtering(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajmanA = $this->createOfflineJajman($this->astrologer, 'Aarav Gupta');
        $jajmanB = $this->createOfflineJajman($this->astrologer, 'Bhavna Sharma');

        // Generate 1 Kundli, 1 Panchang, 1 Daily Insights
        $this->postJson("/api/astrologer/jajmans/{$jajmanA->id}/kundli/reports", ['language' => 'en']);
        $this->postJson("/api/astrologer/jajmans/{$jajmanA->id}/panchang/reports", ['language' => 'en']);
        $this->postJson("/api/astrologer/jajmans/{$jajmanB->id}/daily-insights/reports", ['language' => 'en']);

        // 1. Fetch all unified reports
        $allRes = $this->getJson('/api/astrologer/reports');
        $allRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(3, $allRes->json('data'));

        // 2. Filter by category = 'panchang'
        $panchangRes = $this->getJson('/api/astrologer/reports?report_category=panchang');
        $panchangRes->assertStatus(200);
        $this->assertCount(1, $panchangRes->json('data'));
        $this->assertEquals('panchang', $panchangRes->json('data.0.report_category'));

        // 3. Filter by category = 'daily_insights'
        $insightsRes = $this->getJson('/api/astrologer/reports?report_category=daily_insights');
        $insightsRes->assertStatus(200);
        $this->assertCount(1, $insightsRes->json('data'));
        $this->assertEquals('daily_insights', $insightsRes->json('data.0.report_category'));

        // 4. Filter by jajman_id
        $jajmanARes = $this->getJson("/api/astrologer/reports?jajman_id={$jajmanA->id}");
        $jajmanARes->assertStatus(200);
        $this->assertCount(2, $jajmanARes->json('data'));

        // 5. Jajman-specific endpoint with category filtering
        $jajmanIndexPanchang = $this->getJson("/api/astrologer/jajmans/{$jajmanA->id}/kundli/reports?report_category=panchang");
        $jajmanIndexPanchang->assertStatus(200);
        $this->assertCount(1, $jajmanIndexPanchang->json('data'));
        $this->assertEquals('panchang', $jajmanIndexPanchang->json('data.0.report_category'));
    }

    public function test_fair_use_daily_limit_returns_429_when_exceeded(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        // Set fair use limit to 2 for testing
        config(['astrology.free_daily_report_limit' => 2]);

        $jajman = $this->createOfflineJajman($this->astrologer);

        // Report 1: Kundli (succeeds)
        $res1 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", ['language' => 'en']);
        $res1->assertStatus(201);

        // Report 2: Panchang (succeeds)
        $res2 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/panchang/reports", ['language' => 'en']);
        $res2->assertStatus(201);

        // Report 3: Daily Insights (exceeds limit of 2) -> 429
        $res3 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/daily-insights/reports", ['language' => 'en']);
        $res3->assertStatus(429)
            ->assertJson([
                'success' => false,
                'status' => 'rate_limit_exceeded',
            ])
            ->assertJsonPath('data.limit', 2)
            ->assertJsonPath('data.used', 2);
    }
}
