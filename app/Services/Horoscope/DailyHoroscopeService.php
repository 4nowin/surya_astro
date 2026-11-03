<?php

declare(strict_types=1);

namespace App\Services\Horoscope;

use App\Models\Horoscope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DailyHoroscopeService
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours
    private const CACHE_VERSION_KEY = 'horoscope:cache_version';

    /**
     * Authoritative metadata for all 12 Zodiac signs.
     */
    public const SIGNS = [
        'aries' => [
            'id' => 1,
            'sign' => 'aries',
            'enum' => 'Aries',
            'name_en' => 'Aries',
            'name_hi' => 'मेष',
            'vedic_name' => 'Mesh',
            'symbol' => 'Ram',
            'element' => 'Fire',
        ],
        'taurus' => [
            'id' => 2,
            'sign' => 'taurus',
            'enum' => 'Taurus',
            'name_en' => 'Taurus',
            'name_hi' => 'वृषभ',
            'vedic_name' => 'Vrishabh',
            'symbol' => 'Bull',
            'element' => 'Earth',
        ],
        'gemini' => [
            'id' => 3,
            'sign' => 'gemini',
            'enum' => 'Gemini',
            'name_en' => 'Gemini',
            'name_hi' => 'मिथुन',
            'vedic_name' => 'Mithun',
            'symbol' => 'Twins',
            'element' => 'Air',
        ],
        'cancer' => [
            'id' => 4,
            'sign' => 'cancer',
            'enum' => 'Cancer',
            'name_en' => 'Cancer',
            'name_hi' => 'कर्क',
            'vedic_name' => 'Karka',
            'symbol' => 'Crab',
            'element' => 'Water',
        ],
        'leo' => [
            'id' => 5,
            'sign' => 'leo',
            'enum' => 'Leo',
            'name_en' => 'Leo',
            'name_hi' => 'सिंह',
            'vedic_name' => 'Simha',
            'symbol' => 'Lion',
            'element' => 'Fire',
        ],
        'virgo' => [
            'id' => 6,
            'sign' => 'virgo',
            'enum' => 'Virgo',
            'name_en' => 'Virgo',
            'name_hi' => 'कन्या',
            'vedic_name' => 'Kanya',
            'symbol' => 'Maiden',
            'element' => 'Earth',
        ],
        'libra' => [
            'id' => 7,
            'sign' => 'libra',
            'enum' => 'Libra',
            'name_en' => 'Libra',
            'name_hi' => 'तुला',
            'vedic_name' => 'Tula',
            'symbol' => 'Scales',
            'element' => 'Air',
        ],
        'scorpio' => [
            'id' => 8,
            'sign' => 'scorpio',
            'enum' => 'Scorpio',
            'name_en' => 'Scorpio',
            'name_hi' => 'वृश्चिक',
            'vedic_name' => 'Vrishchik',
            'symbol' => 'Scorpion',
            'element' => 'Water',
        ],
        'sagittarius' => [
            'id' => 9,
            'sign' => 'sagittarius',
            'enum' => 'Sagittarius',
            'name_en' => 'Sagittarius',
            'name_hi' => 'धनु',
            'vedic_name' => 'Dhanu',
            'symbol' => 'Archer',
            'element' => 'Fire',
        ],
        'capricorn' => [
            'id' => 10,
            'sign' => 'capricorn',
            'enum' => 'Capricorn',
            'name_en' => 'Capricorn',
            'name_hi' => 'मकर',
            'vedic_name' => 'Makar',
            'symbol' => 'Goat',
            'element' => 'Earth',
        ],
        'aquarius' => [
            'id' => 11,
            'sign' => 'aquarius',
            'enum' => 'Aquarius',
            'name_en' => 'Aquarius',
            'name_hi' => 'कुम्भ',
            'vedic_name' => 'Kumbh',
            'symbol' => 'Water Bearer',
            'element' => 'Air',
        ],
        'pisces' => [
            'id' => 12,
            'sign' => 'pisces',
            'enum' => 'Pisces',
            'name_en' => 'Pisces',
            'name_hi' => 'मीन',
            'vedic_name' => 'Meen',
            'symbol' => 'Fish',
            'element' => 'Water',
        ],
    ];

    public const SIGN_NUMERIC_MAP = [
        1 => 'aries', 2 => 'taurus', 3 => 'gemini', 4 => 'cancer',
        5 => 'leo', 6 => 'virgo', 7 => 'libra', 8 => 'scorpio',
        9 => 'sagittarius', 10 => 'capricorn', 11 => 'aquarius', 12 => 'pisces',
    ];

    /**
     * Invalidate all horoscope caches upon CMS create/update/delete.
     */
    public static function invalidateCache(): void
    {
        $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $current + 1, self::CACHE_TTL_SECONDS * 30);
    }

    /**
     * Get current cache version number.
     */
    public static function getCacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Retrieve daily horoscope for a given date, sign and language.
     *
     * @param string $date YYYY-MM-DD
     * @param string|null $signKey Lowercase sign identifier (e.g. 'aries')
     * @param string $lang 'en' or 'hi'
     * @return array
     */
    public function getDailyHoroscope(string $date, ?string $signKey = null, string $lang = 'en'): array
    {
        $version = self::getCacheVersion();
        $normalizedSign = $signKey ? strtolower(trim($signKey)) : 'all';
        $cacheKey = "horoscope:daily:v{$version}:{$date}:{$lang}:{$normalizedSign}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($date, $signKey, $lang) {
            return $this->buildDailyHoroscopeData($date, $signKey, $lang);
        });
    }

    /**
     * Internal builder for daily horoscope data.
     */
    protected function buildDailyHoroscopeData(string $date, ?string $signKey, string $lang): array
    {
        $targetDate = Carbon::parse($date)->toDateString();
        $fallbackLang = ($lang === 'hi') ? 'en' : 'hi';

        // Query daily horoscopes for the target date
        $records = Horoscope::where('horoscope_type', 'daily')
            ->whereDate('start_date', $targetDate)
            ->get();

        // Index records by sign enum (lowercase) and language
        $indexed = [];
        foreach ($records as $record) {
            $raw = (string) ($record->zodiac ?: $record->zodiac_sign);
            $sign = strtolower(trim($raw));
            if (is_numeric($sign) && isset(self::SIGN_NUMERIC_MAP[(int) $sign])) {
                $sign = self::SIGN_NUMERIC_MAP[(int) $sign];
            }
            $recordLang = strtolower((string) $record->language);
            $indexed[$sign][$recordLang] = $record;
        }

        $allSignsData = [];
        foreach (self::SIGNS as $key => $meta) {
            $signRecords = $indexed[$key] ?? [];
            
            // Prefer requested language, then fallback language, then any available
            $record = $signRecords[$lang] 
                ?? $signRecords[$fallbackLang] 
                ?? (!empty($signRecords) ? reset($signRecords) : null);

            $allSignsData[$key] = $this->formatSignHoroscope($meta, $record, $targetDate, $lang);
        }

        $selectedSign = null;
        if ($signKey !== null) {
            $normalizedKey = strtolower(trim($signKey));
            $selectedSign = $allSignsData[$normalizedKey] ?? null;
        } else {
            // Default selected sign to Aries (first sign)
            $selectedSign = $allSignsData['aries'] ?? null;
        }

        return [
            'date' => $targetDate,
            'lang' => $lang,
            'formatted_date' => Carbon::parse($targetDate)->locale($lang)->translatedFormat('l, d F Y'),
            'total_signs' => count($allSignsData),
            'selected_sign' => $selectedSign,
            'signs' => array_values($allSignsData),
        ];
    }

    /**
     * Format an individual sign horoscope object.
     */
    protected function formatSignHoroscope(array $meta, ?Horoscope $record, string $date, string $requestedLang): array
    {
        $isAvailable = ($record !== null);

        return [
            'id' => $meta['id'],
            'sign' => $meta['sign'],
            'name_en' => $meta['name_en'],
            'name_hi' => $meta['name_hi'],
            'vedic_name' => $meta['vedic_name'],
            'display_name' => ($requestedLang === 'hi') ? "{$meta['name_hi']} ({$meta['name_en']})" : "{$meta['name_en']} ({$meta['vedic_name']})",
            'symbol' => $meta['symbol'],
            'element' => $meta['element'],
            'image' => $meta['sign'] . '.png',
            'available' => $isAvailable,
            'date' => $date,
            'content_language' => $record?->language,
            'prediction' => $record?->context ? strip_tags((string) $record->context) : null,
            'prediction_html' => $record?->context,
            'categories' => [
                'career' => $record?->career ? strip_tags((string) $record->career) : null,
                'love' => $record?->love ? strip_tags((string) $record->love) : null,
                'finance' => $record?->money ? strip_tags((string) $record->money) : null,
                'health' => $record?->health ? strip_tags((string) $record->health) : null,
                'travel' => $record?->travel ? strip_tags((string) $record->travel) : null,
            ],
            'lucky' => [
                'color' => $record?->lucky_color,
                'number' => $record?->lucky_number,
                'time' => $record?->lucky_time,
            ],
            'mood' => $record?->mood,
        ];
    }

    /**
     * Validate if a given sign key is valid.
     */
    public static function isValidSign(string $sign): bool
    {
        return isset(self::SIGNS[strtolower(trim($sign))]);
    }
}
