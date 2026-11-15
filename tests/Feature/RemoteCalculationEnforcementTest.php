<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\DailyPanchangCache;
use App\Models\KundliReport;
use App\Models\NotificationDelivery;
use App\Models\ReportPurchase;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\Drivers\CalculationDriverInterface;
use App\Services\Astrology\Calculation\Drivers\RemoteHttpCalculationDriver;
use App\Services\Astrology\Calculation\Exceptions\CalculationServiceUnavailableException;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\DetailedKundliReportCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\PersonalizedInsightsCalculationHandler;
use App\Services\FirebaseNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JmeEph\FFI\JmeEphFFI;
use Mockery;
use Tests\TestCase;

class RemoteCalculationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'astrology.driver' => 'remote',
            'astrology.remote.fallback_to_local' => false,
            'astrology.remote.url' => 'http://127.0.0.1:8080',
            'astrology.remote.secret' => 'test_hmac_secret_key',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 1. Test Daily Panchang uses the remote driver and stores results in cache & MySQL.
     */
    public function test_daily_panchang_uses_remote_driver_and_stores_result(): void
    {
        $mockDriver = Mockery::mock(CalculationDriverInterface::class);
        $mockDriver->shouldReceive('calculateDailyPanchang')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['date'] === '2026-11-20' && (float) $payload['lat'] === 28.6139;
            }))
            ->andReturn([
                'panchanga' => [
                    'tithi' => ['name' => 'Ekadashi', 'index' => 11],
                    'nakshatra' => ['name' => 'Rohini', 'index' => 4],
                ],
                'sun_moon' => [
                    'sunrise' => '06:45 AM',
                    'sunset' => '05:30 PM',
                ],
            ]);

        $handler = new DailyPanchangCalculationHandler(null, $mockDriver);

        $payload = [
            'date' => '2026-11-20',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'elevation' => 0.0,
        ];

        $canonicalKey = $handler->computeCanonicalKey($payload);
        $result = $handler->calculate($payload);
        $cacheKey = $handler->storeResult($canonicalKey, $result, $payload);

        $this->assertEquals('Ekadashi', $result['panchanga']['tithi']['name']);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertDatabaseHas('daily_panchang_cache', [
            'canonical_key' => $canonicalKey,
        ]);

        // Subsequent lookup should hit cache
        $cached = $handler->getCachedResult($canonicalKey, $payload);
        $this->assertNotNull($cached);
        $this->assertEquals('Ekadashi', $cached['panchanga']['tithi']['name']);
    }

    /**
     * 2. Test Kundli uses the remote driver and stores results in cache & MySQL.
     */
    public function test_kundli_uses_remote_driver_and_stores_result(): void
    {
        $mockDriver = Mockery::mock(CalculationDriverInterface::class);
        $mockDriver->shouldReceive('calculateKundliComplete')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['date_of_birth'] === '1992-05-15';
            }))
            ->andReturn([
                'planets' => [
                    'sun' => ['rashi' => ['id' => 2, 'name' => 'Taurus']],
                    'moon' => ['rashi' => ['id' => 6, 'name' => 'Virgo']],
                ],
                'ascendant' => ['id' => 1, 'name' => 'Aries'],
                'navamsha' => ['status' => 'available'],
                'saturn_transit' => ['phase' => 'none'],
            ]);

        $handler = new KundliCalculationHandler(null, $mockDriver);

        $payload = [
            'date_of_birth' => '1992-05-15',
            'birth_time' => '14:30:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'name' => 'Test User',
            'birth_city' => 'Delhi',
        ];

        $canonicalKey = $handler->computeCanonicalKey($payload);
        $result = $handler->calculate($payload);
        $cacheKey = $handler->storeResult($canonicalKey, $result, $payload);

        $this->assertEquals('Taurus', $result['planets']['sun']['rashi']['name']);
        $this->assertTrue(Cache::has($cacheKey));

        // Subsequent lookup should hit cache
        $cached = $handler->getCachedResult($canonicalKey, $payload);
        $this->assertNotNull($cached);
        $this->assertEquals('Taurus', $cached['planets']['sun']['rashi']['name']);
    }

    /**
     * 3. Test Personalized Insights uses the remote driver and does NOT invoke local FFI.
     */
    public function test_personalized_insights_uses_remote_driver_without_invoking_local_ffi(): void
    {
        // Bind JmeEphFFI to throw immediately if any code attempts to invoke local FFI
        $this->app->bind(JmeEphFFI::class, function () {
            throw new \RuntimeException("FORBIDDEN: Local FFI invocation detected on Hostinger!");
        });

        $mockDriver = Mockery::mock(CalculationDriverInterface::class);
        $mockDriver->shouldReceive('calculatePersonalizedInsights')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['date_of_birth'] === '1995-08-10';
            }))
            ->andReturn([
                'metadata' => [
                    'ruleset_version' => 'M4B_V1',
                    'target_date' => '2026-11-20',
                ],
                'birth_chart_reference' => [
                    'sun_sign' => ['name' => 'Leo'],
                    'moon_sign' => ['name' => 'Scorpio', 'status' => 'confirmed'],
                ],
                'current_transits' => [
                    'jupiter' => ['planet' => 'Jupiter', 'gochara_house' => 5],
                ],
            ]);

        $handler = new PersonalizedInsightsCalculationHandler(null, $mockDriver);

        $payload = [
            'date_of_birth' => '1995-08-10',
            'birth_time' => '10:00:00',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'target_date' => '2026-11-20',
        ];

        $canonicalKey = $handler->computeCanonicalKey($payload);
        $result = $handler->calculate($payload);
        $cacheKey = $handler->storeResult($canonicalKey, $result, $payload);

        $this->assertEquals('Leo', $result['birth_chart_reference']['sun_sign']['name']);
        $this->assertTrue(Cache::has($cacheKey));

        $cached = $handler->getCachedResult($canonicalKey, $payload);
        $this->assertNotNull($cached);
        $this->assertEquals('Leo', $cached['birth_chart_reference']['sun_sign']['name']);
    }

    /**
     * 4. Test Detailed Kundli reports do NOT invoke local FFI when driver is remote.
     */
    public function test_detailed_kundli_report_uses_remote_calculations_without_local_ffi(): void
    {
        // Enforce FFI prohibition
        $this->app->bind(JmeEphFFI::class, function () {
            throw new \RuntimeException("FORBIDDEN: Local FFI invocation detected during detailed Kundli report generation!");
        });

        // Mock remote driver return for KundliCalculationHandler
        $mockDriver = Mockery::mock(CalculationDriverInterface::class);
        $mockDriver->shouldReceive('calculateKundliComplete')
            ->atLeast()->once()
            ->andReturn([
                'planets' => [
                    'sun' => ['name' => 'Sun', 'rashi' => ['id' => 7, 'name' => 'Libra', 'degrees' => 10.0], 'degree_in_rashi' => 10.0, 'speed' => 1.0, 'is_retrograde' => false],
                    'moon' => ['name' => 'Moon', 'rashi' => ['id' => 1, 'name' => 'Aries', 'degrees' => 15.0], 'degree_in_rashi' => 15.0, 'speed' => 13.0, 'is_retrograde' => false, 'nakshatra' => ['id' => 1, 'name' => 'Ashwini', 'pada' => 1]],
                    'mars' => ['name' => 'Mars', 'rashi' => ['id' => 10, 'name' => 'Capricorn', 'degrees' => 5.0], 'degree_in_rashi' => 5.0, 'speed' => 0.6, 'is_retrograde' => false],
                    'mercury' => ['name' => 'Mercury', 'rashi' => ['id' => 7, 'name' => 'Libra', 'degrees' => 12.0], 'degree_in_rashi' => 12.0, 'speed' => 1.2, 'is_retrograde' => false],
                    'jupiter' => ['name' => 'Jupiter', 'rashi' => ['id' => 4, 'name' => 'Cancer', 'degrees' => 20.0], 'degree_in_rashi' => 20.0, 'speed' => 0.1, 'is_retrograde' => false],
                    'venus' => ['name' => 'Venus', 'rashi' => ['id' => 8, 'name' => 'Scorpio', 'degrees' => 2.0], 'degree_in_rashi' => 2.0, 'speed' => 1.1, 'is_retrograde' => false],
                    'saturn' => ['name' => 'Saturn', 'rashi' => ['id' => 11, 'name' => 'Aquarius', 'degrees' => 25.0], 'degree_in_rashi' => 25.0, 'speed' => 0.05, 'is_retrograde' => false],
                    'rahu' => ['name' => 'Rahu', 'rashi' => ['id' => 11, 'name' => 'Aquarius', 'degrees' => 1.0], 'degree_in_rashi' => 1.0, 'speed' => -0.05, 'is_retrograde' => true],
                    'ketu' => ['name' => 'Ketu', 'rashi' => ['id' => 5, 'name' => 'Leo', 'degrees' => 1.0], 'degree_in_rashi' => 1.0, 'speed' => -0.05, 'is_retrograde' => true],
                ],
                'ascendant' => ['id' => 1, 'name' => 'Aries', 'degrees' => 12.5, 'status' => 'confirmed'],
                'houses' => array_combine(range(1, 12), array_map(function ($h) {
                    return ['house_number' => $h, 'sign_id' => $h, 'rashi' => ['id' => $h, 'name' => 'Sign ' . $h, 'vedic_name' => 'Rashi ' . $h, 'lord' => 'Sun'], 'planets' => []];
                }, range(1, 12))),
                'dasha' => [
                    'vimshottari' => [
                        'current' => ['maha_dasha' => 'Ketu', 'antar_dasha' => 'Venus', 'pratyantar_dasha' => 'Sun'],
                        'timeline' => [],
                    ],
                ],
                'navamsha' => [
                    'status' => 'available',
                    'ascendant' => ['id' => 1, 'name' => 'Aries'],
                    'planets' => [],
                    'houses' => [],
                ],
                'stability' => [
                    'ascendant' => ['d1_ascendant' => ['status' => 'confirmed', 'name' => 'Aries', 'id' => 1], 'd9_ascendant' => ['status' => 'confirmed', 'name' => 'Aries', 'id' => 1], 'is_lagna_invariant' => true],
                    'd1_ascendant' => ['status' => 'confirmed', 'name' => 'Aries', 'id' => 1],
                    'd9_ascendant' => ['status' => 'confirmed', 'name' => 'Aries', 'id' => 1],
                    'panchanga' => ['moon_sign' => ['status' => 'confirmed', 'name' => 'Aries'], 'nakshatra' => ['status' => 'confirmed', 'name' => 'Ashwini'], 'pada' => ['pada' => 1]],
                    'birth_panchanga' => ['moon_sign' => ['status' => 'confirmed', 'name' => 'Aries'], 'nakshatra' => ['status' => 'confirmed', 'name' => 'Ashwini'], 'pada' => ['pada' => 1]],
                    'vimshottari_dasha' => ['status' => 'certified', 'current_dasha' => ['maha_dasha' => 'Ketu'], 'timeline' => []],
                ],
                'saturn_transit' => ['phase' => 'none', 'status' => 'fallback', 'is_stable' => true],
                'saturn_transit_timeline' => [
                    'status' => 'fallback',
                    'is_stable' => true,
                    'qualitative_fallback' => ['phase' => 'none'],
                    'reason' => 'Remote calculation summary',
                ],
            ]);

        $this->app->singleton(CalculationDriverInterface::class, fn() => $mockDriver);
        $this->app->singleton(KundliCalculationHandler::class, fn() => new KundliCalculationHandler(null, $mockDriver));

        $user = User::factory()->create();
        $purchase = ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => (string) Str::uuid(),
            'transaction_type' => ReportPurchase::TYPE_DEBIT,
            'amount' => 149.00,
            'currency' => 'INR',
            'status' => ReportPurchase::STATUS_COMPLETED,
        ]);

        $reportUuid = (string) Str::uuid();
        $report = KundliReport::create([
            'uuid' => $reportUuid,
            'user_id' => $user->id,
            'report_purchase_id' => $purchase->id,
            'birth_payload' => [
                'name' => 'Remote Report User',
                'date_of_birth' => '1990-10-24',
                'birth_time' => '07:15:00',
                'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
                'latitude' => 28.6139,
                'longitude' => 77.2090,
                'timezone' => 'Asia/Kolkata',
                'birth_city' => 'Delhi',
                'birth_country' => 'India',
            ],
            'language' => 'en',
            'status' => KundliReport::STATUS_QUEUED,
        ]);

        $handler = app(DetailedKundliReportCalculationHandler::class);
        $result = $handler->calculate([
            'report_uuid' => $reportUuid,
            'worker_id' => 'worker_test_remote',
            'attempt' => 1,
            'language' => 'en',
        ]);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals($reportUuid, $result['report_uuid']);
        $report->refresh();
        $this->assertEquals(KundliReport::STATUS_COMPLETED, $report->status);
        $this->assertNotEmpty($report->file_path);
    }

    /**
     * 5. Test notification-related calculations do NOT invoke local FFI and use remote driver/cache.
     */
    public function test_notification_calculations_route_through_remote_driver_without_ffi(): void
    {
        // Enforce FFI prohibition
        $this->app->bind(JmeEphFFI::class, function () {
            throw new \RuntimeException("FORBIDDEN: Local FFI invocation detected during notification dispatch!");
        });

        $mockDriver = Mockery::mock(CalculationDriverInterface::class);
        $mockDriver->shouldReceive('calculateDailyPanchang')
            ->once()
            ->andReturn([
                'panchanga' => [
                    'tithi' => ['name' => 'Shukla Navami'],
                    'nakshatra' => ['name' => 'Pushya'],
                ],
                'sun_moon' => [
                    'sunrise' => '06:30 AM',
                ],
            ]);

        $this->app->singleton(CalculationDriverInterface::class, fn() => $mockDriver);
        $this->app->singleton(DailyPanchangCalculationHandler::class, fn() => new DailyPanchangCalculationHandler(null, $mockDriver));

        $user = User::factory()->create(['language' => 'en']);
        $device = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'fcm_remote_calc_device',
            'is_active' => true,
        ]);

        UserNotificationPreference::create([
            'user_id' => $user->id,
            'master_enabled' => true,
            'daily_briefing' => true,
            'daily_briefing_time' => '07:00:00',
            'quiet_hours_enabled' => false,
        ]);

        $mockFcm = Mockery::mock(FirebaseNotificationService::class);
        $mockFcm->shouldReceive('sendToToken')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'msg_remote_panchang_123',
            ]);

        $this->app->instance(FirebaseNotificationService::class, $mockFcm);

        $exitCode = Artisan::call('notifications:dispatch', [
            '--date' => '2026-11-20',
            '--user' => $user->id,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_device_id' => $device->id,
            'notification_type' => 'daily_briefing',
            'status' => 'sent',
            'fcm_message_id' => 'msg_remote_panchang_123',
        ]);
    }

    /**
     * 6. Test VPS failure results in a controlled failure/retry, NEVER local fallback.
     */
    public function test_vps_failure_results_in_controlled_failure_never_local_fallback(): void
    {
        // Even if fallback_to_local is true in the constructor, when astrology.driver is remote,
        // local fallback MUST be impossible and throw CalculationServiceUnavailableException.
        $driver = new RemoteHttpCalculationDriver(
            baseUrl: 'http://127.0.0.1:59999', // Unreachable port
            secret: 'secret',
            connectTimeout: 0.2,
            timeout: 0.2,
            fallbackToLocal: true // Attempt to request fallback
        );

        // 1. Planetary positions
        try {
            $driver->calculatePlanetaryPositions(2460252.0);
            $this->fail('Expected CalculationServiceUnavailableException was not thrown for planetary positions');
        } catch (CalculationServiceUnavailableException $e) {
            $this->assertStringContainsString('Calculation microservice unreachable', $e->getMessage());
        }

        // 2. Daily Panchang
        try {
            $driver->calculateDailyPanchang(['date' => '2026-11-20', 'lat' => 28.6, 'lon' => 77.2]);
            $this->fail('Expected CalculationServiceUnavailableException was not thrown for daily panchang');
        } catch (CalculationServiceUnavailableException $e) {
            $this->assertStringContainsString('Calculation microservice unreachable', $e->getMessage());
        }

        // 3. Kundli
        try {
            $driver->calculateKundli(['dob' => '1990-01-01']);
            $this->fail('Expected CalculationServiceUnavailableException was not thrown for kundli');
        } catch (CalculationServiceUnavailableException $e) {
            $this->assertStringContainsString('Calculation microservice unreachable', $e->getMessage());
        }

        // 4. Kundli complete
        try {
            $driver->calculateKundliComplete(['date_of_birth' => '1990-01-01']);
            $this->fail('Expected CalculationServiceUnavailableException was not thrown for kundli complete');
        } catch (CalculationServiceUnavailableException $e) {
            $this->assertStringContainsString('Calculation microservice unreachable', $e->getMessage());
        }

        // 5. Personalized insights
        try {
            $driver->calculatePersonalizedInsights(['date_of_birth' => '1990-01-01']);
            $this->fail('Expected CalculationServiceUnavailableException was not thrown for personalized insights');
        } catch (CalculationServiceUnavailableException $e) {
            $this->assertStringContainsString('Calculation microservice unreachable', $e->getMessage());
        }
    }

    /**
     * 7. Test minimal structured logging contains job ID, type, endpoint, status, duration and NO secrets.
     */
    public function test_remote_http_driver_logs_successful_requests_without_sensitive_payloads(): void
    {
        $loggedMessages = [];
        Log::shouldReceive('info')
            ->andReturnUsing(function ($message) use (&$loggedMessages) {
                $loggedMessages[] = (string) $message;
            });
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        // Subclass to mock HTTP execution without actual network socket
        $driver = new class('http://127.0.0.1:8080', 'secret') extends RemoteHttpCalculationDriver {
            protected function request(
                string $method,
                string $path,
                ?array $data = null,
                ?string $calcType = null,
                ?string $jobId = null
            ): array {
                $jobStr = !empty($jobId) ? $jobId : 'none';
                $typeStr = !empty($calcType) ? $calcType : 'unknown';
                $httpCode = 200;
                $durationMs = 12.34;
                Log::info("Remote calculation request successful: job_id=[{$jobStr}], type=[{$typeStr}], endpoint=[{$path}], status=[{$httpCode}], duration_ms=[{$durationMs}]");

                return ['status' => 'success', 'data' => ['result' => 'ok']];
            }
        };

        $result = $driver->calculateKundli([
            'job_id' => 'job_audit_98765',
            'date_of_birth' => '1990-05-20',
            'sensitive_token' => 'super_secret_payload_token_do_not_log',
        ]);

        $this->assertEquals(['result' => 'ok'], $result);
        $this->assertNotEmpty($loggedMessages);

        $logLine = $loggedMessages[count($loggedMessages) - 1];
        $this->assertStringContainsString('job_id=[job_audit_98765]', $logLine);
        $this->assertStringContainsString('type=[kundli]', $logLine);
        $this->assertStringContainsString('endpoint=[/calculate/kundli]', $logLine);
        $this->assertStringContainsString('status=[200]', $logLine);
        $this->assertStringContainsString('duration_ms=[12.34]', $logLine);

        // Crucial verification: Secrets or raw payload bodies must NEVER appear in logs
        $this->assertStringNotContainsString('super_secret_payload_token', $logLine);
        $this->assertStringNotContainsString('1990-05-20', $logLine);
        $this->assertStringNotContainsString('secret', $logLine);
    }
}
