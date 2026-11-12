<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AstrologyCalculationJob;
use App\Models\DailyPanchangCache;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\CanonicalPanchangCacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersistentPanchangCalculationTest extends TestCase
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

    public function test_first_request_creates_job_and_launches_targeted_worker(): void
    {
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function ($bin, $artisan) use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $url = '/api/v1/panchang/daily?date=2026-11-12&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $res = $this->getJson($url);

        $res->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $jobId = $res->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched);

        $this->assertDatabaseHas('astrology_calculation_jobs', [
            'job_id' => $jobId,
            'calculation_type' => 'daily_panchang',
            'status' => 'pending',
        ]);
    }

    public function test_cli_worker_with_job_flag_computes_and_persists_to_mysql(): void
    {
        $payload = [
            'date' => '2026-11-12',
            'lat' => 28.6139,
            'lon' => 77.2090,
            'tz' => 'Asia/Kolkata',
            'elevation' => 0.0,
        ];

        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: $payload['date'],
            latitude: $payload['lat'],
            longitude: $payload['lon'],
            timezone: $payload['tz']
        );
        $canonicalKey = CanonicalPanchangCacheKey::generateHash($attributes);

        $job = AstrologyCalculationJob::create([
            'job_id' => 'calc_test_targeted_123',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => $canonicalKey,
            'payload' => $payload,
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
        $this->assertArrayHasKey('panchanga', $job->result);

        // Verify saved in dedicated persistent MySQL cache table
        $this->assertDatabaseHas('daily_panchang_cache', [
            'canonical_key' => $canonicalKey,
            'date' => '2026-11-12',
        ]);

        $dbCache = DailyPanchangCache::where('canonical_key', $canonicalKey)->first();
        $this->assertNotNull($dbCache);
        $this->assertIsArray($dbCache->result);
        $this->assertArrayHasKey('panchanga', $dbCache->result);
    }

    public function test_subsequent_request_returns_saved_result_from_mysql_even_after_cache_flush(): void
    {
        $payload = [
            'date' => '2026-11-12',
            'lat' => 28.6139,
            'lon' => 77.2090,
            'tz' => 'Asia/Kolkata',
        ];

        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: $payload['date'],
            latitude: $payload['lat'],
            longitude: $payload['lon'],
            timezone: $payload['tz']
        );
        $canonicalKey = CanonicalPanchangCacheKey::generateHash($attributes);

        // Seed directly into MySQL persistent cache
        DailyPanchangCache::create([
            'canonical_key' => $canonicalKey,
            'date' => '2026-11-12',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'elevation' => 0.0,
            'result' => [
                'metadata' => ['date' => '2026-11-12'],
                'panchanga' => ['vara' => ['name' => 'Guruvara']],
            ],
        ]);

        // Completely flush application fast cache
        Cache::flush();

        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        // Request Panchang
        $url = '/api/v1/panchang/daily?date=2026-11-12&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $res = $this->getJson($url);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.panchanga.vara.name', 'Guruvara');

        // Zero workers launched
        $this->assertFalse($workerLaunched, 'Worker should not launch when persistent MySQL cache exists');
    }

    public function test_coordinate_floating_point_precision_tolerance_reuses_cached_calculation(): void
    {
        $payload = [
            'date' => '2026-11-12',
            'lat' => 28.6139,
            'lon' => 77.2090,
            'tz' => 'Asia/Kolkata',
        ];

        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: $payload['date'],
            latitude: $payload['lat'],
            longitude: $payload['lon'],
            timezone: $payload['tz']
        );
        $canonicalKey = CanonicalPanchangCacheKey::generateHash($attributes);

        DailyPanchangCache::create([
            'canonical_key' => $canonicalKey,
            'date' => '2026-11-12',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'elevation' => 0.0,
            'result' => [
                'metadata' => ['date' => '2026-11-12'],
                'panchanga' => ['vara' => ['name' => 'Guruvara']],
            ],
        ]);

        // Request with minor insignificant 5th+ decimal place difference (~1 meter)
        $url = '/api/v1/panchang/daily?date=2026-11-12&lat=28.61390001&lon=77.20900002&tz=Asia/Kolkata';
        $res = $this->getJson($url);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.panchanga.vara.name', 'Guruvara');
    }

    public function test_astro_setu_calculates_and_pandit_setu_shares_cached_result_without_worker(): void
    {
        // 1. Astro Setu requests date that is not cached
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $urlAstro = '/api/v1/panchang/daily?date=2026-11-20&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $resAstro = $this->getJson($urlAstro);
        $resAstro->assertStatus(202);
        $jobId = $resAstro->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched);

        // 2. Worker executes and persists to MySQL
        CalculationWorkerLauncher::setLaunchHook(null);
        $this->artisan('astro:calculate-worker', ['--job' => $jobId])->assertSuccessful();

        // 3. Verify in shared MySQL cache
        $this->assertDatabaseHas('daily_panchang_cache', [
            'date' => '2026-11-20',
        ]);

        // 4. Pandit Setu requests identical parameters
        $workerLaunchedAgain = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunchedAgain) {
            $workerLaunchedAgain = true;
            return true;
        });

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $urlPandit = '/api/astrologer/astrology/panchang/daily?date=2026-11-20&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $resPandit = $this->getJson($urlPandit);

        // 5. Pandit Setu receives HTTP 200 directly from shared cache WITHOUT launching worker
        $resPandit->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertHeader('X-Panchang-Cached', 'HIT');
        $this->assertNotNull($resPandit->json('data.panchanga.tithi'));
        $this->assertFalse($workerLaunchedAgain, 'Pandit Setu must reuse Astro Setu result without spawning worker');
    }

    public function test_pandit_setu_calculates_and_astro_setu_shares_cached_result_without_worker(): void
    {
        // 1. Pandit Setu calculates first
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');

        $urlPandit = '/api/astrologer/astrology/panchang/daily?date=2026-11-21&lat=19.0760&lon=72.8777&tz=Asia/Kolkata';
        $resPandit = $this->getJson($urlPandit);
        $resPandit->assertStatus(202);
        $jobId = $resPandit->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched);

        // 2. Worker executes
        CalculationWorkerLauncher::setLaunchHook(null);
        $this->artisan('astro:calculate-worker', ['--job' => $jobId])->assertSuccessful();

        // 3. Astro Setu requests identical date/location
        $workerLaunchedAgain = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunchedAgain) {
            $workerLaunchedAgain = true;
            return true;
        });

        $urlAstro = '/api/v1/panchang/daily?date=2026-11-21&lat=19.0760&lon=72.8777&tz=Asia/Kolkata';
        $resAstro = $this->getJson($urlAstro);

        // 4. Astro Setu receives HTTP 200 directly without launching worker
        $resAstro->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertHeader('X-Panchang-Cached', 'HIT');
        $this->assertNotNull($resAstro->json('data.panchanga.tithi'));
        $this->assertFalse($workerLaunchedAgain, 'Astro Setu must reuse Pandit Setu result without spawning worker');
    }

    public function test_different_date_creates_separate_calculation(): void
    {
        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        $res1 = $this->getJson('/api/v1/panchang/daily?date=2026-11-25&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $res2 = $this->getJson('/api/v1/panchang/daily?date=2026-11-26&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');

        $res1->assertStatus(202);
        $res2->assertStatus(202);

        $job1Id = $res1->json('data.job_id');
        $job2Id = $res2->json('data.job_id');

        $this->assertNotEquals($job1Id, $job2Id, 'Different dates must create separate calculation jobs');

        $job1 = AstrologyCalculationJob::where('job_id', $job1Id)->first();
        $job2 = AstrologyCalculationJob::where('job_id', $job2Id)->first();
        $this->assertNotEquals($job1->canonical_key, $job2->canonical_key);
    }

    public function test_different_location_creates_separate_calculation(): void
    {
        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        // Delhi vs Mumbai
        $resDelhi = $this->getJson('/api/v1/panchang/daily?date=2026-11-27&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $resMumbai = $this->getJson('/api/v1/panchang/daily?date=2026-11-27&lat=19.0760&lon=72.8777&tz=Asia/Kolkata');

        $resDelhi->assertStatus(202);
        $resMumbai->assertStatus(202);

        $jobDelhiId = $resDelhi->json('data.job_id');
        $jobMumbaiId = $resMumbai->json('data.job_id');

        $this->assertNotEquals($jobDelhiId, $jobMumbaiId, 'Different geographic locations must create separate jobs');
    }

    public function test_identical_simultaneous_requests_create_only_one_active_calculation(): void
    {
        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        $url = '/api/v1/panchang/daily?date=2026-11-28&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $resA = $this->getJson($url);
        $resB = $this->getJson($url);

        $resA->assertStatus(202);
        $resB->assertStatus(202);

        $this->assertEquals($resA->json('data.job_id'), $resB->json('data.job_id'), 'Simultaneous requests must deduplicate to single job');
        $this->assertEquals(1, AstrologyCalculationJob::where('canonical_key', $resA->json('data.job_id'))->orWhere('job_id', $resA->json('data.job_id'))->count());
    }

    public function test_cache_survives_optimize_clear_and_server_restarts(): void
    {
        // 1. Calculate and persist
        $url = '/api/v1/panchang/daily?date=2026-11-29&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $res = $this->getJson($url);
        $jobId = $res->json('data.job_id');

        $this->artisan('astro:calculate-worker', ['--job' => $jobId])->assertSuccessful();

        // 2. Simulate server restart and cache flush (php artisan optimize:clear / cache:clear)
        Cache::flush();

        // 3. Request again
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $resAfterRestart = $this->getJson($url);
        $resAfterRestart->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertHeader('X-Panchang-Cached', 'HIT');

        $this->assertFalse($workerLaunched, 'Cache must survive complete cache flush and server restarts');
    }

    public function test_failed_calculation_is_recoverable(): void
    {
        // Create an existing failed job
        $payload = [
            'date' => '2026-11-30',
            'lat' => 28.6139,
            'lon' => 77.2090,
            'tz' => 'Asia/Kolkata',
            'elevation' => 0.0,
        ];
        $attributes = CanonicalPanchangCacheKey::canonicalAttributes(
            localDate: $payload['date'],
            latitude: $payload['lat'],
            longitude: $payload['lon'],
            timezone: $payload['tz']
        );
        $canonicalKey = CanonicalPanchangCacheKey::generateHash($attributes);

        $failedJob = AstrologyCalculationJob::create([
            'job_id' => 'calc_failed_job_test',
            'calculation_type' => 'daily_panchang',
            'canonical_key' => $canonicalKey,
            'payload' => $payload,
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'error_message' => 'Simulated transient network glitch',
        ]);

        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        // Client retries requesting the same date
        $url = '/api/v1/panchang/daily?date=2026-11-30&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $res = $this->getJson($url);

        $res->assertStatus(202);
        $this->assertTrue($workerLaunched, 'Failed job must automatically launch fresh worker on retry without manual SSH intervention');

        $failedJob->refresh();
        $this->assertEquals('pending', $failedJob->status);
        $this->assertNull($failedJob->error_message);
    }

    public function test_web_requests_never_initialize_ffi(): void
    {
        $ffiResolved = false;
        $this->app->resolving(\JmeEph\FFI\JmeEphFFI::class, function () use (&$ffiResolved) {
            $ffiResolved = true;
            throw new \RuntimeException('Native FFI engine was resolved during web request!');
        });

        CalculationWorkerLauncher::setLaunchHook(fn() => true);

        // 1. Astro Setu web request
        $res1 = $this->getJson('/api/v1/panchang/daily?date=2026-12-01&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $res1->assertStatus(202);

        // 2. Pandit Setu web request
        $astrologer = $this->createAstrologer();
        $this->actingAs($astrologer, 'sanctum');
        $res2 = $this->getJson('/api/astrologer/astrology/panchang/daily?date=2026-12-01&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $res2->assertStatus(202);

        $this->assertFalse($ffiResolved, 'Native FFI must NEVER be resolved during web requests');
    }
}
