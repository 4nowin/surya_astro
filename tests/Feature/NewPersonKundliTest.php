<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanKundliReport;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewPersonKundliTest extends TestCase
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

    public function test_new_person_kundli_generation_creates_jajman_and_stores_pdf(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        $payload = [
            'name' => 'Ananya Iyer',
            'phone' => '+91 98765 43210',
            'email' => 'ananya@example.com',
            'gender' => 'female',
            'date_of_birth' => '1993-07-15',
            'birth_time' => '07:45:00',
            'birth_time_precision' => 'verified',
            'birth_city' => 'Bengaluru',
            'latitude' => 12.9716,
            'longitude' => 77.5946,
            'timezone' => 'Asia/Kolkata',
            'language' => 'en',
            'title' => 'Janam Kundli - Ananya Iyer',
        ];

        $res = $this->postJson('/api/astrologer/kundli/reports/new-person', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.language', 'en')
            ->assertJsonPath('data.report.title', 'Janam Kundli - Ananya Iyer')
            ->assertJsonPath('data.jajman.name', 'Ananya Iyer')
            ->assertJsonPath('data.jajman.has_possible_duplicates', false);

        $jajmanId = $res->json('data.jajman.id');
        $reportId = $res->json('data.report.id');

        // Verify Jajman exists in database and is linked to astrologer
        $this->assertDatabaseHas('jajmans', [
            'id' => $jajmanId,
            'name' => 'Ananya Iyer',
            'email' => 'ananya@example.com',
        ]);

        $this->assertDatabaseHas('professional_jajmans', [
            'admin_id' => $this->astrologer->id,
            'jajman_id' => $jajmanId,
            'has_possible_duplicates' => false,
        ]);

        // Verify primary birth detail created
        $this->assertDatabaseHas('jajman_birth_details', [
            'jajman_id' => $jajmanId,
            'birth_place' => 'Bengaluru',
            'is_primary' => true,
        ]);

        // Verify report stored in database and storage disk
        $this->assertDatabaseHas('jajman_kundli_reports', [
            'id' => $reportId,
            'admin_id' => $this->astrologer->id,
            'jajman_id' => $jajmanId,
        ]);

        $report = JajmanKundliReport::find($reportId);
        $this->assertNotNull($report);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_new_person_kundli_generation_matches_existing_jajman_by_phone(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        // Create an existing Jajman in this astrologer's CRM
        $existing = Jajman::create([
            'name' => 'Vikram Aditya',
            'phone' => '+91 98111 22233',
            'email' => null,
            'city' => 'Delhi',
        ]);
        $existing->professionals()->attach($this->astrologer->id, [
            'status' => 'active',
            'source' => 'manual',
            'has_possible_duplicates' => false,
        ]);

        // New person request with same phone (formatted slightly differently)
        $payload = [
            'name' => 'Vikram Aditya',
            'phone' => '09811122233', // normalized to +919811122233
            'email' => 'vikram@example.com',
            'date_of_birth' => '1988-11-20',
            'birth_time' => '14:20:00',
            'birth_city' => 'New Delhi',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'language' => 'hi',
        ];

        $res = $this->postJson('/api/astrologer/kundli/reports/new-person', $payload);

        $res->assertStatus(201);
        $resJajmanId = $res->json('data.jajman.id');

        // Should link to the EXACT same Jajman, not create a duplicate
        $this->assertEquals($existing->id, $resJajmanId);
        $this->assertDatabaseCount('jajmans', 1);

        // Report should be associated with this existing Jajman
        $reportId = $res->json('data.report.id');
        $this->assertDatabaseHas('jajman_kundli_reports', [
            'id' => $reportId,
            'admin_id' => $this->astrologer->id,
            'jajman_id' => $existing->id,
        ]);
    }

    public function test_new_person_kundli_generation_flags_ambiguous_matches_without_blind_merge(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        // Create 2 existing Jajmans with same phone in this astrologer's practice
        $jajmanA = Jajman::create([
            'name' => 'Ramesh Kumar Senior',
            'phone' => '+91 99887 76655',
            'city' => 'Varanasi',
        ]);
        $jajmanA->professionals()->attach($this->astrologer->id, [
            'status' => 'active',
            'source' => 'manual',
            'has_possible_duplicates' => false,
        ]);

        $jajmanB = Jajman::create([
            'name' => 'Ramesh K.',
            'phone' => '+91 99887 76655',
            'city' => 'Varanasi',
        ]);
        $jajmanB->professionals()->attach($this->astrologer->id, [
            'status' => 'active',
            'source' => 'manual',
            'has_possible_duplicates' => false,
        ]);

        $payload = [
            'name' => 'Ramesh Kumar Jr',
            'phone' => '+91 99887 76655',
            'date_of_birth' => '2000-01-01',
            'birth_time' => '10:00:00',
            'birth_city' => 'Varanasi',
            'latitude' => 25.3176,
            'longitude' => 82.9739,
            'language' => 'en',
        ];

        $res = $this->postJson('/api/astrologer/kundli/reports/new-person', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.jajman.has_possible_duplicates', true);

        $newJajmanId = $res->json('data.jajman.id');
        $duplicateCandidates = $res->json('data.jajman.possible_duplicate_ids');

        // Does NOT blindly merge into A or B; creates a distinct third record
        $this->assertNotEquals($jajmanA->id, $newJajmanId);
        $this->assertNotEquals($jajmanB->id, $newJajmanId);
        $this->assertDatabaseCount('jajmans', 3);

        // Contains both candidate IDs
        $this->assertContains($jajmanA->id, $duplicateCandidates);
        $this->assertContains($jajmanB->id, $duplicateCandidates);
    }

    public function test_jajman_removal_via_destroy_endpoint_preserves_kundli_reports(): void
    {
        $this->actingAs($this->astrologer, 'sanctum');

        // Create Jajman with generated report
        $jajman = Jajman::create([
            'name' => 'Meera Bai',
            'phone' => '+91 91234 56789',
            'city' => 'Mathura',
        ]);
        $jajman->professionals()->attach($this->astrologer->id, [
            'status' => 'active',
            'source' => 'manual',
            'has_possible_duplicates' => false,
        ]);
        JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'date_of_birth' => '1991-03-25',
            'birth_time' => '11:11:00',
            'birth_place' => 'Mathura',
            'latitude' => 27.4924,
            'longitude' => 77.6737,
            'is_primary' => true,
        ]);

        $genRes = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports", [
            'language' => 'en',
            'title' => 'Meera Chart 1',
        ]);
        $genRes->assertStatus(201);
        $reportId = $genRes->json('data.id');

        // Astrologer removes Jajman via DELETE /api/astrologer/jajmans/{id}
        $delRes = $this->deleteJson("/api/astrologer/jajmans/{$jajman->id}");
        $delRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Jajman removed from your workspace.');

        // Verify professional link is detached and Jajman soft deleted
        $this->assertDatabaseMissing('professional_jajmans', [
            'admin_id' => $this->astrologer->id,
            'jajman_id' => $jajman->id,
        ]);
        $this->assertSoftDeleted('jajmans', ['id' => $jajman->id]);

        // Verify astrologer can still list their historical reports
        $listRes = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports");
        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reportId);

        // Verify astrologer can still download the stored PDF
        $downRes = $this->get("/api/astrologer/jajmans/{$jajman->id}/kundli/reports/{$reportId}/download");
        $downRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Re-adding this person via new-person Kundli generation restores and re-links the Jajman
        $reAddPayload = [
            'name' => 'Meera Bai',
            'phone' => '+91 91234 56789',
            'date_of_birth' => '1991-03-25',
            'birth_time' => '11:11:00',
            'birth_city' => 'Mathura',
            'latitude' => 27.4924,
            'longitude' => 77.6737,
            'language' => 'en',
            'title' => 'Meera Chart 2',
        ];

        $reAddRes = $this->postJson('/api/astrologer/kundli/reports/new-person', $reAddPayload);
        $reAddRes->assertStatus(201);

        // Same Jajman ID is restored
        $this->assertEquals($jajman->id, $reAddRes->json('data.jajman.id'));
        $this->assertNotSoftDeleted('jajmans', ['id' => $jajman->id]);

        // Jajman now has 2 reports in history (prior report + new report)
        $listAfter = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli/reports");
        $listAfter->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
