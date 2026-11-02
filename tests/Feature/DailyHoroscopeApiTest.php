<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Horoscope;
use App\Services\Horoscope\DailyHoroscopeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DailyHoroscopeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test retrieving all twelve zodiac signs with default today's date.
     */
    public function test_retrieving_all_twelve_zodiac_signs(): void
    {
        $response = $this->getJson('/api/v1/horoscope/daily');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daily horoscope retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'lang',
                    'formatted_date',
                    'total_signs',
                    'selected_sign',
                    'signs' => [
                        '*' => [
                            'id',
                            'sign',
                            'name_en',
                            'name_hi',
                            'vedic_name',
                            'display_name',
                            'symbol',
                            'element',
                            'image',
                            'available',
                        ],
                    ],
                ],
            ]);

        $signs = $response->json('data.signs');
        $this->assertCount(12, $signs);

        // Verify the 12 signs match requirement
        $signKeys = array_column($signs, 'sign');
        $expectedKeys = [
            'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
            'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'
        ];
        $this->assertEquals($expectedKeys, $signKeys);
    }

    /**
     * Test graceful handling of missing content (returns 200 with available: false, no 500 error).
     */
    public function test_graceful_handling_of_missing_content(): void
    {
        $response = $this->getJson('/api/v1/horoscope/daily?date=2026-11-03');

        $response->assertStatus(200);
        $signs = $response->json('data.signs');

        foreach ($signs as $sign) {
            $this->assertFalse($sign['available']);
            $this->assertNull($sign['prediction']);
            $this->assertNull($sign['categories']['career']);
            $this->assertNull($sign['lucky']['color']);
        }
    }

    /**
     * Test retrieving populated daily horoscope content for date and categories.
     */
    public function test_retrieving_populated_daily_horoscope_content(): void
    {
        $date = '2026-11-03';

        Horoscope::create([
            'zodiac_sign' => 'Aries',
            'horoscope_type' => 'daily',
            'zodiac' => 'aries',
            'tag' => 'daily',
            'language' => 'en',
            'context' => '<p>Today brings dynamic energy and leadership opportunities.</p>',
            'love' => 'Harmony with your partner.',
            'career' => 'Promising news on business front.',
            'money' => 'Careful investments will yield dividends.',
            'health' => 'High vitality throughout the day.',
            'travel' => 'Short trip favored.',
            'lucky_number' => '9',
            'lucky_color' => 'Red',
            'lucky_time' => '10:30',
            'mood' => 'Energetic',
            'start_date' => $date,
        ]);

        $response = $this->getJson("/api/v1/horoscope/daily?date={$date}&sign=aries&lang=en");

        $response->assertStatus(200);
        $selected = $response->json('data.selected_sign');

        $this->assertNotNull($selected);
        $this->assertTrue($selected['available']);
        $this->assertEquals('aries', $selected['sign']);
        $this->assertEquals('Aries', $selected['name_en']);
        $this->assertEquals('Mesh', $selected['vedic_name']);
        $this->assertEquals('मेष', $selected['name_hi']);
        $this->assertEquals('Today brings dynamic energy and leadership opportunities.', $selected['prediction']);
        $this->assertEquals('Promising news on business front.', $selected['categories']['career']);
        $this->assertEquals('Careful investments will yield dividends.', $selected['categories']['finance']);
        $this->assertEquals('Harmony with your partner.', $selected['categories']['love']);
        $this->assertEquals('High vitality throughout the day.', $selected['categories']['health']);
        $this->assertEquals('Red', $selected['lucky']['color']);
        $this->assertEquals('9', $selected['lucky']['number']);
    }

    /**
     * Test retrieving an individual sign via path parameter.
     */
    public function test_retrieving_individual_sign_via_path_parameter(): void
    {
        $date = '2026-11-03';

        Horoscope::create([
            'zodiac_sign' => 'Leo',
            'horoscope_type' => 'daily',
            'zodiac' => 'leo',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'Great confidence today for Leos.',
            'start_date' => $date,
            'lucky_number' => '1',
            'lucky_color' => 'Gold',
            'mood' => 'Confident',
        ]);

        $response = $this->getJson("/api/v1/horoscope/daily/leo?date={$date}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'selected_sign' => [
                        'sign' => 'leo',
                        'name_en' => 'Leo',
                        'vedic_name' => 'Simha',
                        'name_hi' => 'सिंह',
                        'available' => true,
                    ],
                ],
            ]);
    }

    /**
     * Test validation error for invalid zodiac sign.
     */
    public function test_validation_error_for_invalid_zodiac_sign(): void
    {
        // Via query parameter
        $responseQuery = $this->getJson('/api/v1/horoscope/daily?sign=dragon');
        $responseQuery->assertStatus(422)
            ->assertJsonValidationErrors(['sign']);

        // Via path parameter
        $responsePath = $this->getJson('/api/v1/horoscope/daily/invalid_sign');
        $responsePath->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test validation error for invalid date format.
     */
    public function test_validation_error_for_invalid_date_format(): void
    {
        $response = $this->getJson('/api/v1/horoscope/daily?date=invalid-date');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /**
     * Test bilingual content and fallback behavior.
     */
    public function test_bilingual_content_and_fallback_behavior(): void
    {
        $date = '2026-11-03';

        // 1. Create English content for Taurus
        Horoscope::create([
            'zodiac_sign' => 'Taurus',
            'horoscope_type' => 'daily',
            'zodiac' => 'taurus',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'English prediction for Taurus.',
            'start_date' => $date,
            'lucky_number' => '6',
            'lucky_color' => 'Green',
            'mood' => 'Calm',
        ]);

        // 2. Create Hindi content for Taurus
        Horoscope::create([
            'zodiac_sign' => 'Taurus',
            'horoscope_type' => 'daily',
            'zodiac' => 'taurus',
            'tag' => 'daily',
            'language' => 'hi',
            'context' => 'वृषभ राशि के लिए आज का हिंदी राशिफल।',
            'start_date' => $date,
            'lucky_number' => '6',
            'lucky_color' => 'हरा',
            'mood' => 'शांत',
        ]);

        // Request in English
        $resEn = $this->getJson("/api/v1/horoscope/daily/taurus?date={$date}&lang=en");
        $resEn->assertStatus(200);
        $this->assertEquals('English prediction for Taurus.', $resEn->json('data.selected_sign.prediction'));
        $this->assertEquals('en', $resEn->json('data.selected_sign.content_language'));

        // Request in Hindi
        $resHi = $this->getJson("/api/v1/horoscope/daily/taurus?date={$date}&lang=hi");
        $resHi->assertStatus(200);
        $this->assertEquals('वृषभ राशि के लिए आज का हिंदी राशिफल।', $resHi->json('data.selected_sign.prediction'));
        $this->assertEquals('hi', $resHi->json('data.selected_sign.content_language'));

        // Fallback test: Gemini only has English content, but user requests Hindi
        Horoscope::create([
            'zodiac_sign' => 'Gemini',
            'horoscope_type' => 'daily',
            'zodiac' => 'gemini',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'English only prediction for Gemini.',
            'start_date' => $date,
            'lucky_number' => '5',
            'lucky_color' => 'Yellow',
            'mood' => 'Curious',
        ]);

        $resFallback = $this->getJson("/api/v1/horoscope/daily/gemini?date={$date}&lang=hi");
        $resFallback->assertStatus(200);
        $this->assertTrue($resFallback->json('data.selected_sign.available'));
        $this->assertEquals('English only prediction for Gemini.', $resFallback->json('data.selected_sign.prediction'));
        $this->assertEquals('en', $resFallback->json('data.selected_sign.content_language'));
    }

    /**
     * Test cache invalidation when administrator creates or updates content.
     */
    public function test_cache_invalidation_on_cms_changes(): void
    {
        $date = '2026-11-03';

        // 1. Initial request with no content -> returns available: false
        $res1 = $this->getJson("/api/v1/horoscope/daily/scorpio?date={$date}");
        $res1->assertStatus(200);
        $this->assertFalse($res1->json('data.selected_sign.available'));

        // 2. Administrator publishes new content
        Horoscope::create([
            'zodiac_sign' => 'Scorpio',
            'horoscope_type' => 'daily',
            'zodiac' => 'scorpio',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'Intuition is very sharp today.',
            'start_date' => $date,
            'lucky_number' => '8',
            'lucky_color' => 'Maroon',
            'mood' => 'Intense',
        ]);
        DailyHoroscopeService::invalidateCache();

        // 3. Next request should immediately pick up the new content
        $res2 = $this->getJson("/api/v1/horoscope/daily/scorpio?date={$date}");
        $res2->assertStatus(200);
        $this->assertTrue($res2->json('data.selected_sign.available'));
        $this->assertEquals('Intuition is very sharp today.', $res2->json('data.selected_sign.prediction'));
    }
}
