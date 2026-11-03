<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DailyInsightRule;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\PersonalizedInsightsCalculationHandler;
use App\Services\Astrology\Interpretation\PersonalizedDailyInterpretationService;
use App\Services\Astrology\PersonalizedAstrologyService;
use Database\Seeders\DailyInsightRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalizedDailyInterpretationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CalculationWorkerLauncher::setLaunchHook(null);
        $this->seed(DailyInsightRulesSeeder::class);
    }

    protected function tearDown(): void
    {
        CalculationWorkerLauncher::setLaunchHook(null);
        parent::tearDown();
    }

    public function test_interpretation_service_generates_all_7_categories_for_verified_profile(): void
    {
        /** @var PersonalizedAstrologyService $astrologyService */
        $astrologyService = app(PersonalizedAstrologyService::class);
        /** @var PersonalizedDailyInterpretationService $interpretationService */
        $interpretationService = app(PersonalizedDailyInterpretationService::class);

        $user = User::factory()->create();
        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Kavita Joshi',
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

        $calcResult = $astrologyService->computePersonalizedInsights(
            birthDate: '1990-05-15',
            birthTime: '14:30:00',
            precision: UserAstrologyProfile::PRECISION_VERIFIED,
            approximateWindow: null,
            latitude: 28.6139,
            longitude: 77.2090,
            timezone: 'Asia/Kolkata',
            targetDate: '2026-11-03'
        );

        $insights = $interpretationService->interpret($calcResult, $profile, 'en');

        // Personalisation must be available
        $this->assertTrue($insights['personalization']['is_personalized']);
        $this->assertEquals('available', $insights['personalization']['status']);

        // All 7 categories must be present
        $this->assertCount(7, $insights['categories']);
        foreach ([
            DailyInsightRule::CATEGORY_OVERALL,
            DailyInsightRule::CATEGORY_CAREER,
            DailyInsightRule::CATEGORY_RELATIONSHIPS,
            DailyInsightRule::CATEGORY_FINANCE,
            DailyInsightRule::CATEGORY_WELLBEING,
            DailyInsightRule::CATEGORY_OPPORTUNITIES_CAUTIONS,
            DailyInsightRule::CATEGORY_GUIDANCE,
        ] as $expectedCat) {
            $this->assertArrayHasKey($expectedCat, $insights['categories']);
            $cat = $insights['categories'][$expectedCat];
            $this->assertNotEmpty($cat['headline']);
            $this->assertNotEmpty($cat['interpretation']);
            $this->assertContains($cat['tone'], ['favourable', 'mixed', 'cautious']);
        }

        // Summary must contain overall tone
        $this->assertContains($insights['summary']['overall_tone'], ['favourable', 'mixed', 'cautious']);
        $this->assertNotEmpty($insights['summary']['primary_influences']);
    }

    public function test_ashtama_chandra_8th_moon_generates_cautious_overall_tone(): void
    {
        /** @var PersonalizedDailyInterpretationService $interpretationService */
        $interpretationService = app(PersonalizedDailyInterpretationService::class);

        $user = User::factory()->create();
        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Suresh Kumar',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1992-01-10',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '10:00:00',
            'birth_city' => 'Jaipur',
            'birth_country' => 'India',
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'timezone' => 'Asia/Kolkata',
        ]);

        // Mock calculation result where transiting Moon is in 8th house from natal Moon
        $calcResult = [
            'metadata' => [
                'ruleset_version' => 'M4B_V1',
                'birth_time_precision' => 'verified',
                'target_date' => '2026-11-03',
            ],
            'birth_chart_reference' => [
                'moon_sign' => [
                    'index' => 10,
                    'name' => 'Aquarius',
                    'vedic_name' => 'Kumbha',
                    'status' => 'confirmed',
                ],
            ],
            'current_transits' => [
                'planets' => [
                    'moon' => [
                        'planet' => 'Moon',
                        'gochara_house' => 8,
                        'gochara_status' => 'confirmed',
                        'is_retrograde' => false,
                        'rashi' => ['name' => 'Virgo', 'vedic_name' => 'Kanya'],
                    ],
                    'sun' => [
                        'planet' => 'Sun',
                        'gochara_house' => 9,
                        'gochara_status' => 'confirmed',
                        'is_retrograde' => false,
                        'rashi' => ['name' => 'Libra', 'vedic_name' => 'Tula'],
                    ],
                    'jupiter' => [
                        'planet' => 'Jupiter',
                        'gochara_house' => 5,
                        'gochara_status' => 'confirmed',
                        'is_retrograde' => false,
                        'rashi' => ['name' => 'Gemini', 'vedic_name' => 'Mithuna'],
                    ],
                ],
            ],
        ];

        $insights = $interpretationService->interpret($calcResult, $profile, 'en');

        $this->assertEquals(DailyInsightRule::TONE_CAUTIOUS, $insights['summary']['overall_tone']);
        $this->assertEquals(DailyInsightRule::TONE_CAUTIOUS, $insights['categories']['overall']['tone']);
        $this->assertStringContainsString('Ashtama Chandra', $insights['categories']['overall']['headline']);
    }

    public function test_unknown_birth_time_with_stable_moon_generates_personalized_moon_insights_with_note(): void
    {
        /** @var PersonalizedDailyInterpretationService $interpretationService */
        $interpretationService = app(PersonalizedDailyInterpretationService::class);

        $user = User::factory()->create();
        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Rohan Mehta',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1995-10-20',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_time' => null,
            'birth_city' => 'Mumbai',
            'birth_country' => 'India',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'timezone' => 'Asia/Kolkata',
        ]);

        $calcResult = [
            'metadata' => [
                'ruleset_version' => 'M4B_V1',
                'birth_time_precision' => 'unknown',
                'target_date' => '2026-11-03',
            ],
            'birth_chart_reference' => [
                'moon_sign' => [
                    'index' => 4,
                    'name' => 'Leo',
                    'vedic_name' => 'Simha',
                    'status' => 'confirmed',
                ],
            ],
            'current_transits' => [
                'planets' => [
                    'moon' => ['planet' => 'Moon', 'gochara_house' => 11, 'gochara_status' => 'confirmed', 'is_retrograde' => false],
                    'jupiter' => ['planet' => 'Jupiter', 'gochara_house' => 11, 'gochara_status' => 'confirmed', 'is_retrograde' => false],
                    'sun' => ['planet' => 'Sun', 'gochara_house' => 3, 'gochara_status' => 'confirmed', 'is_retrograde' => false],
                ],
            ],
        ];

        $insights = $interpretationService->interpret($calcResult, $profile, 'en');

        $this->assertTrue($insights['personalization']['is_personalized']);
        $this->assertEquals('available', $insights['personalization']['status']);
        $this->assertNotNull($insights['personalization']['note']);
        $this->assertStringContainsString('Ascendant-specific factors excluded', $insights['personalization']['note']);
        $this->assertNotEmpty($insights['categories']);
    }

    public function test_unknown_birth_time_with_uncertain_moon_restricts_personalization(): void
    {
        /** @var PersonalizedDailyInterpretationService $interpretationService */
        $interpretationService = app(PersonalizedDailyInterpretationService::class);

        $user = User::factory()->create();
        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Uncertain Moon User',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '2000-01-01',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_time' => null,
            'birth_city' => 'Kolkata',
            'birth_country' => 'India',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'timezone' => 'Asia/Kolkata',
        ]);

        $calcResult = [
            'metadata' => [
                'ruleset_version' => 'M4B_V1',
                'birth_time_precision' => 'unknown',
                'target_date' => '2026-11-03',
            ],
            'birth_chart_reference' => [
                'moon_sign' => [
                    'status' => 'uncertain',
                    'possible_signs' => [
                        ['name' => 'Libra', 'vedic_name' => 'Tula'],
                        ['name' => 'Scorpio', 'vedic_name' => 'Vrischika'],
                    ],
                ],
            ],
            'current_transits' => ['planets' => []],
        ];

        $insights = $interpretationService->interpret($calcResult, $profile, 'en');

        $this->assertFalse($insights['personalization']['is_personalized']);
        $this->assertEquals('uncertain_moon_sign', $insights['personalization']['status']);
        $this->assertStringContainsString('The Moon changed zodiac signs on your birth date', $insights['personalization']['reason']);
        $this->assertEmpty($insights['categories']);
    }

    public function test_bilingual_content_generation_en_vs_hi(): void
    {
        /** @var PersonalizedDailyInterpretationService $interpretationService */
        $interpretationService = app(PersonalizedDailyInterpretationService::class);

        $user = User::factory()->create();
        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Bilingual User',
            'relationship' => 'self',
            'is_default' => true,
            'date_of_birth' => '1990-05-15',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time' => '14:30:00',
            'birth_city' => 'Varanasi',
            'birth_country' => 'India',
            'latitude' => 25.3176,
            'longitude' => 82.9739,
            'timezone' => 'Asia/Kolkata',
        ]);

        $calcResult = [
            'metadata' => ['ruleset_version' => 'M4B_V1', 'birth_time_precision' => 'verified'],
            'birth_chart_reference' => [
                'moon_sign' => ['index' => 3, 'name' => 'Cancer', 'vedic_name' => 'Karka', 'status' => 'confirmed'],
            ],
            'current_transits' => [
                'planets' => [
                    'moon' => ['planet' => 'Moon', 'gochara_house' => 11, 'gochara_status' => 'confirmed', 'is_retrograde' => false],
                    'sun' => ['planet' => 'Sun', 'gochara_house' => 10, 'gochara_status' => 'confirmed', 'is_retrograde' => false],
                    'jupiter' => ['planet' => 'Jupiter', 'gochara_house' => 11, 'gochara_status' => 'confirmed', 'is_retrograde' => false],
                ],
            ],
        ];

        $enInsights = $interpretationService->interpret($calcResult, $profile, 'en');
        $hiInsights = $interpretationService->interpret($calcResult, $profile, 'hi');

        $this->assertEquals('en', $enInsights['locale']);
        $this->assertEquals('hi', $hiInsights['locale']);

        $this->assertEquals('Fulfillment of Aims and Uplifting Camaraderie', $enInsights['categories']['overall']['headline']);
        $this->assertEquals('मनोकामना सिद्धि और मित्रों का सहयोग', $hiInsights['categories']['overall']['headline']);
    }

    public function test_api_today_endpoint_returns_structured_interpretation_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $profile = UserAstrologyProfile::create([
            'user_id' => $user->id,
            'name' => 'Anita Desai',
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

        // Pre-warm calculation cache
        /** @var PersonalizedAstrologyService $astroService */
        $astroService = app(PersonalizedAstrologyService::class);
        $rawCalc = $astroService->calculateForProfile($profile, '2026-11-03');

        /** @var PersonalizedInsightsCalculationHandler $handler */
        $handler = app(PersonalizedInsightsCalculationHandler::class);
        $payload = [
            'profile_id' => $profile->id,
            'user_id' => $user->id,
            'date_of_birth' => '1992-08-25',
            'birth_time_precision' => 'verified',
            'birth_time' => '07:15:00',
            'approximate_time_window' => null,
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'timezone' => 'Asia/Kolkata',
            'target_date' => '2026-11-03',
        ];
        $canonicalKey = $handler->computeCanonicalKey($payload);
        $handler->storeResult($canonicalKey, $rawCalc, $payload);

        // API request with Hindi locale
        $response = $this->getJson('/api/v1/astrology/personalized-insights/today?date=2026-11-03&lang=hi');

        $response->assertStatus(200)
            ->assertHeader('X-Astrology-Cached', 'HIT')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_date', '2026-11-03')
            ->assertJsonPath('data.locale', 'hi')
            ->assertJsonPath('data.personalization.is_personalized', true)
            ->assertJsonPath('data.personalization.status', 'available')
            ->assertJsonStructure([
                'data' => [
                    'target_date',
                    'locale',
                    'profile',
                    'personalization',
                    'summary' => ['overall_tone', 'headline', 'primary_influences'],
                    'categories',
                    'transits_summary',
                    'metadata',
                ],
            ]);
    }

    public function test_admin_can_manage_interpretation_rules_and_toggle_active_status(): void
    {
        $admin = Admin::create([
            'name' => 'Admin User',
            'email' => 'admin_test@astrosetu.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin, 'admin');

        $rule = DailyInsightRule::first();
        $this->assertNotNull($rule);
        $originalStatus = $rule->is_active;

        $response = $this->post(route('daily-insight-rules.toggle-active', $rule));
        $response->assertRedirect();

        $rule->refresh();
        $this->assertEquals(!$originalStatus, $rule->is_active);
    }
}
