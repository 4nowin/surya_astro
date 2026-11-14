<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AstrologyCalculationJob;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersistentKundliCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CalculationWorkerLauncher::setLaunchHook(null);
    }

    protected function tearDown(): void
    {
        CalculationWorkerLauncher::setLaunchHook(null);
        parent::tearDown();
    }

    protected function createAstrologer(): Admin
    {
        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
        $admin = Admin::create([
            'name' => 'Test Astrologer',
            'email' => 'astrologer_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('secret123'),
        ]);
        $admin->assignRole('Astrologer');
        return $admin;
    }

    protected function createConsumerUser(): User
    {
        return User::create([
            'name' => 'Test Consumer',
            'email' => 'consumer_' . uniqid() . '@astrosetu.test',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_first_kundli_request_creates_job_and_launches_targeted_worker(): void
    {
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function ($bin, $artisan) use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $payload = [
            'date_of_birth' => '1990-05-15',
            'birth_time' => '14:30:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Rahul Sharma',
            'birth_city' => 'New Delhi',
        ];

        $res = $this->postJson('/api/astrologer/astrology/kundli/calculate', $payload);

        $res->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $jobId = $res->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched, 'Targeted worker must be launched on cache miss');

        $this->assertDatabaseHas('astrology_calculation_jobs', [
            'job_id' => $jobId,
            'calculation_type' => 'kundli',
            'status' => 'pending',
        ]);
    }

    public function test_cli_worker_with_job_flag_computes_complete_kundli_and_persists_to_mysql(): void
    {
        $payload = [
            'date_of_birth' => '1990-05-15',
            'birth_time' => '14:30:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Rahul Sharma',
            'birth_city' => 'New Delhi',
        ];

        $handler = app(KundliCalculationHandler::class);
        $canonicalKey = $handler->computeCanonicalKey($payload);

        $job = AstrologyCalculationJob::create([
            'job_id' => 'calc_kundli_targeted_test_1',
            'calculation_type' => 'kundli',
            'canonical_key' => $canonicalKey,
            'payload' => KundliCalculationHandler::normalizePayload($payload),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // Run worker targeted at this specific job
        $this->artisan('astro:calculate-worker', ['--job' => $job->job_id])
            ->assertSuccessful();

        $job->refresh();
        $this->assertTrue($job->isCompleted());
        $this->assertNotNull($job->result);
        $this->assertIsArray($job->result);

        $result = $job->result;

        // Verify Core Kundli
        $this->assertArrayHasKey('planets', $result);
        $this->assertArrayHasKey('sun', $result['planets']);
        $this->assertArrayHasKey('moon', $result['planets']);
        $this->assertArrayHasKey('ascendant', $result);
        $this->assertArrayHasKey('houses', $result);

        // Verify Vimshottari Dasha
        $this->assertArrayHasKey('dasha', $result);

        // Verify Manglik Dosha
        $this->assertArrayHasKey('manglik_dosha', $result);

        // Verify Yogas & Aspects
        $this->assertArrayHasKey('yogas', $result);
        $this->assertArrayHasKey('aspects', $result);

        // Verify D-9 Navamsha chart & houses
        $this->assertArrayHasKey('navamsha', $result);
        $this->assertArrayHasKey('houses', $result['navamsha']);
        $this->assertIsArray($result['navamsha']['houses']);
        $this->assertCount(12, $result['navamsha']['houses']);

        // Verify Saturn transits
        $this->assertArrayHasKey('saturn_transit', $result);
    }

    public function test_subsequent_request_returns_saved_result_from_mysql_even_after_cache_flush(): void
    {
        $payload = [
            'date_of_birth' => '1988-10-25',
            'birth_time' => '09:15:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Amit Verma',
            'birth_city' => 'Delhi',
        ];

        $handler = app(KundliCalculationHandler::class);
        $canonicalKey = $handler->computeCanonicalKey($payload);

        // Seed directly into MySQL calculation job table
        AstrologyCalculationJob::create([
            'job_id' => 'calc_kundli_seeded_123',
            'calculation_type' => 'kundli',
            'canonical_key' => $canonicalKey,
            'payload' => KundliCalculationHandler::normalizePayload($payload),
            'status' => AstrologyCalculationJob::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => [
                'metadata' => ['dob' => '1988-10-25'],
                'planets' => [
                    'sun' => ['name' => 'Sun', 'longitude' => 190.5],
                    'moon' => ['name' => 'Moon', 'longitude' => 45.2, 'rashi' => ['id' => 2, 'name' => 'Taurus']],
                ],
                'ascendant' => ['id' => 9, 'name' => 'Sagittarius'],
                'houses' => [],
                'dasha' => ['current_mahadasha' => 'Jupiter'],
            ],
        ]);

        // Completely flush fast cache (simulating optimize:clear)
        Cache::flush();

        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $res = $this->postJson('/api/astrologer/astrology/kundli/calculate', $payload);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.planets.sun.name', 'Sun')
            ->assertJsonPath('data.dasha.current_mahadasha', 'Jupiter');

        $this->assertFalse($workerLaunched, 'Worker should not launch when persistent MySQL result exists');
    }

    public function test_coordinate_floating_point_precision_tolerance_reuses_cached_calculation(): void
    {
        $payload = [
            'date_of_birth' => '1992-03-10',
            'birth_time' => '07:30:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];

        $handler = app(KundliCalculationHandler::class);
        $canonicalKey = $handler->computeCanonicalKey($payload);

        AstrologyCalculationJob::create([
            'job_id' => 'calc_kundli_coord_test',
            'calculation_type' => 'kundli',
            'canonical_key' => $canonicalKey,
            'payload' => KundliCalculationHandler::normalizePayload($payload),
            'status' => AstrologyCalculationJob::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => [
                'metadata' => ['dob' => '1992-03-10'],
                'planets' => ['sun' => ['name' => 'Sun']],
                'ascendant' => ['id' => 1, 'name' => 'Aries'],
            ],
        ]);

        Cache::flush();

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        // Request with minor 5th+ decimal place difference (~1 meter)
        $reqPayload = $payload;
        $reqPayload['latitude'] = 28.61390001;
        $reqPayload['longitude'] = 77.20900002;

        $res = $this->postJson('/api/astrologer/astrology/kundli/calculate', $reqPayload);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.planets.sun.name', 'Sun');
    }

    public function test_pandit_setu_and_astro_setu_share_cached_kundli_without_worker(): void
    {
        // 1. Pandit Setu calculates first
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $birthParams = [
            'date_of_birth' => '1995-12-05',
            'birth_time' => '11:45:00',
            'birth_time_precision' => 'verified',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Deepak',
            'birth_city' => 'Mumbai',
        ];

        $resPandit = $this->postJson('/api/astrologer/astrology/kundli/calculate', $birthParams);
        $resPandit->assertStatus(202);
        $jobId = $resPandit->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched);

        // 2. Worker executes
        CalculationWorkerLauncher::setLaunchHook(null);
        $this->artisan('astro:calculate-worker', ['--job' => $jobId])->assertSuccessful();

        // 3. Astro Setu consumer user creates profile with identical birth parameters
        $consumer = $this->createConsumerUser();
        $profile = UserAstrologyProfile::create([
            'user_id' => $consumer->id,
            'name' => 'Deepak',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1995-12-05',
            'birth_time_precision' => 'verified',
            'birth_time' => '11:45:00',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Mumbai',
            'birth_country' => 'India',
        ]);

        $workerLaunchedAgain = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunchedAgain) {
            $workerLaunchedAgain = true;
            return true;
        });

        $this->actingAs($consumer, 'sanctum');
        $resAstro = $this->postJson('/api/v1/kundli/generate', [
            'profile_id' => $profile->id,
        ]);

        // 4. Astro Setu receives HTTP 200 directly from shared cache WITHOUT spawning worker
        $resAstro->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertHeader('X-Astrology-Cached', 'HIT');
        $this->assertNotNull($resAstro->json('data.planets'));
        $this->assertFalse($workerLaunchedAgain, 'Astro Setu must reuse Pandit Setu calculated Kundli without spawning worker');
    }

    public function test_jajman_kundli_and_adhoc_kundli_share_cached_calculation(): void
    {
        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $jajman = Jajman::create([
            'name' => 'Suresh Kumar',
            'phone' => '9876543210',
            'date_of_birth' => '1985-08-14',
        ]);
        $jajman->professionals()->attach($astrologer->id, ['status' => 'active']);

        JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'birth_place' => 'New Delhi',
            'date_of_birth' => '1985-08-14',
            'birth_time' => '16:00:00',
            'time_unknown' => false,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'is_primary' => true,
        ]);

        // 1. First request for Jajman returns 202 and launches worker
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $res1 = $this->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli");
        $res1->assertStatus(202);
        $jobId = $res1->json('data.job_id');
        $this->assertTrue($workerLaunched);

        // 2. Compute via worker
        CalculationWorkerLauncher::setLaunchHook(null);
        $this->artisan('astro:calculate-worker', ['--job' => $jobId])->assertSuccessful();

        // 3. Ad-hoc Kundli calculation with identical birth details
        $workerLaunchedAgain = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunchedAgain) {
            $workerLaunchedAgain = true;
            return true;
        });

        $res2 = $this->postJson('/api/astrologer/astrology/kundli/calculate', [
            'date_of_birth' => '1985-08-14',
            'birth_time' => '16:00:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);

        // 4. Receives 200 HIT without worker launch
        $res2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertHeader('X-Astrology-Cached', 'HIT');
        $this->assertFalse($workerLaunchedAgain, 'Ad-hoc calculation must reuse Jajman Kundli result without worker');
    }

    public function test_different_birth_details_create_separate_calculations(): void
    {
        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $res1 = $this->postJson('/api/astrologer/astrology/kundli/calculate', [
            'date_of_birth' => '1990-01-01',
            'birth_time' => '10:00:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);

        $res2 = $this->postJson('/api/astrologer/astrology/kundli/calculate', [
            'date_of_birth' => '1990-01-02',
            'birth_time' => '10:00:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);

        $res1->assertStatus(202);
        $res2->assertStatus(202);

        $job1Id = $res1->json('data.job_id');
        $job2Id = $res2->json('data.job_id');

        $this->assertNotEquals($job1Id, $job2Id, 'Different dates of birth must create separate jobs');

        $job1 = AstrologyCalculationJob::where('job_id', $job1Id)->first();
        $job2 = AstrologyCalculationJob::where('job_id', $job2Id)->first();
        $this->assertNotEquals($job1->canonical_key, $job2->canonical_key);
    }

    public function test_identical_simultaneous_kundli_requests_deduplicate_to_single_job(): void
    {
        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $payload = [
            'date_of_birth' => '1993-07-20',
            'birth_time' => '18:15:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];

        $resA = $this->postJson('/api/astrologer/astrology/kundli/calculate', $payload);
        $resB = $this->postJson('/api/astrologer/astrology/kundli/calculate', $payload);

        $resA->assertStatus(202);
        $resB->assertStatus(202);

        $this->assertEquals($resA->json('data.job_id'), $resB->json('data.job_id'), 'Simultaneous requests must deduplicate to single job');
    }

    public function test_failed_kundli_calculation_is_recoverable_without_manual_ssh(): void
    {
        $payload = [
            'date_of_birth' => '1994-09-09',
            'birth_time' => '12:00:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];

        $handler = app(KundliCalculationHandler::class);
        $canonicalKey = $handler->computeCanonicalKey($payload);

        $failedJob = AstrologyCalculationJob::create([
            'job_id' => 'calc_failed_kundli_test',
            'calculation_type' => 'kundli',
            'canonical_key' => $canonicalKey,
            'payload' => KundliCalculationHandler::normalizePayload($payload),
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'error_message' => 'Simulated transient worker failure',
        ]);

        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $res = $this->postJson('/api/astrologer/astrology/kundli/calculate', $payload);
        $res->assertStatus(202);
        $this->assertTrue($workerLaunched, 'Failed job must automatically launch fresh worker on retry');

        $failedJob->refresh();
        $this->assertEquals('pending', $failedJob->status);
        $this->assertNull($failedJob->error_message);
    }

    public function test_web_kundli_requests_never_initialize_ffi(): void
    {
        $ffiResolved = false;
        $this->app->resolving(\JmeEph\FFI\JmeEphFFI::class, function () use (&$ffiResolved) {
            $ffiResolved = true;
            throw new \RuntimeException('Native FFI engine was resolved during web request!');
        });

        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        // 1. Pandit Setu ad-hoc calculation web request
        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $res1 = $this->postJson('/api/astrologer/astrology/kundli/calculate', [
            'date_of_birth' => '1991-04-12',
            'birth_time' => '08:00:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);
        $res1->assertStatus(202);

        // 2. Status inspection web request
        $jobId = $res1->json('data.job_id');
        $resStatus = $this->getJson("/api/v1/calculations/{$jobId}");
        $resStatus->assertStatus(200);

        // 3. Astro Setu consumer user request
        $consumer = $this->createConsumerUser();
        $profile = UserAstrologyProfile::create([
            'user_id' => $consumer->id,
            'name' => 'Pooja',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1991-04-12',
            'birth_time_precision' => 'verified',
            'birth_time' => '08:00:00',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'birth_city' => 'Delhi',
            'birth_country' => 'India',
        ]);

        $this->actingAs($consumer, 'sanctum');
        $res2 = $this->postJson('/api/v1/kundli/generate', [
            'profile_id' => $profile->id,
        ]);
        $res2->assertStatus(202);

        $this->assertFalse($ffiResolved, 'Native FFI must NEVER be resolved during web requests');
    }

    public function test_recalculate_jajman_kundli_forces_fresh_calculation(): void
    {
        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $jajman = Jajman::create([
            'name' => 'Vikram Singh',
            'phone' => '9123456789',
            'date_of_birth' => '1987-11-11',
        ]);
        $jajman->professionals()->attach($astrologer->id, ['status' => 'active']);

        JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'birth_place' => 'New Delhi',
            'date_of_birth' => '1987-11-11',
            'birth_time' => '10:30:00',
            'time_unknown' => false,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'is_primary' => true,
        ]);

        // Seed existing completed calculation
        $handler = app(KundliCalculationHandler::class);
        $payload = [
            'date_of_birth' => '1987-11-11',
            'birth_time' => '10:30:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];
        $canonicalKey = $handler->computeCanonicalKey($payload);

        $existingJob = AstrologyCalculationJob::create([
            'job_id' => 'calc_recalculate_seed_1',
            'calculation_type' => 'kundli',
            'canonical_key' => $canonicalKey,
            'payload' => KundliCalculationHandler::normalizePayload($payload),
            'status' => AstrologyCalculationJob::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => ['planets' => ['sun' => ['name' => 'Old Calculation']]],
        ]);

        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        // Calling recalculate should bypass cached result and launch worker
        $res = $this->postJson("/api/astrologer/jajmans/{$jajman->id}/kundli/calculate");
        $res->assertStatus(202);
        $this->assertTrue($workerLaunched, 'Force recalculate must launch worker even if previous calculation is completed');

        $existingJob->refresh();
        $this->assertEquals('pending', $existingJob->status);
    }
}
