<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DailyPanchangApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test public guest access to daily panchang returns 202 on initial request, and 200 once calculated.
     */
    public function test_guest_can_access_daily_panchang(): void
    {
        $url = '/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';

        // 1st request triggers background job -> returns 202
        $response1 = $this->getJson($url);
        $response1->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // Worker processes the job
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])->assertSuccessful();

        // 2nd request returns 200 with complete Panchang structure
        $response = $this->getJson($url);

        $response->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'HIT')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'metadata' => [
                        'date',
                        'latitude',
                        'longitude',
                        'timezone',
                        'elevation_m',
                        'cached',
                        'cache_key',
                        'calculation_ms',
                    ],
                    'sun_moon' => [
                        'sunrise',
                        'sunrise_iso',
                        'sunset',
                        'sunset_iso',
                        'moonrise',
                        'moonrise_iso',
                        'moonset',
                        'moonset_iso',
                    ],
                    'panchanga' => [
                        'tithi' => [
                            'index',
                            'name',
                            'paksha',
                            'paksha_name',
                            'sunrise_tithi',
                            'current_tithi',
                            'start',
                            'end',
                        ],
                        'vara' => [
                            'index',
                            'name',
                            'sanskrit_name',
                            'ruling_planet',
                        ],
                        'nakshatra' => [
                            'index',
                            'name',
                            'pada',
                            'lord',
                            'end',
                        ],
                        'yoga' => [
                            'index',
                            'name',
                            'end',
                        ],
                        'karana' => [
                            'index',
                            'name',
                            'end',
                        ],
                    ],
                    'auspicious_timings' => [
                        'abhijit' => ['start', 'end', 'start_iso', 'end_iso', 'is_available'],
                        'brahma_muhurta' => ['start', 'end', 'start_iso', 'end_iso', 'is_available'],
                        'amrit_kaal' => ['start', 'end', 'start_iso', 'end_iso', 'is_available'],
                    ],
                    'inauspicious_timings' => [
                        'rahu_kaal' => ['start', 'end', 'start_iso', 'end_iso'],
                        'yamaganda' => ['start', 'end', 'start_iso', 'end_iso'],
                        'gulika_kaal' => ['start', 'end', 'start_iso', 'end_iso'],
                        'durmuhurta',
                        'varjyam' => ['start', 'end', 'start_iso', 'end_iso', 'is_available'],
                    ],
                    'calendar' => [
                        'vikram_samvat',
                        'saka_samvat',
                        'gujarati_samvat',
                        'samvatsara',
                        'ayana',
                        'ritu',
                        'lunar_month_amanta',
                        'lunar_month_purnimanta',
                        'paksha',
                        'is_adhika',
                    ],
                ],
            ]);
    }

    /**
     * Test authenticated user access to daily panchang returns 200 after calculation.
     */
    public function test_authenticated_user_can_access_daily_panchang(): void
    {
        $user = User::factory()->create();
        $url = '/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';

        // 1st request triggers job
        $this->actingAs($user, 'sanctum')->getJson($url)->assertStatus(202);

        // Process job
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])->assertSuccessful();

        $response = $this->actingAs($user, 'sanctum')->getJson($url);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test validation handles missing or invalid query parameters.
     */
    public function test_validation_errors_for_invalid_parameters(): void
    {
        // Missing all parameters
        $this->getJson('/api/v1/panchang/daily')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'lat', 'lon', 'tz']);

        // Invalid date format
        $this->getJson('/api/v1/panchang/daily?date=02-11-2026&lat=28.61&lon=77.20&tz=Asia/Kolkata')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);

        // Out of range coordinates
        $this->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=95.00&lon=77.20&tz=Asia/Kolkata')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);

        $this->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=28.61&lon=-195.00&tz=Asia/Kolkata')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lon']);

        // Invalid timezone
        $this->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=28.61&lon=77.20&tz=Invalid/Timezone')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tz']);
    }

    /**
     * Test deterministic caching: 1st call is a 202, worker processes, subsequent calls return 200 HIT.
     */
    public function test_daily_panchang_is_deterministically_cached(): void
    {
        $url = '/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';

        // 1st request -> Triggers 202
        $response1 = $this->getJson($url);
        $response1->assertStatus(202);

        // Worker processes the job
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 1])->assertSuccessful();

        // 2nd request -> Cache HIT (200)
        $response2 = $this->getJson($url);
        $response2->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'HIT');
        $this->assertTrue($response2->json('data.metadata.cached'));

        $cacheKey = $response2->json('data.metadata.cache_key');
        $this->assertNotEmpty($cacheKey);
        $this->assertTrue(Cache::has($cacheKey));

        // 3rd request -> Same cache HIT
        $response3 = $this->getJson($url);
        $response3->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'HIT');
        $this->assertEquals($cacheKey, $response3->json('data.metadata.cache_key'));
        $this->assertEquals(
            $response2->json('data.panchanga.tithi.name'),
            $response3->json('data.panchanga.tithi.name')
        );
    }

    /**
     * Test location and timezone isolation: different parameters generate different cache keys.
     */
    public function test_location_and_timezone_isolation(): void
    {
        $urlDelhi = '/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';
        $urlTokyo = '/api/v1/panchang/daily?date=2026-11-02&lat=35.6762&lon=139.6503&tz=Asia/Tokyo';

        // Trigger both
        $this->getJson($urlDelhi)->assertStatus(202);
        $this->getJson($urlTokyo)->assertStatus(202);

        // Process both
        $this->artisan('astro:calculate-worker', ['--max-jobs' => 2])->assertSuccessful();

        // Fetch completed
        $responseDelhi = $this->getJson($urlDelhi);
        $responseTokyo = $this->getJson($urlTokyo);

        $responseDelhi->assertStatus(200);
        $responseTokyo->assertStatus(200);

        $keyDelhi = $responseDelhi->json('data.metadata.cache_key');
        $keyTokyo = $responseTokyo->json('data.metadata.cache_key');

        $this->assertNotEquals($keyDelhi, $keyTokyo);
        $this->assertNotEquals(
            $responseDelhi->json('data.sun_moon.sunrise'),
            $responseTokyo->json('data.sun_moon.sunrise')
        );
    }
}
