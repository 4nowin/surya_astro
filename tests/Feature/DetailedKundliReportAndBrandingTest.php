<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanKundliReport;
use App\Models\ProfessionalDocumentSetting;
use App\Models\ProfessionalPublicProfile;
use App\Models\User;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Drivers\LocalCalculationDriver;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\Reports\DetailedKundliReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DetailedKundliReportAndBrandingTest extends TestCase
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
            new LocalCalculationDriver()
        );
        $this->app->instance(
            KundliCalculationHandler::class,
            new KundliCalculationHandler(null, new LocalCalculationDriver())
        );
        Storage::fake('local');
        Storage::fake('public');
        CalculationWorkerLauncher::setLaunchHook(fn () => true);

        Role::firstOrCreate(['name' => 'Astrologer']);

        $this->astrologer = Admin::create([
            'name' => 'Pandit Devrat',
            'email' => 'devrat_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologer->assignRole('Astrologer');

        // Initialize branding profile
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

    public function test_generate_and_store_detailed_kundli_pdf_english(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer, 'Vikramaditya Roy');

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'tier' => 'detailed',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Detailed Kundli PDF generated and stored successfully.',
            ])
            ->assertJsonPath('data.report_category', 'kundli')
            ->assertJsonPath('data.tier', 'detailed')
            ->assertJsonPath('data.report_type', 'detailed')
            ->assertJsonPath('data.person_name', 'Vikramaditya Roy');

        $reportId = $response->json('data.id');
        $report = JajmanKundliReport::findOrFail($reportId);

        $this->assertEquals('detailed', $report->tier);
        $this->assertEquals('kundli', $report->report_category);
        $this->assertEquals('detailed', $report->report_type);
        $this->assertNotNull($report->branding_snapshot);
        $this->assertEquals('Pt. Devrat Shastri', $report->branding_snapshot['practitioner_name']);

        Storage::disk('local')->assertExists($report->file_path);
        // Detailed PDF has 15 sections and is substantially larger
        $this->assertGreaterThan(5000, $report->file_size_bytes);
    }

    public function test_generate_and_store_detailed_kundli_pdf_hindi(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer, 'अमित शर्मा');

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'hi',
            'report_type' => 'detailed',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.tier', 'detailed')
            ->assertJsonPath('data.language', 'hi');

        $reportId = $response->json('data.id');
        $report = JajmanKundliReport::findOrFail($reportId);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_generate_detailed_kundli_for_new_person(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $response = $this->postJson('/api/astrologer/kundli/reports/new-person', [
            'name' => 'Meera Nair',
            'phone' => '+919876599999',
            'email' => 'meera.nair@example.com',
            'gender' => 'female',
            'date_of_birth' => '1994-06-12',
            'birth_time' => '11:45:00',
            'latitude' => 9.9312,
            'longitude' => 76.2673,
            'birth_city' => 'Kochi',
            'language' => 'en',
            'tier' => 'detailed',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Detailed Kundli PDF generated and Jajman linked to CRM successfully.',
            ])
            ->assertJsonPath('data.report.tier', 'detailed')
            ->assertJsonPath('data.jajman.name', 'Meera Nair');

        $reportId = $response->json('data.report.id');
        $report = JajmanKundliReport::findOrFail($reportId);
        $this->assertEquals('detailed', $report->tier);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_detailed_kundli_consent_enforcement_returns_403_when_unconsented(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::factory()->create(['name' => 'Unconsented Consumer']);
        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'tier' => 'detailed',
            'language' => 'en',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'status' => 'consent_required',
            ]);
    }

    public function test_detailed_kundli_succeeds_when_customer_grants_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::factory()->create(['name' => 'Consenting Consumer']);
        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        CustomerAstrologerConsent::create([
            'user_id' => $customer->id,
            'admin_id' => $this->astrologer->id,
            'status' => CustomerAstrologerConsent::STATUS_GRANTED,
            'granted_at' => now(),
        ]);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'tier' => 'detailed',
            'language' => 'en',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tier', 'detailed');
    }

    public function test_detailed_kundli_manual_birth_override_allowed_without_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::factory()->create(['name' => 'Unconsented Overridden']);
        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'tier' => 'detailed',
            'language' => 'en',
            'name' => 'Manual Profile',
            'date_of_birth' => '1991-04-10',
            'birth_time' => '08:30:00',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'birth_city' => 'Mumbai',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tier', 'detailed')
            ->assertJsonPath('data.person_name', 'Manual Profile');
    }

    public function test_practitioner_branding_profile_update_and_retrieval(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        // 1. Get current branding
        $getRes = $this->getJson('/api/astrologer/branding');
        $getRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.practitioner_name', 'Pt. Devrat Shastri')
            ->assertJsonPath('data.city', 'Varanasi');

        // 2. Update branding with new details
        $updateRes = $this->postJson('/api/astrologer/branding', [
            'display_name' => 'Acharya Devrat Ji Maharaj',
            'headline' => 'Renowned KP & Vedic Astrologer',
            'city' => 'Haridwar',
            'phone' => '+919988776655',
            'email' => 'acharya.devrat@haridwar.test',
            'website' => 'https://acharyadevrat.com',
            'logo_url' => 'https://acharyadevrat.com/assets/logo.png',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.practitioner_name', 'Acharya Devrat Ji Maharaj')
            ->assertJsonPath('data.headline', 'Renowned KP & Vedic Astrologer')
            ->assertJsonPath('data.city', 'Haridwar')
            ->assertJsonPath('data.phone', '+919988776655')
            ->assertJsonPath('data.logo_url', 'https://acharyadevrat.com/assets/logo.png');

        // 3. Verify underlying models were updated
        $pubProfile = ProfessionalPublicProfile::where('admin_id', $this->astrologer->id)->first();
        $this->assertEquals('Acharya Devrat Ji Maharaj', $pubProfile->display_name);
        $this->assertEquals('Haridwar', $pubProfile->city);

        $docSetting = ProfessionalDocumentSetting::where('admin_id', $this->astrologer->id)->first();
        $this->assertEquals('Acharya Devrat Ji Maharaj', $docSetting->business_name);
    }

    public function test_practitioner_branding_snapshot_point_in_time_immutability(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer, 'Tarun Kapoor');

        // 1. Generate Report 1 with current branding ("Pt. Devrat Shastri")
        $res1 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'tier' => 'detailed',
        ]);
        $res1->assertStatus(201);
        $report1Id = $res1->json('data.id');
        $report1 = JajmanKundliReport::findOrFail($report1Id);
        $this->assertEquals('Pt. Devrat Shastri', $report1->branding_snapshot['practitioner_name']);
        $originalFilePath = $report1->file_path;
        $originalFileBytes = Storage::disk('local')->get($originalFilePath);

        // 2. Astrologer subsequently updates their branding
        $this->postJson('/api/astrologer/branding', [
            'display_name' => 'Maharishi Devrat New Brand',
            'city' => 'Ayodhya',
        ])->assertStatus(200);

        // 3. Verify Report 1 retains the frozen point-in-time snapshot and unchanged file
        $report1Fresh = JajmanKundliReport::findOrFail($report1Id);
        $this->assertEquals('Pt. Devrat Shastri', $report1Fresh->branding_snapshot['practitioner_name']);
        $this->assertEquals('Varanasi', $report1Fresh->branding_snapshot['city']);
        $this->assertEquals($originalFileBytes, Storage::disk('local')->get($originalFilePath));

        // 4. Generate Report 2 after branding change: gets updated branding
        $res2 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'tier' => 'detailed',
        ]);
        $res2->assertStatus(201);
        $report2Id = $res2->json('data.id');
        $report2 = JajmanKundliReport::findOrFail($report2Id);
        $this->assertEquals('Maharishi Devrat New Brand', $report2->branding_snapshot['practitioner_name']);
        $this->assertEquals('Ayodhya', $report2->branding_snapshot['city']);
    }
}
