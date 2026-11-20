<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanKundliReport;
use App\Models\User;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Jajman\JajmanMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JajmanKundliReportTest extends TestCase
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

        Role::firstOrCreate(['name' => 'Astrologer']);

        $this->astrologer = Admin::create([
            'name' => 'Pandit Devrat',
            'email' => 'devrat_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologer->assignRole('Astrologer');

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
            'phone' => '+919123456789',
            'city' => 'Delhi',
            'user_id' => $customer->id,
        ]);

        $jajman->professionals()->attach($astrologer->id, [
            'status' => 'active',
            'source' => 'chat',
        ]);

        JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'is_primary' => true,
            'date_of_birth' => '1988-11-05',
            'birth_time' => '18:45:00',
            'time_unknown' => false,
            'birth_place' => 'New Delhi',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);

        return $jajman;
    }

    public function test_generate_and_store_basic_kundli_pdf_for_offline_jajman_english(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'report_type' => 'basic',
            'title' => 'Rohan Janam Kundli',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report_type', 'basic')
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.person_name', 'Rohan Verma')
            ->assertJsonPath('data.title', 'Rohan Janam Kundli');

        $reportId = $response->json('data.id');
        $filePath = $response->json('data.file_path');

        $this->assertDatabaseHas('jajman_kundli_reports', [
            'id' => $reportId,
            'admin_id' => $this->astrologer->id,
            'jajman_id' => $jajman->id,
            'report_type' => 'basic',
            'language' => 'en',
        ]);

        // Verify PDF was written to fake local storage and starts with %PDF-
        Storage::disk('local')->assertExists($filePath);
        $pdfContent = Storage::disk('local')->get($filePath);
        $this->assertStringStartsWith('%PDF-', $pdfContent);
    }

    public function test_generate_and_store_basic_kundli_pdf_hindi(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer, 'अमित शर्मा');

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'hi',
            'report_type' => 'basic',
        ]);

        // In environments with headless Chromium, returns 201; in headless-less environments, returns 503
        if ($response->status() === 201) {
            $response->assertJsonPath('success', true)
                ->assertJsonPath('data.language', 'hi');

            $filePath = $response->json('data.file_path');
            Storage::disk('local')->assertExists($filePath);
            $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($filePath));
        } else {
            $response->assertStatus(503)
                ->assertJsonPath('error_code', 'KUNDLI_PDF_TEMPORARILY_UNAVAILABLE');
        }
    }

    public function test_multiple_kundlis_generated_for_same_jajman_are_preserved_without_overwriting(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $jajman = $this->createOfflineJajman($this->astrologer);

        // Generate Report 1
        $res1 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'title' => 'Initial Consultation Kundli',
        ]);
        $res1->assertStatus(201);
        $id1 = $res1->json('data.id');
        $path1 = $res1->json('data.file_path');

        // Generate Report 2
        $res2 = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'title' => 'Follow-up Consultation Kundli',
        ]);
        $res2->assertStatus(201);
        $id2 = $res2->json('data.id');
        $path2 = $res2->json('data.file_path');

        $this->assertNotEquals($id1, $id2);
        $this->assertNotEquals($path1, $path2);

        // Verify both exist in database
        $this->assertDatabaseHas('jajman_kundli_reports', ['id' => $id1]);
        $this->assertDatabaseHas('jajman_kundli_reports', ['id' => $id2]);
        $this->assertEquals(2, JajmanKundliReport::where('jajman_id', $jajman->id)->count());

        // Verify both files exist
        Storage::disk('local')->assertExists($path1);
        Storage::disk('local')->assertExists($path2);

        // Verify listing returns both, newest first
        $listRes = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports");
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $id2)
            ->assertJsonPath('data.1.id', $id1);
    }

    public function test_consent_enforcement_returns_403_when_linked_customer_has_not_granted_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::create([
            'name' => 'Sunil Customer',
            'email' => 'sunil_' . uniqid() . '@astrosetu.test',
            'password' => bcrypt('password123'),
        ]);

        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        // Without consent, reading saved birth details must fail with 403 consent_required
        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'consent_required');

        $this->assertEquals(0, JajmanKundliReport::where('jajman_id', $jajman->id)->count());
    }

    public function test_consent_enforcement_succeeds_when_linked_customer_grants_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::create([
            'name' => 'Consenting Customer',
            'email' => 'consent_' . uniqid() . '@astrosetu.test',
            'password' => bcrypt('password123'),
        ]);

        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        CustomerAstrologerConsent::create([
            'user_id' => $customer->id,
            'admin_id' => $this->astrologer->id,
            'status' => CustomerAstrologerConsent::STATUS_GRANTED,
            'consented_at' => now(),
        ]);

        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertEquals(1, JajmanKundliReport::where('jajman_id', $jajman->id)->count());
    }

    public function test_manual_birth_details_override_allowed_without_consent(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $customer = User::create([
            'name' => 'No Consent Customer',
            'email' => 'noconsent_' . uniqid() . '@astrosetu.test',
            'password' => bcrypt('password123'),
        ]);

        $jajman = $this->createLinkedJajman($this->astrologer, $customer);

        // Practitioner enters their own explicit birth parameters (does not read private consumer profile)
        $response = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'name' => 'Explicit Override',
            'date_of_birth' => '1995-03-12',
            'birth_time' => '07:30:00',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Mumbai',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.birth_snapshot.is_manual_override', true)
            ->assertJsonPath('data.birth_snapshot.birth_city', 'Mumbai');

        $this->assertEquals(1, JajmanKundliReport::where('jajman_id', $jajman->id)->count());
    }

    public function test_practitioner_tenant_isolation_for_listing_and_downloading(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');
        $jajman = $this->createOfflineJajman($this->astrologer);

        $res = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'title' => 'Confidential Chart',
        ]);
        $res->assertStatus(201);
        $reportId = $res->json('data.id');

        // Astrologer A can view and download
        $viewRes = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports/{$reportId}");
        $viewRes->assertStatus(200)->assertJsonPath('data.id', $reportId);

        $downRes = $this->get("/api/astrologer/jajmans/{$jajman->id}/kundli/reports/{$reportId}/download");
        $downRes->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

        // Astrologer B attempts to list, view, or download Astrologer A's report
        $this->actingAs($this->otherAstrologer, 'sanctum');

        $otherList = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports");
        $otherList->assertStatus(200)->assertJsonCount(0, 'data');

        $otherView = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports/{$reportId}");
        $otherView->assertStatus(404);

        $otherDown = $this->get("/api/astrologer/jajmans/{$jajman->id}/kundli/reports/{$reportId}/download");
        $otherDown->assertStatus(404);
    }

    public function test_download_access_preserved_when_jajman_is_unlinked_or_soft_deleted(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');
        $jajman = $this->createOfflineJajman($this->astrologer);

        $res = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'title' => 'Historical Report',
        ]);
        $res->assertStatus(201);
        $reportId = $res->json('data.id');

        // Unlink and soft-delete Jajman (simulating CRM removal)
        $jajman->professionals()->detach($this->astrologer->id);
        $jajman->delete();
        $this->assertSoftDeleted('jajmans', ['id' => $jajman->id]);

        // Practitioner can still list and download the historical report they generated
        $listRes = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports");
        $listRes->assertStatus(200)->assertJsonCount(1, 'data');

        $downRes = $this->get("/api/astrologer/jajmans/{$jajman->id}/kundli/reports/{$reportId}/download");
        $downRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_merge_consolidates_kundli_reports_from_secondary_to_primary_jajman(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $primary = $this->createOfflineJajman($this->astrologer, 'Primary Jajman');
        $secondary = $this->createOfflineJajman($this->astrologer, 'Secondary Jajman');

        // Generate report for secondary Jajman
        $res = $this->postJson("/api/astrologer/jajmans/{$secondary->id}/kundli/reports", [
            'language' => 'en',
            'title' => 'Secondary Jajman Chart',
        ]);
        $res->assertStatus(201);
        $reportId = $res->json('data.id');

        // Perform merge
        /** @var JajmanMergeService $mergeService */
        $mergeService = app(JajmanMergeService::class);
        $mergeResult = $mergeService->merge($this->astrologer->id, $primary->id, $secondary->id);

        $this->assertArrayHasKey('kundli_reports_moved', $mergeResult['consolidated_counts']);
        $this->assertEquals(1, $mergeResult['consolidated_counts']['kundli_reports_moved']);

        // Assert report is now linked to primary Jajman
        $report = JajmanKundliReport::find($reportId);
        $this->assertEquals($primary->id, $report->jajman_id);

        // Primary Jajman list now includes the consolidated report
        $listRes = $this->getJson("/api/astrologer/jajmans/{$primary->id}/kundli/reports");
        $listRes->assertStatus(200)
            ->assertJsonPath('data.0.id', $reportId);
    }
}
