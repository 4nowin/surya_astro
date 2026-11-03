<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\HoroscopeController;
use App\Models\Admin;
use App\Models\Horoscope;
use Carbon\Carbon;
use Database\Seeders\HoroscopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HoroscopeCmsSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * 1. Running HoroscopeSeeder twice does not duplicate records.
     */
    public function test_seeder_idempotency_does_not_duplicate_records(): void
    {
        $seeder = new HoroscopeSeeder();
        
        // First run
        $seeder->run();
        $firstCount = DB::table('horoscopes')->count();
        $this->assertGreaterThan(0, $firstCount);

        // Second run
        $seeder->run();
        $secondCount = DB::table('horoscopes')->count();

        $this->assertEquals($firstCount, $secondCount, 'Running seeder multiple times must not create duplicate records.');
    }

    /**
     * 2. Existing administrator-authored daily content is not overwritten.
     */
    public function test_seeder_does_not_overwrite_existing_admin_content(): void
    {
        $today = Carbon::today()->toDateString();

        // Administrator writes custom Aries prediction for today
        $adminRecord = Horoscope::create([
            'zodiac_sign' => 'Aries',
            'horoscope_type' => 'daily',
            'zodiac' => 'aries',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'CUSTOM ADMIN PREDICTION: Important breakthrough today.',
            'career' => 'CUSTOM ADMIN CAREER: Big promotion on the horizon.',
            'love' => 'CUSTOM ADMIN LOVE: Mutual understanding flourishes.',
            'money' => 'CUSTOM ADMIN MONEY: Financial gains from investments.',
            'health' => 'CUSTOM ADMIN HEALTH: High vitality and peaceful mind.',
            'travel' => 'CUSTOM ADMIN TRAVEL: Successful business journey.',
            'lucky_number' => '77',
            'lucky_color' => 'Gold',
            'mood' => 'Victorious',
            'start_date' => "{$today} 00:00:00",
            'end_date' => "{$today} 23:59:59",
        ]);

        // Run seeder
        $seeder = new HoroscopeSeeder();
        $seeder->run();

        // Verify admin record was NOT overwritten or deleted
        $fresh = Horoscope::find($adminRecord->id);
        $this->assertNotNull($fresh, 'Admin-authored record must remain in database.');
        $this->assertEquals('CUSTOM ADMIN PREDICTION: Important breakthrough today.', $fresh->context);
        $this->assertEquals('CUSTOM ADMIN CAREER: Big promotion on the horizon.', $fresh->career);
        $this->assertEquals('CUSTOM ADMIN LOVE: Mutual understanding flourishes.', $fresh->love);
        $this->assertEquals('77', $fresh->lucky_number);
        $this->assertEquals('Gold', $fresh->lucky_color);

        // Ensure no duplicate Aries record for this date and language
        $ariesCount = DB::table('horoscopes')
            ->where('horoscope_type', 'daily')
            ->where('zodiac_sign', 'Aries')
            ->where('language', 'en')
            ->whereDate('start_date', $today)
            ->count();
        $this->assertEquals(1, $ariesCount, 'There must be exactly 1 Aries record for today.');
    }

    /**
     * 3. Existing records outside the seeder's date range remain untouched.
     */
    public function test_seeder_leaves_records_outside_range_untouched(): void
    {
        $pastDate = Carbon::today()->subDays(30)->toDateString();
        $futureDate = Carbon::today()->addDays(30)->toDateString();

        $pastRecord = Horoscope::create([
            'zodiac_sign' => 'Taurus',
            'horoscope_type' => 'daily',
            'zodiac' => 'taurus',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'Historical archive prediction from last month.',
            'start_date' => "{$pastDate} 00:00:00",
        ]);

        $futureRecord = Horoscope::create([
            'zodiac_sign' => 'Gemini',
            'horoscope_type' => 'daily',
            'zodiac' => 'gemini',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'Advanced published horoscope for next month.',
            'start_date' => "{$futureDate} 00:00:00",
        ]);

        $seeder = new HoroscopeSeeder();
        $seeder->run();

        $this->assertDatabaseHas('horoscopes', [
            'id' => $pastRecord->id,
            'context' => 'Historical archive prediction from last month.',
        ]);

        $this->assertDatabaseHas('horoscopes', [
            'id' => $futureRecord->id,
            'context' => 'Advanced published horoscope for next month.',
        ]);
    }

    /**
     * 4. Zodiac sign, horoscope type and date are correctly populated in the edit form.
     */
    public function test_edit_form_correctly_populates_sign_type_and_formatted_date(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@astrosetu.test',
            'password' => bcrypt('password'),
        ]);

        $horoscope = Horoscope::create([
            'zodiac_sign' => 'Taurus',
            'horoscope_type' => 'daily',
            'zodiac' => 'taurus',
            'tag' => 'daily',
            'mood' => 'calm',
            'language' => 'en',
            'context' => 'Sample prediction text.',
            'start_date' => '2026-11-03 15:45:00',
            'lucky_number' => '6',
            'lucky_color' => 'white',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('horoscope.edit', $horoscope->id));
        $response->assertStatus(200);

        $html = $response->getContent();

        // 1. Verify Taurus (option 2) is marked selected
        $this->assertMatchesRegularExpression('/<option value="2"\s+selected>/', $html, 'Taurus must have selected attribute.');

        // 2. Verify Daily (option 1) is marked selected
        $this->assertMatchesRegularExpression('/<option value="1"\s+selected>/', $html, 'Daily type must have selected attribute.');

        // 3. Verify Calm mood is marked selected
        $this->assertMatchesRegularExpression('/<option value="calm"\s+selected>/', $html, 'Calm mood must have selected attribute.');

        // 4. Verify start_date input contains Y-m-d format '2026-11-03' without time component
        $this->assertStringContainsString('name="start_date" value="2026-11-03"', $html, 'start_date input must contain Y-m-d formatted date.');
    }

    /**
     * 5. Coverage matrix identifies complete, incomplete and missing records correctly.
     */
    public function test_coverage_matrix_distinguishes_complete_incomplete_and_missing(): void
    {
        $controller = new HoroscopeController();

        // A. Missing record
        $missingStatus = $controller->evaluateRecordStatus(null);
        $this->assertEquals('missing', $missingStatus['status']);
        $this->assertCount(6, $missingStatus['missing_fields']);

        // B. Complete record (all 6 fields filled)
        $completeRecord = new Horoscope([
            'context' => 'Clear daily prediction.',
            'career' => 'Promising career milestones.',
            'love' => 'Harmony and understanding.',
            'money' => 'Sound financial planning.',
            'health' => 'High energy and wellness.',
            'travel' => 'Rewarding short journey.',
        ]);
        $completeRecord->id = 101;
        $completeStatus = $controller->evaluateRecordStatus($completeRecord);
        $this->assertEquals('complete', $completeStatus['status']);
        $this->assertEmpty($completeStatus['missing_fields']);
        $this->assertEquals(101, $completeStatus['id']);

        // C. Incomplete record (money is null, travel is empty string, career is whitespace only)
        $incompleteRecord = new Horoscope([
            'context' => 'Has prediction.',
            'career' => "   \n\t   ", // Whitespace only
            'love' => 'Has love.',
            'money' => null,
            'health' => 'Has health.',
            'travel' => '',
        ]);
        $incompleteRecord->id = 102;
        $incompleteStatus = $controller->evaluateRecordStatus($incompleteRecord);
        $this->assertEquals('incomplete', $incompleteStatus['status']);
        $this->assertContains('career', $incompleteStatus['missing_fields']);
        $this->assertContains('money', $incompleteStatus['missing_fields']);
        $this->assertContains('travel', $incompleteStatus['missing_fields']);
        $this->assertNotContains('context', $incompleteStatus['missing_fields']);
        $this->assertNotContains('love', $incompleteStatus['missing_fields']);
        $this->assertNotContains('health', $incompleteStatus['missing_fields']);
    }

    /**
     * 6. English and Hindi coverage are evaluated independently.
     */
    public function test_english_and_hindi_coverage_evaluated_independently(): void
    {
        $admin = Admin::create([
            'name' => 'Matrix Admin',
            'email' => 'matrix@astrosetu.test',
            'password' => bcrypt('password'),
        ]);

        $targetDate = '2026-11-03';

        // Create Leo in English: fully complete
        Horoscope::create([
            'zodiac_sign' => 'Leo',
            'horoscope_type' => 'daily',
            'zodiac' => 'leo',
            'tag' => 'daily',
            'language' => 'en',
            'context' => 'Leo English prediction.',
            'career' => 'Leo English career.',
            'love' => 'Leo English love.',
            'money' => 'Leo English money.',
            'health' => 'Leo English health.',
            'travel' => 'Leo English travel.',
            'start_date' => "{$targetDate} 00:00:00",
        ]);

        // Create Leo in Hindi: incomplete (missing health and travel)
        Horoscope::create([
            'zodiac_sign' => 'Leo',
            'horoscope_type' => 'daily',
            'zodiac' => 'leo',
            'tag' => 'daily',
            'language' => 'hi',
            'context' => 'सिंह हिंदी भविष्यफल।',
            'career' => 'सिंह हिंदी करियर।',
            'love' => 'सिंह हिंदी प्रेम।',
            'money' => 'सिंह हिंदी वित्त।',
            'health' => null, // Missing
            'travel' => '',   // Missing
            'start_date' => "{$targetDate} 00:00:00",
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('horoscope.index', ['date' => $targetDate]));
        $response->assertStatus(200);

        $signsMatrix = $response->viewData('signsMatrix');
        $this->assertIsArray($signsMatrix);
        $this->assertArrayHasKey('leo', $signsMatrix);

        $leo = $signsMatrix['leo'];
        $this->assertEquals('complete', $leo['en_status'], 'Leo English must be complete.');
        $this->assertEmpty($leo['en_missing']);

        $this->assertEquals('incomplete', $leo['hi_status'], 'Leo Hindi must be incomplete.');
        $this->assertContains('health', $leo['hi_missing']);
        $this->assertContains('travel', $leo['hi_missing']);

        $this->assertFalse($leo['is_complete'], 'Sign must not be marked complete when Hindi is incomplete.');

        // Verify HTML displays the warning badge for Hindi
        $html = $response->getContent();
        $this->assertStringContainsString('EN ✓', $html);
        $this->assertStringContainsString('HI ⚠ (2 missing)', $html);
    }

    /**
     * 7. An existing periodic (weekly/monthly/yearly) record for one sign/language
     * does not prevent other signs or languages from being seeded.
     */
    public function test_existing_periodic_record_does_not_prevent_other_signs_or_languages_from_seeding(): void
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        // 1. Pre-insert an administrator-authored weekly horoscope for Aries in English
        $adminAries = Horoscope::create([
            'zodiac_sign' => 'Aries',
            'horoscope_type' => 'weekly',
            'zodiac' => 'aries',
            'tag' => 'Weekly Horoscope',
            'language' => 'en',
            'context' => 'CUSTOM ADMIN WEEKLY PREDICTION FOR ARIES',
            'career' => 'Aries weekly career focus.',
            'love' => 'Aries weekly relationship balance.',
            'money' => 'Aries weekly financial management.',
            'health' => 'Aries weekly wellness.',
            'travel' => 'Aries weekly travel plans.',
            'lucky_number' => '9',
            'lucky_color' => 'Red',
            'mood' => 'Motivated',
            'start_date' => $weekStart->format('Y-m-d 00:00:00'),
            'end_date' => $weekEnd->format('Y-m-d 23:59:59'),
        ]);

        // 2. Pre-insert an administrator-authored monthly horoscope for Scorpio in Hindi
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $adminScorpio = Horoscope::create([
            'zodiac_sign' => 'Scorpio',
            'horoscope_type' => 'monthly',
            'zodiac' => 'scorpio',
            'tag' => 'मासिक राशिफल',
            'language' => 'hi',
            'context' => 'CUSTOM ADMIN MONTHLY SCORPIO IN HINDI',
            'career' => 'वृश्चिक करियर।',
            'love' => 'वृश्चिक प्रेम।',
            'money' => 'वृश्चिक वित्त।',
            'health' => 'वृश्चिक स्वास्थ्य।',
            'travel' => 'वृश्चिक यात्रा।',
            'lucky_number' => '8',
            'lucky_color' => 'मजेंटा',
            'mood' => 'उत्साही',
            'start_date' => $monthStart->format('Y-m-d 00:00:00'),
            'end_date' => $monthEnd->format('Y-m-d 23:59:59'),
        ]);

        // 3. Run the seeder
        $seeder = new HoroscopeSeeder();
        $seeder->run();

        // 4. Assert Admin records were preserved and unmodified
        $freshAries = Horoscope::find($adminAries->id);
        $this->assertNotNull($freshAries);
        $this->assertEquals('CUSTOM ADMIN WEEKLY PREDICTION FOR ARIES', $freshAries->context);

        $freshScorpio = Horoscope::find($adminScorpio->id);
        $this->assertNotNull($freshScorpio);
        $this->assertEquals('CUSTOM ADMIN MONTHLY SCORPIO IN HINDI', $freshScorpio->context);

        // 5. Assert Aries in Hindi for the same week WAS seeded
        $ariesHindiCount = DB::table('horoscopes')
            ->where('horoscope_type', 'weekly')
            ->where('zodiac_sign', 'Aries')
            ->where('language', 'hi')
            ->whereDate('start_date', $weekStart->toDateString())
            ->count();
        $this->assertEquals(1, $ariesHindiCount, 'Aries weekly in Hindi must be seeded even when English exists.');

        // 6. Assert all 12 signs in both English and Hindi exist for the weekly period (24 total: 1 admin + 23 seeded)
        $weeklyTotal = DB::table('horoscopes')
            ->where('horoscope_type', 'weekly')
            ->whereDate('start_date', $weekStart->toDateString())
            ->count();
        $this->assertEquals(24, $weeklyTotal, 'All 12 signs in both EN and HI (24 total) must exist for current week.');

        // 7. Assert Scorpio in English for the monthly period WAS seeded
        $scorpioEnglishCount = DB::table('horoscopes')
            ->where('horoscope_type', 'monthly')
            ->where('zodiac_sign', 'Scorpio')
            ->where('language', 'en')
            ->whereDate('start_date', $monthStart->toDateString())
            ->count();
        $this->assertEquals(1, $scorpioEnglishCount, 'Scorpio monthly in English must be seeded even when Hindi exists.');

        // 8. Assert all 12 signs in both English and Hindi exist for the monthly period (24 total: 1 admin + 23 seeded)
        $monthlyTotal = DB::table('horoscopes')
            ->where('horoscope_type', 'monthly')
            ->whereDate('start_date', $monthStart->toDateString())
            ->count();
        $this->assertEquals(24, $monthlyTotal, 'All 12 signs in both EN and HI (24 total) must exist for current month.');

        // 9. Assert all 12 signs in both English and Hindi exist for the yearly period (24 total seeded)
        $yearStart = $now->copy()->startOfYear();
        $yearlyTotal = DB::table('horoscopes')
            ->where('horoscope_type', 'yearly')
            ->whereDate('start_date', $yearStart->toDateString())
            ->count();
        $this->assertEquals(24, $yearlyTotal, 'All 12 signs in both EN and HI (24 total) must exist for current year.');

        // 10. Re-running the seeder must remain completely idempotent
        $totalBefore = DB::table('horoscopes')->count();
        $seeder->run();
        $totalAfter = DB::table('horoscopes')->count();
        $this->assertEquals($totalBefore, $totalAfter, 'Re-running seeder must not create any duplicate periodic records.');
    }
}
