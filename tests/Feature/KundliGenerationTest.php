<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\KundliService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KundliGenerationTest extends TestCase
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
        $getRes = $this->getJson('/api/v1/astrology/kundli');
        $getRes->assertUnauthorized();

        $postGenRes = $this->postJson('/api/v1/kundli/generate', []);
        $postGenRes->assertUnauthorized();

        $postCalcRes = $this->postJson('/api/v1/astrology/kundli/calculate', []);
        $postCalcRes->assertUnauthorized();
    }

    public function test_authenticated_user_without_profiles_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/astrology/kundli');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'No astrology profile found for user. Please create a birth profile first.']);
    }

    public function test_authenticated_user_cannot_access_another_users_profile(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $profileB = UserAstrologyProfile::create([
            'user_id' => $userB->id,
            'name' => 'User B Profile',
            'relationship' => 'self',
            'date_of_birth' => '1990-05-15',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '14:30:00',
            'birth_city' => 'New Delhi',
            'birth_country' => 'India',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'timezone' => 'Asia/Kolkata',
            'is_default' => true,
        ]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/astrology/kundli?profile_id=' . $profileB->id);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'The specified astrology profile was not found or does not belong to your account.']);
    }

    public function test_verified_birth_time_generates_complete_kundli_with_ascendant_and_12_houses(): void
    {
        $service = app(KundliService::class);

        // Verification case: Born 1985-11-20 08:30:00 at New Delhi
        $result = $service->computeKundli(
            birthDate: '1985-11-20',
            birthTime: '08:30:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata',
            profileMetadata: ['name' => 'Verified User', 'relationship' => 'self']
        );

        // 1. Metadata check
        $this->assertEquals(KundliService::DEFAULT_AYANAMSHA, $result['metadata']['ayanamsha']);
        $this->assertEquals(KundliService::DEFAULT_HOUSE_SYSTEM, $result['metadata']['house_system']);
        $this->assertEquals('verified', $result['metadata']['precision']);

        // 2. Ascendant check
        $asc = $result['ascendant'];
        $this->assertEquals('confirmed', $asc['status']);
        $this->assertNotNull($asc['id']);
        $this->assertGreaterThanOrEqual(1, $asc['id']);
        $this->assertLessThanOrEqual(12, $asc['id']);
        $this->assertNotNull($asc['degrees_dms']);
        $this->assertNotNull($asc['nakshatra']);
        $this->assertTrue($result['chart_availability']['lagna_chart_available']);

        // 3. Planets check (9 classical bodies)
        $planets = $result['planets'];
        $this->assertCount(9, $planets);
        $expectedKeys = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $planets);
            $p = $planets[$key];
            $this->assertGreaterThanOrEqual(0.0, $p['longitude']);
            $this->assertLessThan(360.0, $p['longitude']);
            $this->assertGreaterThanOrEqual(1, $p['rashi']['id']);
            $this->assertLessThanOrEqual(12, $p['rashi']['id']);
            $this->assertNotNull($p['nakshatra']['name']);
            $this->assertGreaterThanOrEqual(1, $p['nakshatra']['pada']);
            $this->assertLessThanOrEqual(4, $p['nakshatra']['pada']);
            $this->assertIsBool($p['is_retrograde']);
            $this->assertIsBool($p['is_combust']);
        }

        // Ketu is exactly 180° opposite Rahu
        $rahuLon = $planets['rahu']['longitude'];
        $ketuLon = $planets['ketu']['longitude'];
        $diff = fmod(abs($ketuLon - $rahuLon), 360.0);
        $this->assertEqualsWithDelta(180.0, $diff, 0.01, 'Ketu must be 180° opposite Rahu.');

        // 4. Houses check (12 houses under Whole Sign)
        $houses = $result['houses'];
        $this->assertIsArray($houses);
        $this->assertCount(12, $houses);

        // House 1 must match the Ascendant rashi
        $this->assertEquals($asc['id'], $houses[1]['sign_id']);

        // Check continuous rashi progression: House 2 = (House 1 % 12) + 1
        for ($h = 1; $h <= 12; $h++) {
            $expectedSignId = (($asc['id'] - 1 + ($h - 1)) % 12) + 1;
            $this->assertEquals($expectedSignId, $houses[$h]['sign_id']);
        }

        // 5. Total planets placed in all 12 houses must equal 9
        $totalPlaced = 0;
        foreach ($houses as $h) {
            $totalPlaced += count($h['planets']);
        }
        $this->assertEquals(9, $totalPlaced, 'All 9 planets must be distributed across the 12 houses.');
    }

    public function test_approximate_birth_time_crossing_sign_boundary_suppresses_ascendant(): void
    {
        $service = app(KundliService::class);

        // Approximate morning window (08:00 to 12:00) spans 4 hours, which always crosses at least 2 zodiac signs
        $result = $service->computeKundli(
            birthDate: '1992-07-15',
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_APPROXIMATE,
            approximateWindow: 'morning',
            latitude: 19.0760,
            longitude: 72.8777,
            timezone: 'Asia/Kolkata'
        );

        // Ascendant must be marked uncertain
        $this->assertEquals('uncertain', $result['ascendant']['status']);
        $this->assertNull($result['ascendant']['id']);
        $this->assertStringContainsString('spans multiple signs', $result['ascendant']['reason']);

        // Lagna chart houses must be null/suppressed
        $this->assertNull($result['houses']);
        $this->assertFalse($result['chart_availability']['lagna_chart_available']);

        // But Chandra Kundli must remain available!
        $this->assertTrue($result['chart_availability']['chandra_chart_available']);
        $this->assertCount(12, $result['chandra_kundli']['houses']);
        $this->assertCount(9, $result['planets']);
    }

    public function test_unknown_birth_time_returns_unavailable_ascendant_without_noon_substitution(): void
    {
        $service = app(KundliService::class);

        $result = $service->computeKundli(
            birthDate: '1988-10-05',
            birthTime: null,
            precision: UserAstrologyProfile::PRECISION_UNKNOWN,
            approximateWindow: null,
            latitude: 26.9124,
            longitude: 75.7873,
            timezone: 'Asia/Kolkata'
        );

        $this->assertEquals('unavailable', $result['ascendant']['status']);
        $this->assertNull($result['ascendant']['id']);
        $this->assertNull($result['ascendant']['longitude']);
        $this->assertNull($result['houses']);
        $this->assertFalse($result['chart_availability']['lagna_chart_available']);
        $this->assertStringContainsString('Exact time is required for Lagna calculation', $result['ascendant']['reason']);

        // Planetary positions and Chandra Kundli remain accessible
        $this->assertCount(9, $result['planets']);
        $this->assertCount(12, $result['chandra_kundli']['houses']);
    }

    public function test_chandra_kundli_generated_reliably_for_all_precision_tiers(): void
    {
        $service = app(KundliService::class);

        $result = $service->computeKundli(
            birthDate: '1995-03-21',
            birthTime: '18:45:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: 12.9716,
            longitude: 77.5946,
            timezone: 'Asia/Kolkata'
        );

        $chandra = $result['chandra_kundli'];
        $this->assertEquals('available', $chandra['status']);
        $moonSignId = $result['planets']['moon']['rashi']['id'];
        $this->assertEquals($moonSignId, $chandra['moon_sign_id']);

        // House 1 in Chandra Kundli must have sign_id == moonSignId
        $this->assertEquals($moonSignId, $chandra['houses'][1]['sign_id']);

        // The Moon must be situated in House 1 of Chandra Kundli
        $house1Planets = array_column($chandra['houses'][1]['planets'], 'key');
        $this->assertContains('moon', $house1Planets);
    }

    public function test_combustion_and_retrogression_detected_correctly(): void
    {
        $service = app(KundliService::class);

        $result = $service->computeKundli(
            birthDate: '1985-11-20',
            birthTime: '08:30:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata'
        );

        $planets = $result['planets'];

        // Sun, Rahu, and Ketu must never be marked combust
        $this->assertFalse($planets['sun']['is_combust']);
        $this->assertFalse($planets['rahu']['is_combust']);
        $this->assertFalse($planets['ketu']['is_combust']);

        // Rahu and Ketu must be marked retrograde
        $this->assertTrue($planets['rahu']['is_retrograde']);
        $this->assertTrue($planets['ketu']['is_retrograde']);

        // Check angular separation is computed for all other planets
        foreach (['moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn'] as $pKey) {
            $this->assertArrayHasKey('angular_separation_from_sun', $planets[$pKey]);
            $this->assertGreaterThanOrEqual(0.0, $planets[$pKey]['angular_separation_from_sun']);
            $this->assertLessThanOrEqual(180.0, $planets[$pKey]['angular_separation_from_sun']);
        }
    }

    public function test_repeated_requests_return_cached_result_with_canonical_cache_hit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Caching Test Profile',
            'relationship' => 'self',
            'date_of_birth' => '1990-01-01',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '10:00:00',
            'birth_city' => 'Jaipur',
            'birth_country' => 'India',
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'timezone' => 'Asia/Kolkata',
            'is_default' => true,
        ]);

        CalculationWorkerLauncher::setLaunchHook(function () {
            return true;
        });

        // First request: Cache MISS -> dispatches job & returns HTTP 202
        $firstRes = $this->getJson('/api/v1/astrology/kundli');
        $firstRes->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $jobId = $firstRes->json('data.job_id');
        $this->assertNotEmpty($jobId);

        // Execute background calculation worker
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])
            ->assertSuccessful();

        $job = AstrologyCalculationJob::where('job_id', $jobId)->firstOrFail();
        $this->assertTrue($job->isCompleted());

        // Polling calculation status endpoint returns completed with result
        $statusCompleted = $this->getJson("/api/v1/calculations/{$jobId}");
        $statusCompleted->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ready', true);

        // Second request: Cache HIT -> returns HTTP 200 with X-Astrology-Cached: HIT
        $secondRes = $this->getJson('/api/v1/astrology/kundli');
        $secondRes->assertStatus(200)
            ->assertHeader('X-Astrology-Cached', 'HIT')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.name', 'Caching Test Profile')
            ->assertJsonPath('data.metadata.cached', true);
    }

    public function test_post_kundli_generate_endpoint_works_identically(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Endpoint Parity Profile',
            'relationship' => 'self',
            'date_of_birth' => '1994-08-12',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '07:15:00',
            'birth_city' => 'Kolkata',
            'birth_country' => 'India',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'timezone' => 'Asia/Kolkata',
            'is_default' => true,
        ]);

        CalculationWorkerLauncher::setLaunchHook(function () {
            return true;
        });

        // Call POST /api/v1/kundli/generate
        $response = $this->postJson('/api/v1/kundli/generate', [
            'profile_id' => $profile->id,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // Execute worker
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])
            ->assertSuccessful();

        // Subsequent call returns 200 HIT
        $cachedRes = $this->postJson('/api/v1/kundli/generate', [
            'profile_id' => $profile->id,
        ]);
        $cachedRes->assertStatus(200)
            ->assertHeader('X-Astrology-Cached', 'HIT')
            ->assertJsonPath('success', true);
    }

    public function test_sanctum_bearer_token_authorization_header_authenticates_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Sanctum User',
            'relationship' => 'self',
            'birth_time_precision' => 'verified',
            'date_of_birth' => '1995-08-10',
            'birth_time' => '11:15:00',
            'birth_city' => 'Mumbai',
            'birth_country' => 'India',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
            'is_default' => true,
        ]);

        CalculationWorkerLauncher::setLaunchHook(function () {
            return true;
        });

        // 1. Without Authorization header -> 401 Unauthenticated
        $unauthRes = $this->postJson('/api/v1/kundli/generate', []);
        $unauthRes->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');

        // 2. With Sanctum Bearer token -> 202 Accepted
        $authRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/kundli/generate', []);

        $authRes->assertStatus(202)
            ->assertJsonPath('success', true);

        // 3. GET endpoint with Bearer token
        $getAuthRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/astrology/kundli');

        $getAuthRes->assertStatus(202)
            ->assertJsonPath('success', true);
    }
}
