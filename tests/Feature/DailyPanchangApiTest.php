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
     * Test public guest access to daily panchang returns 200 with valid structure.
     */
    public function test_guest_can_access_daily_panchang(): void
    {
        $response = $this->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');

        $response->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'MISS')
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
                        'abhijit' => ['start', 'end', 'is_available'],
                        'brahma_muhurta' => ['start', 'end', 'is_available'],
                        'amrit_kaal' => ['start', 'end', 'is_available'],
                    ],
                    'inauspicious_timings' => [
                        'rahu_kaal' => ['start', 'end'],
                        'yamaganda' => ['start', 'end'],
                        'gulika_kaal' => ['start', 'end'],
                        'durmuhurta',
                        'varjyam' => ['start', 'end', 'is_available'],
                    ],
                    'calendar' => [
                        'vikram_samvat',
                        'saka_samvat',
                        'samvatsara',
                        'ayana',
                        'ritu',
                        'lunar_month_amanta',
                        'lunar_month_purnimanta',
                        'paksha',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertFalse($data['metadata']['cached']);
        $this->assertEquals('2026-11-02', $data['metadata']['date']);
        $this->assertEquals(28.6139, $data['metadata']['latitude']);
        $this->assertEquals(77.209, $data['metadata']['longitude']);
        $this->assertEquals('Asia/Kolkata', $data['metadata']['timezone']);

        // Verify five limbs
        $this->assertNotEmpty($data['panchanga']['tithi']['name']);
        $this->assertEquals(1, $data['panchanga']['vara']['index']); // Monday
        $this->assertEquals('Monday', $data['panchanga']['vara']['name']);
        $this->assertEquals('Somavara', $data['panchanga']['vara']['sanskrit_name']);
        $this->assertNotEmpty($data['panchanga']['nakshatra']['name']);
        $this->assertNotEmpty($data['panchanga']['yoga']['name']);
        $this->assertNotEmpty($data['panchanga']['karana']['name']);

        // Verify Sun & Moon timings
        $this->assertNotEmpty($data['sun_moon']['sunrise']);
        $this->assertNotEmpty($data['sun_moon']['sunset']);

        // Verify Auspicious & Inauspicious
        $this->assertNotEmpty($data['auspicious_timings']['brahma_muhurta']['start']);
        $this->assertNotEmpty($data['inauspicious_timings']['rahu_kaal']['start']);
        $this->assertNotEmpty($data['inauspicious_timings']['rahu_kaal']['end']);
    }

    /**
     * Test authenticated user access to daily panchang returns 200.
     */
    public function test_authenticated_user_can_access_daily_panchang(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');

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
     * Test deterministic caching: 1st call is a miss, 2nd call is a hit.
     */
    public function test_daily_panchang_is_deterministically_cached(): void
    {
        $url = '/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata';

        // 1st request -> Cache MISS
        $response1 = $this->getJson($url);
        $response1->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'MISS');
        $this->assertFalse($response1->json('data.metadata.cached'));

        $cacheKey = $response1->json('data.metadata.cache_key');
        $this->assertNotEmpty($cacheKey);
        $this->assertTrue(Cache::has($cacheKey));

        // 2nd request -> Cache HIT
        $response2 = $this->getJson($url);
        $response2->assertStatus(200)
            ->assertHeader('X-Panchang-Cached', 'HIT');
        $this->assertTrue($response2->json('data.metadata.cached'));
        $this->assertEquals($cacheKey, $response2->json('data.metadata.cache_key'));
        $this->assertEquals(
            $response1->json('data.panchanga.tithi.name'),
            $response2->json('data.panchanga.tithi.name')
        );
    }

    /**
     * Test location and timezone isolation: different parameters generate different cache keys.
     */
    public function test_location_and_timezone_isolation(): void
    {
        // Delhi, India
        $responseDelhi = $this->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=28.6139&lon=77.2090&tz=Asia/Kolkata');
        $responseDelhi->assertStatus(200);

        // Tokyo, Japan
        $responseTokyo = $this->getJson('/api/v1/panchang/daily?date=2026-11-02&lat=35.6762&lon=139.6503&tz=Asia/Tokyo');
        $responseTokyo->assertStatus(200);

        $keyDelhi = $responseDelhi->json('data.metadata.cache_key');
        $keyTokyo = $responseTokyo->json('data.metadata.cache_key');

        $this->assertNotEquals($keyDelhi, $keyTokyo);
        // Sunrise in Tokyo happens hours earlier than Delhi in local times
        $this->assertNotEquals(
            $responseDelhi->json('data.sun_moon.sunrise'),
            $responseTokyo->json('data.sun_moon.sunrise')
        );
    }
}
