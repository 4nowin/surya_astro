<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\PersonalizedInsightsCalculationHandler;
use App\Services\Astrology\PersonalizedAstrologyService;
use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalizedAstrologyCalculationTest extends TestCase
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

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $todayRes = $this->getJson('/api/v1/astrology/personalized-insights/today');
        $todayRes->assertUnauthorized();

        $calcRes = $this->postJson('/api/v1/astrology/personalized-insights/calculate', []);
        $calcRes->assertUnauthorized();
    }

    public function test_authenticated_user_without_profiles_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/astrology/personalized-insights/today');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'No astrology profile found for user. Please create a birth profile first.']);
    }

    public function test_authenticated_user_cannot_access_another_users_profile(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $profileA = UserAstrologyProfile::create([
            'user_id' => $userA->id,
            'name' => 'User A Profile',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1990-05-15',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '14:30:00',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
        ]);

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/v1/astrology/personalized-insights/today?profile_id={$profileA->id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'The specified astrology profile was not found or does not belong to your account.']);
    }

    public function test_user_cannot_access_another_users_calculation_job(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $job = AstrologyCalculationJob::create([
            'job_id' => 'job_isolated_user_a_123',
            'calculation_type' => PersonalizedInsightsCalculationHandler::TYPE,
            'canonical_key' => 'hash_user_a_isolation_test',
            'payload' => ['profile_id' => 1],
            'user_id' => $userA->id,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/v1/calculations/{$job->job_id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Calculation job not found or access denied.']);
    }

    public function test_verified_birth_time_calculation_produces_confirmed_ascendant_and_all_navagraha_positions(): void
    {
        /** @var PersonalizedAstrologyService $service */
        $service = app(PersonalizedAstrologyService::class);

        // Verified profile: 1990-05-15 14:30:00 at New Delhi (28.6139, 77.2090)
        $result = $service->computePersonalizedInsights(
            birthDate: '1990-05-15',
            birthTime: '14:30:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata',
            targetDate: '2026-11-03'
        );

        // Metadata check
        $this->assertEquals(PersonalizedAstrologyService::RULESET_VERSION, $result['metadata']['ruleset_version']);
        $this->assertEquals('True Chitra Paksha (Lahiri)', $result['metadata']['ayanamsha']['name']);
        $this->assertEquals('TRUE_CITRA_LAHIRI', $result['metadata']['ayanamsha']['code']);
        $this->assertGreaterThan(23.0, $result['metadata']['ayanamsha']['value_degrees']);

        // Birth chart reference check
        $chart = $result['birth_chart_reference'];

        // Ascendant confirmed
        $this->assertEquals('confirmed', $chart['ascendant']['status']);
        $this->assertNotNull($chart['ascendant']['name']);
        $this->assertNotNull($chart['ascendant']['longitude']);
        $this->assertEquals(Rasi::Kanya->getEnglishName(), $chart['ascendant']['name']); // Virgo (Kanya) rising around 14:30 in May Delhi

        // Janma Rashi (Moon sign) and Nakshatra confirmed
        $this->assertEquals('confirmed', $chart['moon_sign']['status']);
        $this->assertNotNull($chart['moon_sign']['name']);
        $this->assertEquals('confirmed', $chart['nakshatra']['status']);
        $this->assertContains($chart['nakshatra']['pada'], [1, 2, 3, 4]);

        // Sun sign confirmed
        $this->assertEquals('confirmed', $chart['sun_sign']['status']);
        $this->assertEquals(Rasi::Vrishabha->getEnglishName(), $chart['sun_sign']['name']); // Sun in Taurus in mid-May sidereal

        // All 9 Navagrahas present in birth positions
        $this->assertCount(9, $chart['planetary_positions']);
        foreach (['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'] as $body) {
            $this->assertArrayHasKey($body, $chart['planetary_positions']);
            $planet = $chart['planetary_positions'][$body];
            $this->assertGreaterThanOrEqual(0.0, $planet['longitude']);
            $this->assertLessThan(360.0, $planet['longitude']);
            $this->assertGreaterThanOrEqual(0, $planet['rashi_index']);
            $this->assertLessThanOrEqual(11, $planet['rashi_index']);
        }

        // Ketu is 180 degrees opposite Rahu
        $rahuLon = $chart['planetary_positions']['rahu']['longitude'];
        $ketuLon = $chart['planetary_positions']['ketu']['longitude'];
        $expectedKetu = fmod($rahuLon + 180.0, 360.0);
        $this->assertEqualsWithDelta($expectedKetu, $ketuLon, 0.05);

        // Transits present for 9 planets with Gochara house 1..12
        $transits = $result['current_transits']['planets'];
        $this->assertCount(9, $transits);
        foreach ($transits as $key => $transit) {
            $this->assertGreaterThanOrEqual(1, $transit['gochara_house']);
            $this->assertLessThanOrEqual(12, $transit['gochara_house']);
            $this->assertEquals('confirmed', $transit['gochara_status']);
            $this->assertIsBool($transit['is_retrograde']);
        }
    }

    public function test_approximate_birth_time_calculation_suppresses_uncertain_ascendant(): void
    {
        /** @var PersonalizedAstrologyService $service */
        $service = app(PersonalizedAstrologyService::class);

        // Approximate birth time in morning window (08:00 to 12:00) spanning 4 hours (~2 zodiac signs)
        $result = $service->computePersonalizedInsights(
            birthDate: '1990-05-15',
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_APPROXIMATE,
            approximateWindow: 'morning',
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata',
            targetDate: '2026-11-03'
        );

        $ascendant = $result['birth_chart_reference']['ascendant'];

        // Lagna must be marked uncertain and suppressed (null position)
        $this->assertEquals('uncertain', $ascendant['status']);
        $this->assertNull($ascendant['longitude']);
        $this->assertNull($ascendant['name']);
        $this->assertStringContainsString('Ascendant suppressed', $ascendant['reason']);
        $this->assertNotEmpty($ascendant['possible_signs']);
    }

    public function test_unknown_birth_time_calculation_strictly_marks_ascendant_unavailable(): void
    {
        /** @var PersonalizedAstrologyService $service */
        $service = app(PersonalizedAstrologyService::class);

        $result = $service->computePersonalizedInsights(
            birthDate: '1995-10-20',
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_UNKNOWN,
            approximateWindow: null,
            latitude: 19.0760,
            longitude: 72.8777,
            timezone: 'Asia/Kolkata',
            targetDate: '2026-11-03'
        );

        $ascendant = $result['birth_chart_reference']['ascendant'];

        $this->assertEquals('unavailable', $ascendant['status']);
        $this->assertNull($ascendant['longitude']);
        $this->assertNull($ascendant['name']);
        $this->assertStringContainsString('birth time is unknown', $ascendant['reason']);

        // Evaluation interval must cover the complete day (00:00:00 to 23:59:59)
        $eval = $result['metadata']['evaluation_interval'];
        $this->assertStringContainsString('00:00:00', $eval['start']);
        $this->assertStringContainsString('23:59:59', $eval['end']);
    }

    public function test_moon_sign_transition_marks_janma_rashi_uncertain(): void
    {
        /** @var PersonalizedAstrologyService $service */
        $service = app(PersonalizedAstrologyService::class);

        // On 2026-11-03, Moon transitions signs during the day
        // Let's test full-day evaluation for unknown birth time
        $result = $service->computePersonalizedInsights(
            birthDate: '2026-11-03',
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_UNKNOWN,
            approximateWindow: null,
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata',
            targetDate: '2026-11-03'
        );

        $moonSign = $result['birth_chart_reference']['moon_sign'];

        // If Moon moved into the next sign on this date, it must be marked uncertain
        if (!$moonSign['is_stable_across_interval']) {
            $this->assertEquals('uncertain', $moonSign['status']);
            $this->assertCount(2, $moonSign['possible_signs']);

            // Transits should reflect uncertain Gochara
            $transits = $result['current_transits']['planets'];
            $sunTransit = $transits['sun'];
            $this->assertEquals('uncertain', $sunTransit['gochara_status']);
            $this->assertCount(2, $sunTransit['gochara_possible_houses']);
        } else {
            // Stable across the entire day
            $this->assertEquals('confirmed', $moonSign['status']);
            $this->assertTrue($moonSign['is_stable_across_interval']);
        }
    }

    public function test_gochara_transits_correctly_calculate_houses_relative_to_natal_moon(): void
    {
        /** @var PersonalizedAstrologyService $service */
        $service = app(PersonalizedAstrologyService::class);

        // Verified profile with natal Moon in Aries (index 0)
        // If transiting planet is in Aries -> 1st house
        // If transiting planet is in Taurus -> 2nd house
        // If transiting planet is in Pisces -> 12th house
        $result = $service->computePersonalizedInsights(
            birthDate: '1990-05-15',
            birthTime: '14:30:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata',
            targetDate: '2026-11-03'
        );

        $natalMoonRashi = $result['birth_chart_reference']['moon_sign']['index'];
        $transits = $result['current_transits']['planets'];

        foreach ($transits as $planetKey => $transit) {
            $transitingRashi = $transit['rashi']['index'];
            $expectedHouse = (($transitingRashi - $natalMoonRashi + 12) % 12) + 1;
            $this->assertEquals($expectedHouse, $transit['gochara_house'], "House calculation mismatch for planet {$planetKey}");
        }
    }

    public function test_end_to_end_asynchronous_calculation_workflow_and_cache_retrieval(): void
    {
        $workerLaunched = false;
        CalculationWorkerLauncher::setLaunchHook(function () use (&$workerLaunched) {
            $workerLaunched = true;
            return true;
        });

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Aditi Sharma',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1992-08-25',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '07:15:00',
            'birth_city' => 'Jaipur',
            'birth_country' => 'India',
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'timezone' => 'Asia/Kolkata',
        ]);

        // 1. Initial request: Cache MISS -> Dispatches job & returns HTTP 202
        $initialResponse = $this->getJson('/api/v1/astrology/personalized-insights/today?date=2026-11-03');

        $initialResponse->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.calculation_type', PersonalizedInsightsCalculationHandler::TYPE)
            ->assertJsonPath('data.status', 'pending');

        $jobId = $initialResponse->json('data.job_id');
        $this->assertNotEmpty($jobId);
        $this->assertTrue($workerLaunched);

        // 2. Calculation status endpoint reports pending
        $statusPending = $this->getJson("/api/v1/calculations/{$jobId}");
        $statusPending->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.ready', false);

        // 3. Worker executes calculation (simulated via artisan worker)
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])
            ->assertSuccessful();

        $job = AstrologyCalculationJob::where('job_id', $jobId)->firstOrFail();
        $this->assertTrue($job->isCompleted());
        $this->assertNotNull($job->result_cache_key);

        // 4. Polling calculation status endpoint now returns completed with result
        $statusCompleted = $this->getJson("/api/v1/calculations/{$jobId}");
        $statusCompleted->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.result.birth_chart_reference.ascendant.status', 'confirmed');

        // 5. Subsequent request to today endpoint returns HTTP 200 Cache HIT
        $cachedResponse = $this->getJson('/api/v1/astrology/personalized-insights/today?date=2026-11-03');
        $cachedResponse->assertStatus(200)
            ->assertHeader('X-Astrology-Cached', 'HIT')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.metadata.ruleset_version', PersonalizedInsightsCalculationHandler::TYPE === 'personalized_insights' ? 'M4B_V1' : '')
            ->assertJsonPath('data.birth_chart_reference.ascendant.status', 'confirmed');
    }

    public function test_cache_namespace_isolation_from_panchang_cache(): void
    {
        /** @var PersonalizedInsightsCalculationHandler $handler */
        $handler = app(PersonalizedInsightsCalculationHandler::class);

        $payload = [
            'profile_id' => 10,
            'date_of_birth' => '1990-05-15',
            'birth_time_precision' => 'verified',
            'birth_time' => '14:30:00',
            'approximate_time_window' => null,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'target_date' => '2026-11-03',
        ];

        $canonicalKey = $handler->computeCanonicalKey($payload);
        $cacheKey = $handler->storeResult($canonicalKey, ['foo' => 'bar'], $payload);

        // Verifies distinct cache namespace
        $this->assertStringStartsWith('astrology:personalized_insights:', $cacheKey);
        $this->assertStringNotContainsString('panchang:daily:', $cacheKey);

        $cached = $handler->getCachedResult($canonicalKey, $payload);
        $this->assertNotNull($cached);
        $this->assertEquals('bar', $cached['foo']);
    }
}
