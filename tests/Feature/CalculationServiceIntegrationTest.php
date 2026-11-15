<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\DailyPanchangCache;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\Drivers\CalculationDriverInterface;
use App\Services\Astrology\Calculation\Drivers\LocalCalculationDriver;
use App\Services\Astrology\Calculation\Drivers\RemoteHttpCalculationDriver;
use App\Services\Astrology\Calculation\Exceptions\CalculationServiceUnavailableException;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CalculationServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test_secret_key_12345';
    protected string $baseUrl = 'http://127.0.0.1:8080';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Helper to make a signed or custom HTTP request to the microservice.
     */
    protected function callService(
        string $method,
        string $path,
        ?array $data = null,
        ?string $secret = null,
        ?int $timestamp = null,
        ?string $nonce = null,
        bool $omitHeaders = false
    ): array {
        $secret ??= $this->secret;
        $timestamp ??= time();
        $nonce ??= bin2hex(random_bytes(16));
        $rawBody = $data !== null ? json_encode($data, JSON_UNESCAPED_SLASHES) : '';

        $canonical = "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n{$rawBody}";
        $signature = hash_hmac('sha256', $canonical, $secret);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!$omitHeaders) {
            $headers[] = "X-Timestamp: {$timestamp}";
            $headers[] = "X-Nonce: {$nonce}";
            $headers[] = "X-Signature: {$signature}";
        }

        if ($method === 'POST') {
            $headers[] = 'Content-Length: ' . strlen($rawBody);
        }

        $ch = curl_init("{$this->baseUrl}{$path}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'status' => $httpCode,
            'body' => json_decode((string) $response, true) ?? [],
            'raw' => (string) $response,
        ];
    }

    public function test_health_check_endpoint_is_accessible_without_auth(): void
    {
        $res = $this->callService('GET', '/health', null, null, null, null, true);
        $this->assertEquals(200, $res['status']);
        $this->assertEquals('ok', $res['body']['status'] ?? null);
        $this->assertEquals('astro-calc-service', $res['body']['service'] ?? null);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $res = $this->callService('POST', '/calculate/planetary-positions', ['jd' => 2460252.0], null, null, null, true);
        $this->assertEquals(401, $res['status']);
        $this->assertEquals('error', $res['body']['status'] ?? null);
        $this->assertStringContainsString('Missing HMAC authentication headers', $res['body']['message'] ?? '');
    }

    public function test_invalid_signature_is_rejected_with_401(): void
    {
        $res = $this->callService('POST', '/calculate/planetary-positions', ['jd' => 2460252.0], 'wrong_secret');
        $this->assertEquals(401, $res['status']);
        $this->assertEquals('error', $res['body']['status'] ?? null);
        $this->assertStringContainsString('Invalid signature', $res['body']['message'] ?? '');
    }

    public function test_expired_timestamp_is_rejected_with_401(): void
    {
        // 600 seconds in the past (> 300s limit)
        $expiredTs = time() - 600;
        $res = $this->callService('POST', '/calculate/planetary-positions', ['jd' => 2460252.0], $this->secret, $expiredTs);
        $this->assertEquals(401, $res['status']);
        $this->assertEquals('error', $res['body']['status'] ?? null);
        $this->assertStringContainsString('Expired timestamp', $res['body']['message'] ?? '');
    }

    public function test_replayed_nonce_is_rejected_with_401(): void
    {
        $fixedNonce = bin2hex(random_bytes(16));
        $now = time();

        // First call should succeed
        $res1 = $this->callService('POST', '/calculate/planetary-positions', ['jd' => 2460252.0], $this->secret, $now, $fixedNonce);
        $this->assertEquals(200, $res1['status']);

        // Second call with same nonce must be rejected
        $res2 = $this->callService('POST', '/calculate/planetary-positions', ['jd' => 2460252.0], $this->secret, $now, $fixedNonce);
        $this->assertEquals(401, $res2['status']);
        $this->assertStringContainsString('Nonce already used', $res2['body']['message'] ?? '');
    }

    public function test_planetary_positions_endpoint_returns_valid_data(): void
    {
        $driver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $result = $driver->calculatePlanetaryPositions(2460252.0);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('ayanamsha', $result);
        $this->assertArrayHasKey('planets', $result);
        // Planets are keyed by Moshier body ID (0 = Sun, 1 = Moon)
        $this->assertArrayHasKey(0, $result['planets']);
        $this->assertArrayHasKey(1, $result['planets']);
        $this->assertArrayHasKey('longitude', $result['planets'][0]);
        $this->assertGreaterThan(0.0, $result['ayanamsha']);
    }

    public function test_daily_panchang_endpoint_returns_valid_data(): void
    {
        $driver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $payload = [
            'date' => '2026-11-03',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'elevation' => 0.0,
        ];

        $result = $driver->calculateDailyPanchang($payload);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('panchanga', $result);
        $this->assertArrayHasKey('tithi', $result['panchanga']);
        $this->assertArrayHasKey('nakshatra', $result['panchanga']);
        $this->assertArrayHasKey('yoga', $result['panchanga']);
        $this->assertArrayHasKey('karana', $result['panchanga']);
        $this->assertArrayHasKey('sun_moon', $result);
        $this->assertArrayHasKey('sunrise', $result['sun_moon']);
        $this->assertArrayHasKey('sunset', $result['sun_moon']);
    }

    public function test_kundli_endpoint_returns_core_data(): void
    {
        $driver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $payload = [
            'date_of_birth' => '1995-05-15',
            'birth_time' => '14:30:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Integration Test',
            'birth_city' => 'New Delhi',
        ];

        $result = $driver->calculateKundli($payload);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('planets', $result);
        $this->assertArrayHasKey('ascendant', $result);
        $this->assertArrayHasKey('houses', $result);
        $this->assertArrayHasKey('dasha', $result);
    }

    public function test_kundli_complete_endpoint_returns_enriched_navamsha(): void
    {
        $driver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $payload = [
            'date_of_birth' => '1995-05-15',
            'birth_time' => '14:30:00',
            'birth_time_precision' => 'verified',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Integration Test Complete',
            'birth_city' => 'New Delhi',
        ];

        $result = $driver->calculateKundliComplete($payload);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('planets', $result);
        $this->assertArrayHasKey('ascendant', $result);
        $this->assertArrayHasKey('navamsha', $result);
        $this->assertArrayHasKey('manglik_dosha', $result);
        $this->assertArrayHasKey('yogas', $result);
    }

    public function test_daily_panchang_handler_end_to_end_with_remote_driver(): void
    {
        $driver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $handler = new DailyPanchangCalculationHandler(null, $driver);

        config(['astrology.driver' => 'remote']);

        $payload = [
            'date' => '2026-11-03',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];

        $canonicalKey = $handler->computeCanonicalKey($payload);
        $result = $handler->calculate($payload);
        $cacheKey = $handler->storeResult($canonicalKey, $result, $payload);

        $this->assertNotEmpty($cacheKey);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertDatabaseHas('daily_panchang_cache', [
            'canonical_key' => $canonicalKey,
        ]);
    }

    public function test_remote_failure_throws_exception_when_fallback_disabled(): void
    {
        $this->expectException(CalculationServiceUnavailableException::class);

        // Point to non-existent port with fallback disabled
        $driver = new RemoteHttpCalculationDriver('http://127.0.0.1:59999', $this->secret, 0.5, 0.5, false);
        $driver->calculatePlanetaryPositions(2460252.0);
    }

    public function test_remote_failure_falls_back_to_local_when_enabled(): void
    {
        config(['astrology.driver' => 'local']);
        $localDriver = new LocalCalculationDriver();
        $driver = new RemoteHttpCalculationDriver('http://127.0.0.1:59999', $this->secret, 0.5, 0.5, true, $localDriver);

        $result = $driver->calculatePlanetaryPositions(2460252.0);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('planets', $result);
    }

    public function test_tolerance_parity_between_remote_and_local_calculations(): void
    {
        $remoteDriver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $localDriver = new LocalCalculationDriver();

        $jd = 2460252.0;
        $remote = $remoteDriver->calculatePlanetaryPositions($jd);
        $local = $localDriver->calculatePlanetaryPositions($jd);

        // Ayanamsha parity within 0.000001 deg
        $this->assertEqualsWithDelta($local['ayanamsha'], $remote['ayanamsha'], 0.000001, 'Ayanamsha deviates between remote and local');

        // All 11 supported bodies within 0.0001 deg (Bodies 0-9 and Mean Node 21)
        $supportedBodies = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 21];
        foreach ($supportedBodies as $bodyId) {
            $this->assertArrayHasKey($bodyId, $remote['planets']);
            $this->assertArrayHasKey($bodyId, $local['planets']);

            $this->assertEqualsWithDelta(
                $local['planets'][$bodyId]['longitude'],
                $remote['planets'][$bodyId]['longitude'],
                0.0001,
                "Planet ID {$bodyId} longitude deviates between remote and local"
            );
            $this->assertEqualsWithDelta(
                $local['planets'][$bodyId]['latitude'],
                $remote['planets'][$bodyId]['latitude'],
                0.0001,
                "Planet ID {$bodyId} latitude deviates between remote and local"
            );
        }
    }

    public function test_kundli_job_lifecycle_and_persistence_via_remote_driver(): void
    {
        $driver = new RemoteHttpCalculationDriver($this->baseUrl, $this->secret);
        $handler = new KundliCalculationHandler(null, $driver);
        $jobManager = app(CalculationJobManager::class);

        $payload = [
            'date_of_birth' => '1990-08-20',
            'birth_time' => '09:15:00',
            'birth_time_precision' => 'verified',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Lifecycle Test',
            'birth_city' => 'Mumbai',
        ];

        // 1. Enqueue job
        $jobResult = $jobManager->findOrCreateJob('kundli', $payload);
        $this->assertTrue($jobResult['is_new']);
        $job = $jobResult['job'];
        $this->assertEquals(AstrologyCalculationJob::STATUS_PENDING, $job->status);

        // 2. Worker claims job
        $workerId = 'worker-test-prod-1';
        $claimed = $jobManager->claimJobById($job->job_id, $workerId, 60);
        $this->assertNotNull($claimed);
        $this->assertEquals(AstrologyCalculationJob::STATUS_PROCESSING, $claimed->status);
        $this->assertEquals($workerId, $claimed->worker_id);

        // 3. Execute calculation using remote driver
        $result = $handler->calculate($payload);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('planets', $result);
        $this->assertArrayHasKey('ascendant', $result);

        // 4. Mark completed
        $cacheKey = "kundli:{$job->canonical_key}";
        $completed = $jobManager->markCompleted($claimed, $cacheKey, $workerId, $result);
        $this->assertTrue($completed);

        // 5. Verify database persistence and final status
        $refreshed = $job->fresh();
        $this->assertEquals(AstrologyCalculationJob::STATUS_COMPLETED, $refreshed->status);
        $this->assertNotNull($refreshed->completed_at);
        $this->assertNotNull($refreshed->result);
        $this->assertArrayHasKey('planets', $refreshed->result);
    }

    public function test_job_deduplication_reuses_existing_job(): void
    {
        $jobManager = app(CalculationJobManager::class);
        $payload = [
            'date' => '2026-12-25',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ];

        $res1 = $jobManager->findOrCreateJob('daily_panchang', $payload);
        $res2 = $jobManager->findOrCreateJob('daily_panchang', $payload);

        $this->assertTrue($res1['is_new']);
        $this->assertFalse($res2['is_new']);
        $this->assertEquals($res1['job']->job_id, $res2['job']->job_id);
    }

    public function test_production_configuration_strictly_disables_fallback(): void
    {
        config([
            'astrology.driver' => 'remote',
            'astrology.remote.fallback_to_local' => false,
        ]);

        $this->assertFalse(config('astrology.remote.fallback_to_local'));

        // Point to unreachable port with fallback disabled
        $driver = new RemoteHttpCalculationDriver('http://127.0.0.1:59999', $this->secret, 0.5, 0.5, false);

        $this->expectException(CalculationServiceUnavailableException::class);
        $driver->calculatePlanetaryPositions(2460252.0);
    }
}
