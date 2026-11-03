<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Horoscope;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\Handlers\PersonalizedInsightsCalculationHandler;
use App\Services\Astrology\Interpretation\PersonalizedDailyInterpretationService;
use App\Services\FirebaseNotificationService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DispatchDailyNotificationsCommand extends Command
{
    /**
     * The console command signature.
     * Single daily cron batch architecture (targeted for 07:00 AM IST).
     */
    protected $signature = 'notifications:dispatch
                            {--date= : Override target local date YYYY-MM-DD}
                            {--user= : Target a specific user ID for testing}
                            {--force : Force delivery bypassing already-sent check}';

    protected $description = 'Dispatch consolidated morning daily briefing to active user devices once daily with idempotency.';

    protected const ZODIAC_EN = [
        1 => 'Aries', 2 => 'Taurus', 3 => 'Gemini', 4 => 'Cancer',
        5 => 'Leo', 6 => 'Virgo', 7 => 'Libra', 8 => 'Scorpio',
        9 => 'Sagittarius', 10 => 'Capricorn', 11 => 'Aquarius', 12 => 'Pisces',
    ];

    protected const ZODIAC_HI = [
        1 => 'मेष', 2 => 'वृषभ', 3 => 'मिथुन', 4 => 'कर्क',
        5 => 'सिंह', 6 => 'कन्या', 7 => 'तुला', 8 => 'वृश्चिक',
        9 => 'धनु', 10 => 'मकर', 11 => 'कुंभ', 12 => 'मीन',
    ];

    public function __construct(
        protected FirebaseNotificationService $fcmService,
        protected DailyPanchangService $panchangService,
        protected CalculationJobManager $jobManager,
        protected PersonalizedDailyInterpretationService $interpretationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting single daily notification dispatch batch...');

        // 1. Acquire atomic execution lock to prevent overlapping runs
        $lock = Cache::lock('notifications_dispatch_single_batch_lock', 900);
        if (!$lock->get()) {
            $this->warn('A daily notification dispatch batch is already in progress. Exiting safely.');
            return self::SUCCESS;
        }

        try {
            return $this->processBatch();
        } finally {
            $lock->release();
        }
    }

    protected function processBatch(): int
    {
        $validTimezones = DateTimeZone::listIdentifiers();

        $query = UserNotificationPreference::query()
            ->where('master_enabled', true);

        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        }

        $totalSent = 0;
        $totalFailed = 0;
        $totalSkipped = 0;
        $totalSuppressed = 0;

        // Process users in manageable chunks to respect Hostinger shared memory
        $query->with([
            'user.devices' => function ($q) {
                $q->where('is_active', true);
            },
            'user.astrologyProfiles'
        ])->chunkById(100, function ($preferences) use (
            $validTimezones,
            &$totalSent,
            &$totalFailed,
            &$totalSkipped,
            &$totalSuppressed
        ) {
            foreach ($preferences as $pref) {
                /** @var User|null $user */
                $user = $pref->user;
                if (!$user || $user->devices->isEmpty()) {
                    continue;
                }

                $activeDevices = $user->devices;
                $userLang = in_array($user->language, ['en', 'hi'], true) ? $user->language : 'en';

                // Determine user's local calendar date using their stored IANA timezone
                $tz = in_array($pref->timezone, $validTimezones, true) ? $pref->timezone : 'Asia/Kolkata';
                $userLocalDate = $this->option('date') ?: CarbonImmutable::now($tz)->toDateString();

                // Build a single consolidated morning briefing
                $briefing = $this->buildConsolidatedBriefing($pref, $user, $userLocalDate, $userLang);

                if ($briefing === null) {
                    $totalSuppressed++;
                    continue;
                }

                // Deliver to all active devices of the user with independent per-device idempotency
                foreach ($activeDevices as $device) {
                    $existing = NotificationDelivery::where('user_device_id', $device->id)
                        ->where('notification_type', 'daily_briefing')
                        ->whereDate('scheduled_date', $userLocalDate)
                        ->first();

                    if ($existing && $existing->status === 'sent' && !$this->option('force')) {
                        $totalSkipped++;
                        continue;
                    }

                    $delivery = $existing ?: new NotificationDelivery([
                        'user_id' => $user->id,
                        'user_device_id' => $device->id,
                        'notification_type' => 'daily_briefing',
                        'scheduled_date' => $userLocalDate,
                        'scheduled_hour' => 7,
                    ]);

                    $delivery->status = 'pending';
                    $delivery->save();

                    // Send via FCM HTTP v1
                    $result = $this->fcmService->sendToToken(
                        $device->fcm_token,
                        $briefing['title'],
                        $briefing['body'],
                        $briefing['data']
                    );

                    if ($result['success']) {
                        $delivery->status = 'sent';
                        $delivery->fcm_message_id = $result['message_id'];
                        $delivery->error_message = null;
                        $delivery->sent_at = now();
                        $delivery->save();
                        $totalSent++;
                    } else {
                        $delivery->status = 'failed';
                        $delivery->error_message = $result['error'];
                        $delivery->save();
                        $totalFailed++;

                        // Clean up uninstalled or invalid tokens
                        if ($result['unregistered']) {
                            $device->update(['is_active' => false]);
                        }
                    }
                }
            }
        });

        $this->info("Dispatch complete. Sent: {$totalSent}, Skipped: {$totalSkipped}, Failed: {$totalFailed}, Suppressed: {$totalSuppressed}");

        return self::SUCCESS;
    }

    /**
     * Build one consolidated morning daily briefing containing whichever categories are enabled and available.
     *
     * @return array{title: string, body: string, data: array<string, mixed>}|null
     */
    protected function buildConsolidatedBriefing(
        UserNotificationPreference $pref,
        User $user,
        string $targetDate,
        string $lang
    ): ?array {
        $panchangEnabled = (bool) $pref->panchang_enabled;
        $horoscopeEnabled = (bool) $pref->horoscope_enabled;
        $personalizedEnabled = (bool) $pref->personalized_enabled;
        $remindersEnabled = (bool) $pref->reminders_enabled;

        // If all streams are disabled, suppress delivery
        if (!$panchangEnabled && !$horoscopeEnabled && !$personalizedEnabled && !$remindersEnabled) {
            return null;
        }

        // 1. Daily Panchang section
        $panchangData = null;
        if ($panchangEnabled) {
            $panchangData = $this->fetchPanchangLimbs($targetDate);
        }

        // 2. Daily Horoscope section
        $horoscopeData = null;
        $signKey = null;
        if ($horoscopeEnabled) {
            $horoscopeData = $this->fetchHoroscopeSnippet($user, $targetDate, $lang);
            $signKey = $horoscopeData['sign'] ?? null;
        }

        // 3. Personalised Insights section (reuses cached/completed results only; never recalculates)
        $personalizedData = null;
        if ($personalizedEnabled) {
            $personalizedData = $this->fetchCachedPersonalizedInsights($user, $targetDate, $lang);
        }

        // Case A: Consolidated Panchang + Horoscope
        if ($panchangData && $horoscopeData) {
            $tithi = $panchangData['tithi'];
            $nakshatra = $panchangData['nakshatra'];
            $rashiName = $horoscopeData['rashi_name'];
            $horoscopeSnippet = $horoscopeData['snippet'];

            if ($lang === 'hi') {
                $title = "आज का पंचांग व राशिफल • {$targetDate}";
                $body = "पंचांग: {$tithi}, {$nakshatra} | {$rashiName}: {$horoscopeSnippet}";
                if ($personalizedData) {
                    $body .= " | गोचर: {$personalizedData}";
                }
            } else {
                $title = "Today's Panchang & Horoscope • {$targetDate}";
                $body = "Panchang: {$tithi}, {$nakshatra} • {$rashiName}: {$horoscopeSnippet}";
                if ($personalizedData) {
                    $body .= " • Transit: {$personalizedData}";
                }
            }

            return [
                'title' => $title,
                'body' => mb_strimwidth($body, 0, 160, '...'),
                'data' => [
                    'type' => 'daily_briefing',
                    'screen' => 'horoscope',
                    'sign' => (string) ($signKey ?: '1'),
                    'date' => $targetDate,
                ],
            ];
        }

        // Case B: Only Panchang
        if ($panchangData) {
            $tithi = $panchangData['tithi'];
            $nakshatra = $panchangData['nakshatra'];
            $sunrise = $panchangData['sunrise'];

            if ($lang === 'hi') {
                $title = "आज का पंचांग • {$targetDate}";
                $body = "तिथि: {$tithi} | नक्षत्र: {$nakshatra} | सूर्योदय: {$sunrise}";
            } else {
                $title = "Today's Panchang • {$targetDate}";
                $body = "Tithi: {$tithi} • Nakshatra: {$nakshatra} • Sunrise: {$sunrise}";
            }

            return [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'daily_briefing',
                    'screen' => 'panchang',
                    'date' => $targetDate,
                ],
            ];
        }

        // Case C: Only Horoscope
        if ($horoscopeData) {
            $rashiName = $horoscopeData['rashi_name'];
            $snippet = $horoscopeData['snippet'];

            if ($lang === 'hi') {
                $title = "{$rashiName} राशिफल • {$targetDate}";
                $body = $snippet;
            } else {
                $title = "{$rashiName} Horoscope • {$targetDate}";
                $body = $snippet;
            }

            return [
                'title' => $title,
                'body' => mb_strimwidth($body, 0, 160, '...'),
                'data' => [
                    'type' => 'daily_briefing',
                    'screen' => 'horoscope',
                    'sign' => (string) ($signKey ?: '1'),
                    'date' => $targetDate,
                ],
            ];
        }

        // Case D: Only Personalised Insights (if Panchang & Horoscope were disabled by user)
        if ($personalizedData) {
            if ($lang === 'hi') {
                $title = "दैनिक व्यक्तिगत अंतर्दृष्टि • {$targetDate}";
                $body = $personalizedData;
            } else {
                $title = "Personalised Daily Insights • {$targetDate}";
                $body = $personalizedData;
            }

            return [
                'title' => $title,
                'body' => mb_strimwidth($body, 0, 160, '...'),
                'data' => [
                    'type' => 'daily_briefing',
                    'screen' => 'personalized_insights',
                    'date' => $targetDate,
                ],
            ];
        }

        // Case E: Only Daily App Reminder enabled
        if ($remindersEnabled) {
            if ($lang === 'hi') {
                $title = "एस्ट्रो सेतु • दैनिक स्मरण";
                $body = "आज का पंचांग, शुभ मुहूर्त एवं अपने दिन का ज्योतिषीय मार्गदर्शन देखें।";
            } else {
                $title = "Astro Setu • Daily Reminder";
                $body = "Check today’s Panchang, auspicious timings, and planetary guide.";
            }

            return [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'daily_briefing',
                    'screen' => 'home',
                    'date' => $targetDate,
                ],
            ];
        }

        return null;
    }

    /**
     * Fetch core Panchang limbs (cached).
     *
     * @return array{tithi: string, nakshatra: string, sunrise: string}
     */
    protected function fetchPanchangLimbs(string $targetDate): array
    {
        try {
            $panchang = $this->panchangService->getDailyPanchang(
                date: $targetDate,
                latitude: 28.6139,
                longitude: 77.2090,
                timezone: 'Asia/Kolkata',
                elevation: 0.0
            );

            return [
                'tithi' => $panchang['panchanga']['tithi']['name'] ?? 'Tithi',
                'nakshatra' => $panchang['panchanga']['nakshatra']['name'] ?? 'Nakshatra',
                'sunrise' => $panchang['sun_moon']['sunrise'] ?? '06:30 AM',
            ];
        } catch (\Throwable $e) {
            Log::warning("Panchang limb lookup fallback: " . $e->getMessage());
            return [
                'tithi' => 'Shukla Pratipada',
                'nakshatra' => 'Ashwini',
                'sunrise' => '06:30 AM',
            ];
        }
    }

    /**
     * Fetch user's Rashi daily horoscope snippet.
     *
     * @return array{rashi_name: string, snippet: string, sign: int}
     */
    protected function fetchHoroscopeSnippet(User $user, string $targetDate, string $lang): array
    {
        $sign = 1; // Default Aries
        /** @var UserAstrologyProfile|null $profile */
        $profile = $user->primaryAstrologyProfile ?? $user->astrologyProfiles->first();
        if ($profile && !empty($profile->moon_sign_index)) {
            $sign = (int) $profile->moon_sign_index;
        }

        $rashiName = ($lang === 'hi')
            ? (self::ZODIAC_HI[$sign] ?? 'राशिफल')
            : (self::ZODIAC_EN[$sign] ?? 'Horoscope');

        $horoscope = Horoscope::where('horoscope_type', 'daily')
            ->where('zodiac_sign', $sign)
            ->where('language', $lang)
            ->whereDate('start_date', $targetDate)
            ->first();

        $snippet = $horoscope?->prediction_general ?: ($lang === 'hi'
            ? 'आज के शुभ ग्रह गोचर एवं अनुकूल समय का अवलोकन करें।'
            : 'Explore today’s planetary transits and auspicious timings.');

        $cleanSnippet = mb_substr(strip_tags($snippet), 0, 110);
        if (mb_strlen(strip_tags($snippet)) > 110) {
            $cleanSnippet .= '...';
        }

        return [
            'rashi_name' => $rashiName,
            'snippet' => $cleanSnippet,
            'sign' => $sign,
        ];
    }

    /**
     * Safely lookup cached M4B personalised transit insights.
     * STRICT SAFEGUARD: Never recalculates from scratch during the notification batch.
     * If uncomputed or unavailable, returns null immediately.
     */
    protected function fetchCachedPersonalizedInsights(User $user, string $targetDate, string $lang): ?string
    {
        /** @var UserAstrologyProfile|null $profile */
        $profile = $user->primaryAstrologyProfile ?? $user->astrologyProfiles->first();

        if (!$profile) {
            return null;
        }

        $timezone = $profile->timezone ?: 'Asia/Kolkata';
        $dob = $profile->date_of_birth instanceof DateTimeInterface
            ? $profile->date_of_birth->format('Y-m-d')
            : (string) $profile->date_of_birth;

        $payload = [
            'profile_id' => $profile->id,
            'user_id' => $user->id,
            'date_of_birth' => $dob,
            'birth_time_precision' => (string) $profile->birth_time_precision,
            'birth_time' => $profile->birth_time,
            'approximate_time_window' => $profile->approximate_time_window,
            'latitude' => (float) $profile->latitude,
            'longitude' => (float) $profile->longitude,
            'timezone' => $timezone,
            'target_date' => $targetDate,
            'name' => $profile->name,
            'relationship' => $profile->relationship,
            'birth_city' => $profile->birth_city,
        ];

        // Query M4B calculation job manager for existing/cached calculation only
        $lookup = $this->jobManager->findOrCreateJob(
            type: PersonalizedInsightsCalculationHandler::TYPE,
            payload: $payload,
            userId: $user->id
        );

        if (!$lookup['ready'] || empty($lookup['result'])) {
            // Calculation not yet ready or queued in worker; do not block, omit section
            return null;
        }

        try {
            $interpreted = $this->interpretationService->getOrGenerateDailyInsights(
                calculationResult: $lookup['result'],
                canonicalKey: (string) $lookup['canonical_key'],
                profile: $profile,
                locale: $lang
            );

            $personalization = $interpreted['personalization'] ?? [];
            if (isset($personalization['eligible']) && $personalization['eligible'] === false) {
                return null;
            }

            $overall = $interpreted['overall_outlook']['summary'] ?? null;
            if ($overall) {
                return mb_strimwidth(strip_tags($overall), 0, 70, '...');
            }
        } catch (\Throwable $e) {
            Log::warning("Personalized insight lookup suppressed: " . $e->getMessage());
        }

        return null;
    }
}
