<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\User;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use App\Services\Astrology\CanonicalPanchangCacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AstrologyCalculationInfrastructureTest extends TestCase
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

    public function test_missing_panchang_result_creates_background_job_and_returns_202(): void
    {
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function ($bin, $artisan) use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $response = $this->getJson('/api/v1/panchang/daily?date=2026-11-03&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.calculation_type', 'daily_panchang')
            ->assertJsonPath('data.status', 'pending');

        $jobId = $response->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched);

        $this->assertDatabaseHas('astrology_calculation_jobs', [
            'job_id' => $jobId,
            'calculation_type' => 'daily_panchang',
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    public function test_repeated_identical_requests_reuse_single_job(): void
    {
        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        $res1 = $this->getJson('/api/v1/panchang/daily?date=2026-11-03&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $res2 = $this->getJson('/api/v1/panchang/daily?date=2026-11-03&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');

        $res1->assertStatus(202);
        $res2->assertStatus(202);

        $jobId1 = $res1->json('data.job_id');
        $jobId2 = $res2->json('data.job_id');

        $this->assertEquals($jobId1, $jobId2);
        $this->assertEquals(1, AstrologyCalculationJob::where('calculation_type', 'daily_panchang')->count());
    }

    public function test_completed_cached_result_returns_200_without_worker_launch(): void
    {
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: '2026-11-03',
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata'
        );
        $hash = CanonicalPanchangCacheKey::generateHash($attributes);
        $cacheKey = "panchang:daily:{$hash}";

        $dummyPanchang = [
            'metadata' => [
                'date' => '2026-11-03',
                'latitude' => 28.6139,
                'longitude' => 77.2090,
                'timezone' => 'Asia/Kolkata',
                'cached' => true,
            ],
            'panchanga' => [
                'vara' => ['name' => 'Tuesday'],
            ],
        ];

        Cache::put($cacheKey, $dummyPanchang, now()->addDays(30));

        $response = $this->getJson('/api/v1/panchang/daily?date=2026-11-03&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');

        $response->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'HIT')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.panchanga.vara.name', 'Tuesday');

        $this->assertFalse($workerLaunched);
    }

    public function test_worker_command_claims_and_completes_job(): void
    {
        $job = AstrologyCalculationJob::create([
            'job_id' => 'calc_test_worker_123',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => 'hash_test_123',
            'payload' => [
                'date' => '2026-11-03',
                'lat' => 28.6139,
                'lon' => 77.2090,
                'tz' => 'Asia/Kolkata',
            ],
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])
            ->assertSuccessful();

        $job->refresh();
        $this->assertTrue($job->isCompleted());
        $this->assertNotNull($job->completed_at);
        $this->assertEquals(1, $job->attempts);
        $this->assertNotNull($job->result_cache_key);

        $this->assertTrue(Cache::has($job->result_cache_key));
    }

    public function test_calculation_status_endpoint_reports_status_and_returns_result_when_completed(): void
    {
        $job = AstrologyCalculationJob::create([
            'job_id' => 'calc_status_test_456',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => 'hash_status_456',
            'payload' => [
                'date' => '2026-11-03',
                'lat' => 28.6139,
                'lon' => 77.2090,
                'tz' => 'Asia/Kolkata',
            ],
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // When pending
        $pendingRes = $this->getJson("/api/v1/calculations/{$job->job_id}");
        $pendingRes->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.ready', false);

        // When completed
        $cacheKey = "panchang:daily:hash_status_456";
        Cache::put($cacheKey, ['metadata' => ['date' => '2026-11-03']], now()->addDays(30));

        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result_cache_key' => $cacheKey,
        ]);

        $completedRes = $this->getJson("/api/v1/calculations/{$job->job_id}");
        $completedRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.result.metadata.date', '2026-11-03');
    }

    public function test_personal_job_is_protected_from_unauthorized_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $personalJob = AstrologyCalculationJob::create([
            'job_id' => 'calc_personal_789',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => 'hash_personal_789',
            'payload' => ['date' => '2026-11-03', 'lat' => 28.6139, 'lon' => 77.2090, 'tz' => 'Asia/Kolkata'],
            'status' => 'pending',
            'user_id' => $user1->id,
        ]);

        // Public unauthenticated user cannot view user1's job
        $this->getJson("/api/v1/calculations/{$personalJob->job_id}")
            ->assertStatus(404);

        // Another user cannot view user1's job
        $this->actingAs($user2, 'sanctum')
            ->getJson("/api/v1/calculations/{$personalJob->job_id}")
            ->assertStatus(404);

        // Owner can view the job
        $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/calculations/{$personalJob->job_id}")
            ->assertStatus(200);
    }

    public function test_stale_processing_job_is_reclaimed_safely(): void
    {
        $staleJob = AstrologyCalculationJob::create([
            'job_id' => 'calc_stale_111',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => 'hash_stale_111',
            'payload' => ['date' => '2026-11-03', 'lat' => 28.6139, 'lon' => 77.2090, 'tz' => 'Asia/Kolkata'],
            'status' => 'processing',
            'worker_id' => 'dead_worker_999',
            'lease_expires_at' => now()->subMinutes(5), // Stale expired lease
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        /** @var CalculationJobManager $jobManager */
        $jobManager = app(CalculationJobManager::class);

        $claimed = $jobManager->claimNextJob('new_worker_222', 60);

        $this->assertNotNull($claimed);
        $this->assertEquals($staleJob->job_id, $claimed->job_id);
        $this->assertEquals(2, $claimed->attempts);
        $this->assertEquals('new_worker_222', $claimed->worker_id);
    }

    public function test_failed_job_exhausting_max_attempts_is_not_reclaimed(): void
    {
        $failedJob = AstrologyCalculationJob::create([
            'job_id' => 'calc_failed_222',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => 'hash_failed_222',
            'payload' => ['date' => '2026-11-03', 'lat' => 28.6139, 'lon' => 77.2090, 'tz' => 'Asia/Kolkata'],
            'status' => 'failed',
            'attempts' => 3,
            'max_attempts' => 3,
            'error_message' => 'Permanent ephemeris failure',
        ]);

        /** @var CalculationJobManager $jobManager */
        $jobManager = app(CalculationJobManager::class);

        $claimed = $jobManager->claimNextJob('worker_333', 60);

        $this->assertNull($claimed);
    }

    public function test_invalid_input_parameters_cannot_create_jobs(): void
    {
        $response = $this->getJson('/api/v1/panchang/daily?date=invalid-date&lat=999&lon=77.2090&tz=Asia/Kolkata');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'lat']);

        $this->assertEquals(0, AstrologyCalculationJob::count());
    }

    public function test_worker_lock_renewal_and_single_worker_protection(): void
    {
        /** @var CalculationWorkerLauncher $launcher */
        $launcher = app(CalculationWorkerLauncher::class);

        // Initially no worker is running
        $this->assertFalse($launcher->isWorkerRunning());

        // First worker acquires lock
        $this->assertTrue($launcher->acquireWorkerLock(60));
        $this->assertTrue($launcher->isWorkerRunning());

        // Second worker attempt to acquire fails
        $this->assertFalse($launcher->acquireWorkerLock(60));

        // Worker renews lock
        $launcher->renewWorkerLock(60);
        $this->assertTrue($launcher->isWorkerRunning());

        // Release lock
        $launcher->releaseWorkerLock();
        $this->assertFalse($launcher->isWorkerRunning());
    }

    public function test_database_enforces_active_job_uniqueness_and_allows_subsequent_completed_jobs(): void
    {
        /** @var CalculationJobManager $jobManager */
        $jobManager = app(CalculationJobManager::class);

        $payload = ['date' => '2026-11-03', 'lat' => 28.6139, 'lon' => 77.2090, 'tz' => 'Asia/Kolkata'];

        // Request 1 creates active job
        $job1 = $jobManager->findOrCreateJob('daily_panchang', $payload);
        $this->assertFalse($job1['ready']);
        $this->assertTrue($job1['is_new']);
        $this->assertNotNull($job1['job']->active_key);

        // Direct DB attempt to insert a duplicate active job with same active_key must fail unique constraint
        $duplicateAttemptCaught = false;
        try {
            AstrologyCalculationJob::create([
                'job_id' => 'calc_duplicate_raw',
                'calculation_type' => 'daily_panchang',
                'canonical_key' => $job1['canonical_key'],
                'active_key' => $job1['job']->active_key,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 3,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            $duplicateAttemptCaught = true;
        }
        $this->assertTrue($duplicateAttemptCaught, 'Database unique constraint must reject duplicate active jobs.');

        // Mark first job completed
        $jobManager->markCompleted($job1['job'], 'panchang:daily:' . $job1['canonical_key']);
        $job1['job']->refresh();
        $this->assertNull($job1['job']->active_key);

        // Now cache expired simulation: remove cache
        Cache::forget('panchang:daily:' . $job1['canonical_key']);

        // A new or re-queued job for the same calculation can now be active without conflict
        $job2 = $jobManager->findOrCreateJob('daily_panchang', $payload);
        $this->assertFalse($job2['ready']);
        $this->assertNotNull($job2['job']->active_key);
    }

    public function test_web_lifecycle_never_initializes_native_ffi_engine_across_all_scenarios(): void
    {
        // Assert that native JmeEphFFI and PanchangService are NEVER resolved in web context
        $ffiInstantiated = false;
        $this->app->resolving(\JmeEph\FFI\JmeEphFFI::class, function () use (&$ffiInstantiated) {
            $ffiInstantiated = true;
            throw new \RuntimeException('Native FFI engine was resolved during web request!');
        });

        $panchangServiceInstantiated = false;
        $this->app->resolving(\JayeshMepani\PanchangCore\Panchanga\PanchangService::class, function () use (&$panchangServiceInstantiated) {
            $panchangServiceInstantiated = true;
            throw new \RuntimeException('PanchangService was resolved during web request!');
        });

        $workerLaunchCount = 0;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunchCount) {
            $workerLaunchCount++;
            return true;
        });

        $url = '/api/v1/panchang/daily?date=2026-11-04&lat=19.0760&lon=72.8777&tz=Asia/Kolkata';

        // 1. Scenario: Cache miss
        $missRes = $this->getJson($url);
        $missRes->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');
        $this->assertEquals(1, $workerLaunchCount);
        $jobId = $missRes->json('data.job_id');

        // 2. Scenario: Duplicate request reuses the active job
        $dupRes = $this->getJson($url);
        $dupRes->assertStatus(202);
        $this->assertEquals($jobId, $dupRes->json('data.job_id'));

        // 3. Scenario: Job status transitions
        // 3a. Pending status
        $statusPending = $this->getJson("/api/v1/calculations/{$jobId}");
        $statusPending->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.ready', false);

        // 3b. Processing transition
        $job = AstrologyCalculationJob::where('job_id', $jobId)->firstOrFail();
        $job->update([
            'status' => AstrologyCalculationJob::STATUS_PROCESSING,
            'worker_id' => 'worker_cli_test',
            'started_at' => now(),
            'lease_expires_at' => now()->addSeconds(60),
        ]);
        $statusProcessing = $this->getJson("/api/v1/calculations/{$jobId}");
        $statusProcessing->assertStatus(200)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.ready', false);

        // 3c. Completed transition with cached result
        $dummyResult = [
            'metadata' => [
                'date' => '2026-11-04',
                'latitude' => 19.0760,
                'longitude' => 72.8777,
                'timezone' => 'Asia/Kolkata',
                'cached' => true,
            ],
            'panchanga' => [
                'vara' => ['name' => 'Wednesday'],
                'tithi' => ['name' => 'Ekadashi'],
            ],
        ];
        $cacheKey = "panchang:daily:{$job->canonical_key}";
        Cache::put($cacheKey, $dummyResult, now()->addDays(30));

        /** @var CalculationJobManager $jobManager */
        $jobManager = app(CalculationJobManager::class);
        $jobManager->markCompleted($job, $cacheKey);

        $statusCompleted = $this->getJson("/api/v1/calculations/{$jobId}");
        $statusCompleted->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.result.panchanga.vara.name', 'Wednesday');

        // 4. Scenario: Completed result returned by original Panchang endpoint
        $hitRes = $this->getJson($url);
        $hitRes->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'HIT')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.panchanga.vara.name', 'Wednesday');

        // Confirm worker was not launched again on cache hit
        $this->assertEquals(1, $workerLaunchCount);

        // Final assertion: Native FFI and PanchangService were NEVER touched
        $this->assertFalse($ffiInstantiated, 'JmeEphFFI must never be instantiated in web requests');
        $this->assertFalse($panchangServiceInstantiated, 'PanchangService must never be instantiated in web requests');
    }
}
