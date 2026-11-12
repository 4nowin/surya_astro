<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\DailyPanchangCache;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\CanonicalPanchangCacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
}
