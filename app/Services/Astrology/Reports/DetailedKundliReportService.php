<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports;

use App\Models\Astrologer;
use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Interpretation\DetailedInterpretationService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\Pdf\SvgVargaRenderer;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use RuntimeException;

/**
 * 15-Section Report Orchestrator and Gate B Eligibility Engine.
 *
 * Implements Work Package A5 of Astro Setu Milestone M9.2 (Detailed Kundli Report).
 *
 * Enforces Gate B strict eligibility rules:
 *  - Certified: Full mathematical certainty across birth uncertainty interval.
 *  - Qualified: Boundary transition detected; specific caveats, ranges, or multi-limb notations attached.
 *  - Suppressed: Mathematical uncertainty prevents reliable display; honest suppression without empty headers.
 *  - Fallback: Approved qualitative evaluation rendered when dated timelines are unviable.
 */
class DetailedKundliReportService
{
    public const REPORT_VERSION = 'M9.2_A5_V1';

    public const ZODIAC_NAMES = [
        1 => ['en' => 'Aries', 'hi' => 'मेष'],
        2 => ['en' => 'Taurus', 'hi' => 'वृषभ'],
        3 => ['en' => 'Gemini', 'hi' => 'मिथुन'],
        4 => ['en' => 'Cancer', 'hi' => 'कर्क'],
        5 => ['en' => 'Leo', 'hi' => 'सिंह'],
        6 => ['en' => 'Virgo', 'hi' => 'कन्या'],
        7 => ['en' => 'Libra', 'hi' => 'तुला'],
        8 => ['en' => 'Scorpio', 'hi' => 'वृश्चिक'],
        9 => ['en' => 'Sagittarius', 'hi' => 'धनु'],
        10 => ['en' => 'Capricorn', 'hi' => 'मकर'],
        11 => ['en' => 'Aquarius', 'hi' => 'कुम्भ'],
        12 => ['en' => 'Pisces', 'hi' => 'मीन'],
    ];

    public const ZODIAC_BY_NAME = [
        'aries' => 'मेष',
        'taurus' => 'वृषभ',
        'gemini' => 'मिथुन',
        'cancer' => 'कर्क',
        'leo' => 'सिंह',
        'virgo' => 'कन्या',
        'libra' => 'तुला',
        'scorpio' => 'वृश्चिक',
        'sagittarius' => 'धनु',
        'capricorn' => 'मकर',
        'aquarius' => 'कुम्भ',
        'pisces' => 'मीन',
        // Vedic transliterations
        'mesha' => 'मेष',
        'vrishabha' => 'वृषभ',
        'mithuna' => 'मिथुन',
        'karka' => 'कर्क',
        'simha' => 'सिंह',
        'kanya' => 'कन्या',
        'tula' => 'तुला',
        'vrishchika' => 'वृश्चिक',
        'dhanu' => 'धनु',
        'makara' => 'मकर',
        'kumbha' => 'कुम्भ',
        'meena' => 'मीन',
    ];

    public const VAARA_NAMES = [
        0 => ['en' => 'Sunday', 'hi' => 'रविवार'],
        1 => ['en' => 'Monday', 'hi' => 'सोमवार'],
        2 => ['en' => 'Tuesday', 'hi' => 'मंगलवार'],
        3 => ['en' => 'Wednesday', 'hi' => 'बुधवार'],
        4 => ['en' => 'Thursday', 'hi' => 'गुरुवार'],
        5 => ['en' => 'Friday', 'hi' => 'शुक्रवार'],
        6 => ['en' => 'Saturday', 'hi' => 'शनिवार'],
        7 => ['en' => 'Sunday', 'hi' => 'रविवार'],
    ];

    public const NAKSHATRA_NAMES = [
        1 => ['en' => 'Ashwini', 'hi' => 'अश्विनी'],
        2 => ['en' => 'Bharani', 'hi' => 'भरणी'],
        3 => ['en' => 'Krittika', 'hi' => 'कृत्तिका'],
        4 => ['en' => 'Rohini', 'hi' => 'रोहिणी'],
        5 => ['en' => 'Mrigashira', 'hi' => 'मृगशिरा'],
        6 => ['en' => 'Ardra', 'hi' => 'आर्द्रा'],
        7 => ['en' => 'Punarvasu', 'hi' => 'पुनर्वसु'],
        8 => ['en' => 'Pushya', 'hi' => 'पुष्य'],
        9 => ['en' => 'Ashlesha', 'hi' => 'आश्लेषा'],
        10 => ['en' => 'Magha', 'hi' => 'मघा'],
        11 => ['en' => 'PurvaPhalguni', 'hi' => 'पूर्वा फाल्गुनी'],
        12 => ['en' => 'UttaraPhalguni', 'hi' => 'उत्तरा फाल्गुनी'],
        13 => ['en' => 'Hasta', 'hi' => 'हस्त'],
        14 => ['en' => 'Chitra', 'hi' => 'चित्रा'],
        15 => ['en' => 'Swati', 'hi' => 'स्वाती'],
        16 => ['en' => 'Vishakha', 'hi' => 'विशाखा'],
        17 => ['en' => 'Anuradha', 'hi' => 'अनुराधा'],
        18 => ['en' => 'Jyeshtha', 'hi' => 'ज्येष्ठा'],
        19 => ['en' => 'Mula', 'hi' => 'मूल'],
        20 => ['en' => 'PurvaAshadha', 'hi' => 'पूर्वाषाढ़ा'],
        21 => ['en' => 'UttaraAshadha', 'hi' => 'उत्तराषाढ़ा'],
        22 => ['en' => 'Shravana', 'hi' => 'श्रवण'],
        23 => ['en' => 'Dhanishta', 'hi' => 'धनिष्ठा'],
        24 => ['en' => 'Shatabhisha', 'hi' => 'शतभिषा'],
        25 => ['en' => 'PurvaBhadrapada', 'hi' => 'पूर्वाभाद्रपद'],
        26 => ['en' => 'UttaraBhadrapada', 'hi' => 'उत्तराभाद्रपद'],
        27 => ['en' => 'Revati', 'hi' => 'रेवती'],
    ];

    public const NAKSHATRA_BY_NAME = [
        'ashwini' => 'अश्विनी',
        'bharani' => 'भरणी',
        'krittika' => 'कृत्तिका',
        'rohini' => 'रोहिणी',
        'mrigashira' => 'मृगशिरा',
        'ardra' => 'आर्द्रा',
        'punarvasu' => 'पुनर्वसु',
        'pushya' => 'पुष्य',
        'ashlesha' => 'आश्लेषा',
        'magha' => 'मघा',
        'purvaphalguni' => 'पूर्वा फाल्गुनी',
        'purva phalguni' => 'पूर्वा फाल्गुनी',
        'uttaraphalguni' => 'उत्तरा फाल्गुनी',
        'uttara phalguni' => 'उत्तरा फाल्गुनी',
        'hasta' => 'हस्त',
        'chitra' => 'चित्रा',
        'swati' => 'स्वाती',
        'vishakha' => 'विशाखा',
        'anuradha' => 'अनुराधा',
        'jyeshtha' => 'ज्येष्ठा',
        'mula' => 'मूल',
        'purvaashadha' => 'पूर्वाषाढ़ा',
        'purva ashadha' => 'पूर्वाषाढ़ा',
        'uttaraashadha' => 'उत्तराषाढ़ा',
        'uttara ashadha' => 'उत्तराषाढ़ा',
        'shravana' => 'श्रवण',
        'dhanishta' => 'धनिष्ठा',
        'shatabhisha' => 'शतभिषा',
        'purvabhadrapada' => 'पूर्वाभाद्रपद',
        'purva bhadrapada' => 'पूर्वाभाद्रपद',
        'uttarabhadrapada' => 'उत्तराभाद्रपद',
        'uttara bhadrapada' => 'उत्तराभाद्रपद',
        'revati' => 'रेवती',
    ];

    public const YOGA_NAMES = [
        1 => ['en' => 'Vishkambha', 'hi' => 'विष्कम्भ'],
        2 => ['en' => 'Priti', 'hi' => 'प्रीति'],
        3 => ['en' => 'Ayushman', 'hi' => 'आयुष्मान'],
        4 => ['en' => 'Saubhagya', 'hi' => 'सौभाग्य'],
        5 => ['en' => 'Shobhana', 'hi' => 'शोभन'],
        6 => ['en' => 'Atiganda', 'hi' => 'अतिगण्ड'],
        7 => ['en' => 'Sukarma', 'hi' => 'सुकर्मा'],
        8 => ['en' => 'Dhriti', 'hi' => 'धृति'],
        9 => ['en' => 'Shula', 'hi' => 'शूल'],
        10 => ['en' => 'Ganda', 'hi' => 'गण्ड'],
        11 => ['en' => 'Vriddhi', 'hi' => 'वृद्धि'],
        12 => ['en' => 'Dhruva', 'hi' => 'ध्रुव'],
        13 => ['en' => 'Vyaghata', 'hi' => 'व्याघात'],
        14 => ['en' => 'Harshana', 'hi' => 'हर्षण'],
        15 => ['en' => 'Vajra', 'hi' => 'वज्र'],
        16 => ['en' => 'Siddhi', 'hi' => 'सिद्धि'],
        17 => ['en' => 'Vyatipata', 'hi' => 'व्यतीपात'],
        18 => ['en' => 'Variyan', 'hi' => 'वरीयान्'],
        19 => ['en' => 'Parigha', 'hi' => 'परिघ'],
        20 => ['en' => 'Shiva', 'hi' => 'शिव'],
        21 => ['en' => 'Siddha', 'hi' => 'सिद्ध'],
        22 => ['en' => 'Sadhya', 'hi' => 'साध्य'],
        23 => ['en' => 'Shubha', 'hi' => 'शुभ'],
        24 => ['en' => 'Shukla', 'hi' => 'शुक्ल'],
        25 => ['en' => 'Brahma', 'hi' => 'ब्रह्म'],
        26 => ['en' => 'Indra', 'hi' => 'इन्द्र'],
        27 => ['en' => 'Vaidhriti', 'hi' => 'वैधृति'],
    ];

    public const KARANA_NAMES = [
        1 => ['en' => 'Bava', 'hi' => 'बव'],
        2 => ['en' => 'Balava', 'hi' => 'बालव'],
        3 => ['en' => 'Kaulava', 'hi' => 'कौलव'],
        4 => ['en' => 'Taitila', 'hi' => 'तैतिल'],
        5 => ['en' => 'Gara', 'hi' => 'गर'],
        6 => ['en' => 'Vanija', 'hi' => 'वणिज'],
        7 => ['en' => 'Vishti (Bhadra)', 'hi' => 'विष्टि (भद्रा)'],
        8 => ['en' => 'Shakuni', 'hi' => 'शकुनि'],
        9 => ['en' => 'Chatushpada', 'hi' => 'चतुष्पाद'],
        10 => ['en' => 'Naga', 'hi' => 'नाग'],
        11 => ['en' => 'Kintughna', 'hi' => 'किस्तुघ्न'],
    ];

    public const PLANET_NAMES = [
        'sun' => ['en' => 'Sun', 'hi' => 'सूर्य'],
        'moon' => ['en' => 'Moon', 'hi' => 'चन्द्र'],
        'mars' => ['en' => 'Mars', 'hi' => 'मंगल'],
        'mercury' => ['en' => 'Mercury', 'hi' => 'बुध'],
        'jupiter' => ['en' => 'Jupiter', 'hi' => 'बृहस्पति'],
        'venus' => ['en' => 'Venus', 'hi' => 'शुक्र'],
        'saturn' => ['en' => 'Saturn', 'hi' => 'शनि'],
        'rahu' => ['en' => 'Rahu', 'hi' => 'राहु'],
        'ketu' => ['en' => 'Ketu', 'hi' => 'केतु'],
        'ascendant' => ['en' => 'Ascendant', 'hi' => 'लग्न'],
    ];

    public function __construct(
        protected ?KundliService $kundliService = null,
        protected ?AstrologyIntervalStabilityService $stabilityService = null,
        protected ?YogaDoshaOrchestrator $yogaOrchestrator = null,
        protected ?SaturnTransitService $transitService = null,
        protected ?SvgVargaRenderer $svgRenderer = null,
        protected ?DetailedInterpretationService $interpretationService = null
    ) {
        $this->kundliService ??= app(KundliService::class);
        $this->stabilityService ??= app(AstrologyIntervalStabilityService::class);
        $this->yogaOrchestrator ??= app(YogaDoshaOrchestrator::class);
        $this->transitService ??= app(SaturnTransitService::class);
        $this->svgRenderer ??= app(SvgVargaRenderer::class);
        $this->interpretationService ??= app(DetailedInterpretationService::class);
    }

    public function localizeZodiac(string|int|null $sign, string $locale = 'en'): string
    {
        if ($sign === null || $sign === '') {
            return '—';
        }

        if (is_int($sign) || (is_string($sign) && ctype_digit($sign))) {
            $id = (int) $sign;
            if ($id >= 1 && $id <= 12) {
                return $locale === 'hi' ? self::ZODIAC_NAMES[$id]['hi'] : self::ZODIAC_NAMES[$id]['en'];
            }
        }

        $str = trim((string) $sign);
        if ($locale === 'hi') {
            $lower = strtolower($str);
            return self::ZODIAC_BY_NAME[$lower] ?? $str;
        }

        return $str;
    }

    public function localizeNakshatra(string|int|null $nak, string $locale = 'en'): string
    {
        if ($nak === null || $nak === '') {
            return '—';
        }

        if (is_int($nak) || (is_string($nak) && ctype_digit($nak))) {
            $id = (int) $nak;
            if ($id >= 1 && $id <= 27) {
                return $locale === 'hi' ? self::NAKSHATRA_NAMES[$id]['hi'] : self::NAKSHATRA_NAMES[$id]['en'];
            }
        }

        $str = trim((string) $nak);
        if ($locale === 'hi') {
            $clean = strtolower(str_replace([' ', '_', '-'], '', $str));
            foreach (self::NAKSHATRA_BY_NAME as $k => $hi) {
                if (strtolower(str_replace([' ', '_', '-'], '', $k)) === $clean) {
                    return $hi;
                }
            }
            return self::NAKSHATRA_BY_NAME[strtolower($str)] ?? $str;
        }

        return $str;
    }

    public function localizePlanet(string|null $planet, string $locale = 'en'): string
    {
        if ($planet === null || $planet === '') {
            return '';
        }
        $k = strtolower(trim($planet));
        if ($locale === 'hi' && isset(self::PLANET_NAMES[$k]['hi'])) {
            return self::PLANET_NAMES[$k]['hi'];
        }
        return self::PLANET_NAMES[$k]['en'] ?? ucfirst($planet);
    }

    public function localizeVaara(int|string|null $vara, string $locale = 'en'): string
    {
        if ($vara === null) {
            return '—';
        }
        if (is_int($vara) || ctype_digit((string) $vara)) {
            $id = (int) $vara;
            return $locale === 'hi' ? (self::VAARA_NAMES[$id]['hi'] ?? 'बुधवार') : (self::VAARA_NAMES[$id]['en'] ?? 'Wednesday');
        }
        $vStr = strtolower(trim((string) $vara));
        foreach (self::VAARA_NAMES as $item) {
            if (strtolower($item['en']) === $vStr) {
                return $locale === 'hi' ? $item['hi'] : $item['en'];
            }
        }
        return (string) $vara;
    }

    public function localizeYoga(int|string|null $yoga, string $locale = 'en'): string
    {
        if ($yoga === null) {
            return '—';
        }
        if (is_int($yoga) || ctype_digit((string) $yoga)) {
            $id = (int) $yoga;
            return $locale === 'hi' ? (self::YOGA_NAMES[$id]['hi'] ?? 'अतिगण्ड') : (self::YOGA_NAMES[$id]['en'] ?? 'Atiganda');
        }
        $yStr = strtolower(trim((string) $yoga));
        foreach (self::YOGA_NAMES as $item) {
            if (strtolower($item['en']) === $yStr) {
                return $locale === 'hi' ? $item['hi'] : $item['en'];
            }
        }
        return (string) $yoga;
    }

    public function localizeKarana(int|string|null $karana, string $locale = 'en'): string
    {
        if ($karana === null) {
            return '—';
        }
        if (is_int($karana) || ctype_digit((string) $karana)) {
            $id = (int) $karana;
            return $locale === 'hi' ? (self::KARANA_NAMES[$id]['hi'] ?? 'कौलव') : (self::KARANA_NAMES[$id]['en'] ?? 'Kaulava');
        }
        $kStr = strtolower(trim((string) $karana));
        foreach (self::KARANA_NAMES as $item) {
            if (strtolower($item['en']) === $kStr) {
                return $locale === 'hi' ? $item['hi'] : $item['en'];
            }
        }
        return (string) $karana;
    }

    public function localizeTithi(int|string|null $tithiId, ?string $rawName, string $locale = 'en'): string
    {
        $id = is_numeric($tithiId) ? (int) $tithiId : null;
        if ($id !== null && $id >= 1 && $id <= 30) {
            if ($id === 30) {
                return $locale === 'hi' ? 'कृष्ण पक्ष अमावस्या' : 'Krishna Amavasya';
            }
            $isShukla = ($id <= 15);
            $idxInPaksha = $isShukla ? $id : ($id - 15);
            $tithiBase = [
                1 => ['en' => 'Pratipada', 'hi' => 'प्रतिपदा'],
                2 => ['en' => 'Dwitiya', 'hi' => 'द्वितीया'],
                3 => ['en' => 'Tritiya', 'hi' => 'तृतीया'],
                4 => ['en' => 'Chaturthi', 'hi' => 'चतुर्थी'],
                5 => ['en' => 'Panchami', 'hi' => 'पंचमी'],
                6 => ['en' => 'Shashthi', 'hi' => 'षष्ठी'],
                7 => ['en' => 'Saptami', 'hi' => 'सप्तमी'],
                8 => ['en' => 'Ashtami', 'hi' => 'अष्टमी'],
                9 => ['en' => 'Navami', 'hi' => 'नवमी'],
                10 => ['en' => 'Dashami', 'hi' => 'दशमी'],
                11 => ['en' => 'Ekadashi', 'hi' => 'एकादशी'],
                12 => ['en' => 'Dwadashi', 'hi' => 'द्वादशी'],
                13 => ['en' => 'Trayodashi', 'hi' => 'त्रयोदशी'],
                14 => ['en' => 'Chaturdashi', 'hi' => 'चतुर्दशी'],
                15 => ['en' => 'Purnima', 'hi' => 'पूर्णिमा'],
            ];

            $pakshaName = $isShukla
                ? ($locale === 'hi' ? 'शुक्ल पक्ष' : 'Shukla')
                : ($locale === 'hi' ? 'कृष्ण पक्ष' : 'Krishna');

            $name = $tithiBase[$idxInPaksha][$locale] ?? 'षष्ठी';
            return "{$pakshaName} {$name}";
        }

        return $rawName ?? '—';
    }

    /**
     * Build the complete 15-section report data payload.
     *
     * @param array<string, mixed> $payload Birth data payload
     * @param string $locale 'en' or 'hi'
     * @return array<string, mixed>
     */
    public function buildReportData(array $payload, string $locale = 'en'): array
    {
        $birthDate = (string) ($payload['date_of_birth'] ?? $payload['birth_date'] ?? '');
        $birthTime = isset($payload['birth_time']) ? (string) $payload['birth_time'] : null;
        $precision = (string) ($payload['birth_time_precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED);
        $approximateWindow = isset($payload['approximate_time_window']) ? (string) $payload['approximate_time_window'] : null;
        $latitude = (float) ($payload['latitude'] ?? 0.0);
        $longitude = (float) ($payload['longitude'] ?? 0.0);
        $timezone = (string) ($payload['timezone'] ?? 'UTC');
        $personName = (string) ($payload['name'] ?? 'Native');
        $birthCity = (string) ($payload['birth_city'] ?? 'Unknown');
        $birthCountry = (string) ($payload['birth_country'] ?? 'India');

        // Resolve coordinates and timezone via canonical local gazetteer if not specified or 0,0
        if (($latitude == 0.0 && $longitude == 0.0) && $birthCity !== 'Unknown') {
            try {
                $geoProvider = app(\App\Services\Geo\LocalGazetteerGeocodingProvider::class);
                $cityMatches = $geoProvider->searchCities($birthCity);
                if (!empty($cityMatches)) {
                    $latitude = (float) $cityMatches[0]['latitude'];
                    $longitude = (float) $cityMatches[0]['longitude'];
                    if ($timezone === 'UTC' || empty($payload['timezone'])) {
                        $timezone = (string) $cityMatches[0]['timezone'];
                    }
                }
            } catch (\Throwable $e) {
                // Fall back gracefully to provided coordinates
            }
        }

        if ($birthDate === '') {
            throw new RuntimeException('Missing date_of_birth in report calculation payload.');
        }

        // 1. Core Kundli calculation
        $kundli = $this->kundliService->calculateFromPayload([
            'date_of_birth' => $birthDate,
            'birth_time' => $birthTime,
            'birth_time_precision' => $precision,
            'approximate_time_window' => $approximateWindow,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'name' => $personName,
            'birth_city' => $birthCity,
        ]);

        // 2. Interval stability analysis across birth uncertainty window
        $stability = $this->stabilityService->analyze(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            targetDate: CarbonImmutable::now($timezone)->toIso8601String(),
            locale: $locale
        );

        // Calculate user age in years
        $birthCarbon = CarbonImmutable::parse($birthDate, $timezone);
        $userAgeYears = (int) $birthCarbon->diffInYears(CarbonImmutable::now($timezone));

        // 3. Yogas, aspects & Manglik Dosha
        $yogas = $this->yogaOrchestrator->evaluate(
            planets: $kundli['planets'],
            ascendantData: $kundli['ascendant'],
            stabilityData: $stability,
            userAgeYears: $userAgeYears
        );

        // 4. Saturn transits (Sade Sati & Dhaiya)
        $transitData = $this->resolveSaturnTransits($kundli, $stability, $birthCarbon);

        // 5. Active verified astrologers for referral section
        $astrologers = $this->getReferralAstrologers($locale);

        // 6. Evaluate Gate B eligibility and assemble all 15 sections
        $auditMatrix = [];

        // Section 1: Cover & Coordinates
        $sec1 = $this->buildSection1Cover(
            $kundli,
            $precision,
            $personName,
            $birthCity,
            $birthCountry,
            $birthDate,
            $birthTime,
            $approximateWindow,
            $latitude,
            $longitude,
            $timezone,
            $locale
        );
        $auditMatrix['sec1_cover'] = $sec1['gate_b_status'];

        // Section 2: Panchanga & Avakahada
        $sec2 = $this->buildSection2Panchanga($kundli, $stability, $locale);
        $auditMatrix['sec2_panchanga'] = $sec2['gate_b_status'];

        // Section 3: Lagna Kundli (D-1) & Planetary Table
        $sec3 = $this->buildSection3LagnaKundli($kundli, $stability, $locale);
        $auditMatrix['sec3_lagna_d1'] = $sec3['gate_b_status'];

        // Section 4: Chandra Kundli (Moon Chart)
        $sec4 = $this->buildSection4ChandraKundli($kundli, $stability, $locale);
        $auditMatrix['sec4_chandra'] = $sec4['gate_b_status'];

        // Section 5: D-9 Navamsha Chart & Vargottama
        $sec5 = $this->buildSection5Navamsha($kundli, $stability, $locale);
        $auditMatrix['sec5_navamsha_d9'] = $sec5['gate_b_status'];

        // Section 6: Parashari Graha Drishti Matrix
        $sec6 = $this->buildSection6Aspects($yogas['aspects'] ?? [], $precision, $locale);
        $auditMatrix['sec6_aspects'] = $sec6['gate_b_status'];

        // Section 7: Janma Nakshatra Deep Dive
        $sec7 = $this->buildSection7JanmaNakshatra($kundli, $stability, $locale);
        $auditMatrix['sec7_nakshatra'] = $sec7['gate_b_status'];

        // Section 8: Vimshottari Mahadasha Timeline
        $sec8 = $this->buildSection8VimshottariTimeline($stability['vimshottari_dasha'] ?? [], $locale);
        $auditMatrix['sec8_vimshottari_timeline'] = $sec8['gate_b_status'];

        // Section 9: Current Running Dasha & Pratyantardasha
        $sec9 = $this->buildSection9CurrentDasha($stability['vimshottari_dasha'] ?? [], $locale);
        $auditMatrix['sec9_current_dasha'] = $sec9['gate_b_status'];

        // Section 10: Classical Planetary Yogas
        $sec10 = $this->buildSection10ClassicalYogas($yogas, $sec3['gate_b_status'], $locale);
        $auditMatrix['sec10_classical_yogas'] = $sec10['gate_b_status'];

        // Section 11: Manglik Dosha Audit
        $sec11 = $this->buildSection11ManglikDosha($yogas['manglik_dosha'] ?? [], $locale);
        $auditMatrix['sec11_manglik_dosha'] = $sec11['gate_b_status'];

        // Section 12: Saturn Sade Sati & Dhaiya
        $sec12 = $this->buildSection12SaturnTransit($transitData, $locale);
        $auditMatrix['sec12_saturn_transit'] = $sec12['gate_b_status'];

        // Section 13: Classical Vedic Remedies (Upay)
        $sec13 = $this->buildSection13Remedies($kundli, $yogas, $sec11, $locale);
        $auditMatrix['sec13_remedies'] = $sec13['gate_b_status'];

        // Section 14: Astrologer Referral
        $sec14 = $this->buildSection14AstrologerReferral($astrologers, $locale);
        $auditMatrix['sec14_astrologer_referral'] = $sec14['gate_b_status'];

        // Section 15: Precision Audit & Disclaimers
        $auditMatrix['sec15_precision_audit'] = 'certified';
        $sec15 = $this->buildSection15PrecisionAudit($auditMatrix, $precision, $locale);

        return [
            'meta' => [
                'engine_version' => self::REPORT_VERSION,
                'ruleset_version' => DetailedInterpretationService::RULE_VERSION,
                'locale' => $locale,
                'precision_tier' => $precision,
                'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'person_name' => $personName,
                'birth_city' => $birthCity,
                'birth_country' => $birthCountry,
                'audit_matrix' => $auditMatrix,
            ],
            'sections' => [
                'sec1_cover' => $sec1,
                'sec2_panchanga' => $sec2,
                'sec3_lagna_d1' => $sec3,
                'sec4_chandra' => $sec4,
                'sec5_navamsha_d9' => $sec5,
                'sec6_aspects' => $sec6,
                'sec7_nakshatra' => $sec7,
                'sec8_vimshottari_timeline' => $sec8,
                'sec9_current_dasha' => $sec9,
                'sec10_classical_yogas' => $sec10,
                'sec11_manglik_dosha' => $sec11,
                'sec12_saturn_transit' => $sec12,
                'sec13_remedies' => $sec13,
                'sec14_astrologer_referral' => $sec14,
                'sec15_precision_audit' => $sec15,
            ],
        ];
    }

    /**
     * Resolve Saturn transit timeline or qualitative fallback.
     */
    protected function resolveSaturnTransits(array $kundli, array $stability, CarbonImmutable $birthCarbon): array
    {
        $moonLon = (float) ($kundli['planets']['moon']['longitude'] ?? 0.0);
        $moonRashi = (int) (floor($moonLon / 30.0) + 1);

        // Check if Moon sign is invariant across interval
        $panchangaLimb = $stability['panchanga']['moon_sign'] ?? null;
        $isMoonInvariant = $panchangaLimb === null || !empty($panchangaLimb['is_invariant']);

        if (!$isMoonInvariant) {
            // Moon sign volatile: return qualitative fallback
            $fallback = $this->transitService->evaluateCurrentPhaseAt($moonRashi);
            return [
                'status' => 'fallback',
                'is_stable' => false,
                'qualitative_fallback' => $fallback,
                'reason' => 'Natal Moon sign crosses sign boundary across uncertainty interval. Qualitative fallback provided.',
            ];
        }

        // Calculate a 15-year timeline from birth with 7-day sampling step
        try {
            $timeline = $this->transitService->calculateTimeline(
                natalMoonRashi: $moonRashi,
                startDate: $birthCarbon,
                endDate: $birthCarbon->addYears(15),
                options: [
                    'reference_date' => CarbonImmutable::now('UTC'),
                    'sample_step_days' => 7.0,
                ]
            );
            $timeline['is_stable'] = true;
            return $timeline;
        } catch (\Throwable $e) {
            // Fallback to current phase if full timeline calculation fails
            $fallback = $this->transitService->evaluateCurrentPhaseAt($moonRashi);
            return [
                'status' => 'fallback',
                'is_stable' => true,
                'qualitative_fallback' => $fallback,
                'reason' => 'Current phase summary provided.',
            ];
        }
    }

    /**
     * Retrieve active verified astrologers from database.
     */
    protected function getReferralAstrologers(string $locale): array
    {
        try {
            return Astrologer::query()
                ->where('active', 1)
                ->take(3)
                ->get()
                ->map(function ($ast) use ($locale) {
                    return [
                        'id' => $ast->id,
                        'name' => $ast->name,
                        'expertise' => $ast->expertise ?? 'Vedic Astrology',
                        'experience' => $ast->experience ?? 5,
                        'price' => (float) $ast->price,
                        'call_price' => (float) ($ast->call_price ?? $ast->price),
                        'rating' => round((float) ($ast->averageRating() ?: 4.8), 1),
                        'language' => $ast->language ?? ($locale === 'hi' ? 'हिन्दी, English' : 'English, Hindi'),
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Section 1: Cover Page & Coordinates.
     */
    protected function buildSection1Cover(
        array $kundli,
        string $precision,
        string $name,
        string $birthCity,
        string $birthCountry,
        string $birthDate,
        ?string $birthTime,
        ?string $approximateWindow,
        float $latitude,
        float $longitude,
        string $timezone,
        string $locale
    ): array {
        $formattedTime = match ($precision) {
            UserAstrologyProfile::PRECISION_VERIFIED => $birthTime ? ($birthTime . ' (Local Time)') : ($locale === 'hi' ? 'प्रमाणित' : 'Verified'),
            UserAstrologyProfile::PRECISION_APPROXIMATE => $approximateWindow
                ? (ucfirst(str_replace('_', ' ', $approximateWindow)) . ' Window')
                : ($locale === 'hi' ? 'अनुमानित' : 'Approximate Window'),
            UserAstrologyProfile::PRECISION_UNKNOWN => $locale === 'hi' ? 'अज्ञात (संपूर्ण दिन)' : 'Unknown (Civil Day)',
            default => $birthTime ?: '—',
        };

        $coordFormatted = ($latitude != 0.0 || $longitude != 0.0)
            ? sprintf('%s° %s, %s° %s', abs(round($latitude, 4)), $latitude >= 0 ? 'N' : 'S', abs(round($longitude, 4)), $longitude >= 0 ? 'E' : 'W')
            : ($locale === 'hi' ? 'अनुपलब्ध' : 'Unavailable');

        $tzFormatted = $timezone === 'Asia/Kolkata'
            ? ($locale === 'hi' ? 'Asia/Kolkata (IST, UTC+05:30)' : 'Asia/Kolkata (IST, UTC+05:30)')
            : $timezone;

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'वैदिक जन्म पत्रिका (विस्तृत प्रतिवेदन)' : 'Comprehensive Vedic Janam Kundli Report',
            'name' => $name,
            'birth_city' => $birthCity,
            'birth_country' => $birthCountry,
            'date_of_birth' => $birthDate,
            'birth_time' => $birthTime,
            'birth_time_formatted' => $formattedTime,
            'precision_tier' => $precision,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'coordinates_formatted' => $coordFormatted,
            'timezone' => $timezone,
            'timezone_formatted' => $tzFormatted,
            'ayanamsa' => 'True Chitra Paksha Lahiri (IAU Compliant)',
            'calculation_engine' => 'Jayesh Mepani Moshier Ephemeris + JmeRuntime FFI',
            'report_version' => self::REPORT_VERSION,
            'purushartha_framing' => DetailedInterpretationService::PURUSHARTHA_FRAMING[$locale],
            'general_disclaimer' => DetailedInterpretationService::GENERAL_DISCLAIMER[$locale],
        ];
    }

    /**
     * Compute classical Avakahada Chakra attributes deterministically from Moon sign and Nakshatra.
     *
     * @return array<string, string>
     */
    protected function computeAvakahada(int $moonRashiId, int $nakshatraId, string $locale): array
    {
        // 1. Varna by Moon sign (Water: 4,8,12; Fire: 1,5,9; Earth: 2,6,10; Air: 3,7,11)
        $varnaMap = [
            4 => ['en' => 'Brahmana (Water)', 'hi' => 'ब्राह्मण'],
            8 => ['en' => 'Brahmana (Water)', 'hi' => 'ब्राह्मण'],
            12 => ['en' => 'Brahmana (Water)', 'hi' => 'ब्राह्मण'],
            1 => ['en' => 'Kshatriya (Fire)', 'hi' => 'क्षत्रिय'],
            5 => ['en' => 'Kshatriya (Fire)', 'hi' => 'क्षत्रिय'],
            9 => ['en' => 'Kshatriya (Fire)', 'hi' => 'क्षत्रिय'],
            2 => ['en' => 'Vaishya (Earth)', 'hi' => 'वैश्य'],
            6 => ['en' => 'Vaishya (Earth)', 'hi' => 'वैश्य'],
            10 => ['en' => 'Vaishya (Earth)', 'hi' => 'वैश्य'],
            3 => ['en' => 'Shudra (Air)', 'hi' => 'शूद्र'],
            7 => ['en' => 'Shudra (Air)', 'hi' => 'शूद्र'],
            11 => ['en' => 'Shudra (Air)', 'hi' => 'शूद्र'],
        ];

        // 2. Vashya by Moon sign
        $vashyaMap = [
            1 => ['en' => 'Chatushpada (Quadruped)', 'hi' => 'चतुष्पाद'],
            2 => ['en' => 'Chatushpada (Quadruped)', 'hi' => 'चतुष्पाद'],
            3 => ['en' => 'Manava / Dvipada (Human)', 'hi' => 'मानव (द्विपद)'],
            4 => ['en' => 'Jalachara (Water Dweller)', 'hi' => 'जलचर'],
            5 => ['en' => 'Vanachara (Forest / Lion)', 'hi' => 'वनचर'],
            6 => ['en' => 'Manava / Dvipada (Human)', 'hi' => 'मानव (द्विपद)'],
            7 => ['en' => 'Manava / Dvipada (Human)', 'hi' => 'मानव (द्विपद)'],
            8 => ['en' => 'Keeta (Insect / Scorpion)', 'hi' => 'कीट'],
            9 => ['en' => 'Chatushpada / Manava', 'hi' => 'द्विपद / चतुष्पाद'],
            10 => ['en' => 'Jalachara / Chatushpada', 'hi' => 'जलचर / चतुष्पाद'],
            11 => ['en' => 'Manava / Dvipada (Human)', 'hi' => 'मानव (द्विपद)'],
            12 => ['en' => 'Jalachara (Water Dweller)', 'hi' => 'जलचर'],
        ];

        // 3. Yoni by Nakshatra (1..27)
        $yoniList = [
            1 => ['en' => 'Ashwa (Horse)', 'hi' => 'अश्व'],
            2 => ['en' => 'Gaja (Elephant)', 'hi' => 'गज'],
            3 => ['en' => 'Mesha (Sheep)', 'hi' => 'मेष'],
            4 => ['en' => 'Sarpa (Serpent)', 'hi' => 'सर्प'],
            5 => ['en' => 'Sarpa (Serpent)', 'hi' => 'सर्प'],
            6 => ['en' => 'Shwana (Dog)', 'hi' => 'श्वान'],
            7 => ['en' => 'Marjara (Cat)', 'hi' => 'मार्जार'],
            8 => ['en' => 'Mesha (Sheep)', 'hi' => 'मेष'],
            9 => ['en' => 'Marjara (Cat)', 'hi' => 'मार्जार'],
            10 => ['en' => 'Mushaka (Rat)', 'hi' => 'मूषक'],
            11 => ['en' => 'Mushaka (Rat)', 'hi' => 'मूषक'],
            12 => ['en' => 'Go (Cow)', 'hi' => 'गौ'],
            13 => ['en' => 'Mahisha (Buffalo)', 'hi' => 'महिष'],
            14 => ['en' => 'Vyaghra (Tiger)', 'hi' => 'व्याघ्र'],
            15 => ['en' => 'Mahisha (Buffalo)', 'hi' => 'महिष'],
            16 => ['en' => 'Vyaghra (Tiger)', 'hi' => 'व्याघ्र'],
            17 => ['en' => 'Mriga (Deer)', 'hi' => 'मृग'],
            18 => ['en' => 'Mriga (Deer)', 'hi' => 'मृग'],
            19 => ['en' => 'Shwana (Dog)', 'hi' => 'श्वान'],
            20 => ['en' => 'Vanara (Monkey)', 'hi' => 'वानर'],
            21 => ['en' => 'Nakula (Mongoose)', 'hi' => 'नकुल'],
            22 => ['en' => 'Vanara (Monkey)', 'hi' => 'वानर'],
            23 => ['en' => 'Simha (Lion)', 'hi' => 'सिंह'],
            24 => ['en' => 'Ashwa (Horse)', 'hi' => 'अश्व'],
            25 => ['en' => 'Simha (Lion)', 'hi' => 'सिंह'],
            26 => ['en' => 'Go (Cow)', 'hi' => 'गौ'],
            27 => ['en' => 'Gaja (Elephant)', 'hi' => 'गज'],
        ];

        // 4. Gana by Nakshatra
        $devaGana = [1, 5, 7, 8, 13, 15, 17, 22, 27];
        $rakshasaGana = [3, 9, 10, 14, 16, 18, 19, 23, 24];
        $gana = in_array($nakshatraId, $devaGana, true)
            ? ['en' => 'Deva (Divine)', 'hi' => 'देव']
            : (in_array($nakshatraId, $rakshasaGana, true)
                ? ['en' => 'Rakshasa (Fierce)', 'hi' => 'राक्षस']
                : ['en' => 'Manushya (Human)', 'hi' => 'मनुष्य']);

        // 5. Nadi by Nakshatra (Adi, Madhya, Antya)
        $adiNadi = [1, 6, 7, 12, 13, 18, 19, 24, 25];
        $madhyaNadi = [2, 5, 8, 11, 14, 17, 20, 23, 26];
        $nadi = in_array($nakshatraId, $adiNadi, true)
            ? ['en' => 'Adi (Vata)', 'hi' => 'आदि (वात)']
            : (in_array($nakshatraId, $madhyaNadi, true)
                ? ['en' => 'Madhya (Pitta)', 'hi' => 'मध्य (पित्त)']
                : ['en' => 'Antya (Kapha)', 'hi' => 'अंत्य (कफ)']);

        // 6. Nakshatra Ruling Planet (Vimshottari 9-cycle)
        $vimLordOrder = [
            0 => ['en' => 'Ketu', 'hi' => 'केतु'],
            1 => ['en' => 'Venus', 'hi' => 'शुक्र'],
            2 => ['en' => 'Sun', 'hi' => 'सूर्य'],
            3 => ['en' => 'Moon', 'hi' => 'चन्द्र'],
            4 => ['en' => 'Mars', 'hi' => 'मंगल'],
            5 => ['en' => 'Rahu', 'hi' => 'राहु'],
            6 => ['en' => 'Jupiter', 'hi' => 'बृहस्पति'],
            7 => ['en' => 'Saturn', 'hi' => 'शनि'],
            8 => ['en' => 'Mercury', 'hi' => 'बुध'],
        ];
        $nakLord = $vimLordOrder[($nakshatraId - 1) % 9] ?? ['en' => 'Moon', 'hi' => 'चन्द्र'];

        // 7. Moon Rashi Lord
        $rashiLordMap = [
            1 => ['en' => 'Mars', 'hi' => 'मंगल'],
            2 => ['en' => 'Venus', 'hi' => 'शुक्र'],
            3 => ['en' => 'Mercury', 'hi' => 'बुध'],
            4 => ['en' => 'Moon', 'hi' => 'चन्द्र'],
            5 => ['en' => 'Sun', 'hi' => 'सूर्य'],
            6 => ['en' => 'Mercury', 'hi' => 'बुध'],
            7 => ['en' => 'Venus', 'hi' => 'शुक्र'],
            8 => ['en' => 'Mars', 'hi' => 'मंगल'],
            9 => ['en' => 'Jupiter', 'hi' => 'बृहस्पति'],
            10 => ['en' => 'Saturn', 'hi' => 'शनि'],
            11 => ['en' => 'Saturn', 'hi' => 'शनि'],
            12 => ['en' => 'Jupiter', 'hi' => 'बृहस्पति'],
        ];
        $rashiLord = $rashiLordMap[$moonRashiId] ?? ['en' => 'Mars', 'hi' => 'मंगल'];

        return [
            'varna' => $locale === 'hi' ? $varnaMap[$moonRashiId]['hi'] : $varnaMap[$moonRashiId]['en'],
            'vashya' => $locale === 'hi' ? $vashyaMap[$moonRashiId]['hi'] : $vashyaMap[$moonRashiId]['en'],
            'yoni' => $locale === 'hi' ? ($yoniList[$nakshatraId]['hi'] ?? 'गज') : ($yoniList[$nakshatraId]['en'] ?? 'Gaja'),
            'gana' => $locale === 'hi' ? $gana['hi'] : $gana['en'],
            'nadi' => $locale === 'hi' ? $nadi['hi'] : $nadi['en'],
            'nakshatra_lord' => $locale === 'hi' ? $nakLord['hi'] : $nakLord['en'],
            'rashi_lord' => $locale === 'hi' ? $rashiLord['hi'] : $rashiLord['en'],
        ];
    }

    /**
     * Section 2: Panchanga & Avakahada Chakra.
     */
    protected function buildSection2Panchanga(array $kundli, array $stability, string $locale): array
    {
        $panchangaStability = $stability['panchanga'] ?? ($stability['birth_panchanga'] ?? []);
        $hasTransition = false;

        foreach ($panchangaStability as $limbKey => $limbData) {
            if (is_array($limbData) && empty($limbData['is_invariant']) && !empty($limbData['boundary_crossings'])) {
                $hasTransition = true;
                break;
            }
        }

        $gateBStatus = $hasTransition ? 'qualified' : 'certified';

        // Tithi formatting
        $tithiData = $panchangaStability['tithi'] ?? [];
        $tithiId = $tithiData['id'] ?? null;
        $tithiName = $this->localizeTithi($tithiId, $tithiData['name'] ?? null, $locale);

        // Vaara formatting
        $vaaraData = $panchangaStability['vaara'] ?? [];
        $vaaraId = $vaaraData['id'] ?? null;
        $vaaraName = $this->localizeVaara($vaaraId ?? ($vaaraData['name'] ?? null), $locale);

        // Nakshatra formatting
        $nakData = $panchangaStability['nakshatra'] ?? [];
        $nakId = $nakData['id'] ?? ($kundli['planets']['moon']['nakshatra']['id'] ?? null);
        $nakRawName = $nakData['name'] ?? ($kundli['planets']['moon']['nakshatra']['name'] ?? '—');
        $nakName = $this->localizeNakshatra($nakId ?? $nakRawName, $locale);
        $padaData = $panchangaStability['pada'] ?? [];
        $padaNum = (int) ($padaData['pada'] ?? ($kundli['planets']['moon']['nakshatra']['pada'] ?? 1));
        $nakDisplay = $nakName !== '—'
            ? ($nakName . ($locale === 'hi' ? " (चरण {$padaNum})" : " (Pada {$padaNum})"))
            : '—';

        // Yoga formatting
        $yogaData = $panchangaStability['yoga'] ?? [];
        $yogaId = $yogaData['id'] ?? null;
        $yogaName = $this->localizeYoga($yogaId ?? ($yogaData['name'] ?? null), $locale);

        // Karana formatting
        $karanaData = $panchangaStability['karana'] ?? [];
        $karanaId = $karanaData['id'] ?? null;
        $karanaName = $this->localizeKarana($karanaId ?? ($karanaData['name'] ?? null), $locale);

        // Sun times formatting
        $sunTimes = $panchangaStability['sun_times'] ?? [];
        $sunriseStr = !empty($sunTimes['sunrise'])
            ? CarbonImmutable::parse($sunTimes['sunrise'])->format('h:i A')
            : '—';
        $sunsetStr = !empty($sunTimes['sunset'])
            ? CarbonImmutable::parse($sunTimes['sunset'])->format('h:i A')
            : '—';

        // Avakahada Chakra derivation
        $moonRashiId = (int) ($kundli['planets']['moon']['rashi']['id'] ?? 1);
        $moonNakId = (int) ($nakData['id'] ?? ($kundli['planets']['moon']['nakshatra']['id'] ?? 1));
        $avakahada = $this->computeAvakahada($moonRashiId, $moonNakId, $locale);

        return [
            'gate_b_status' => $gateBStatus,
            'title' => $locale === 'hi' ? 'पंचांग एवं अवकहड़ा चक्र' : 'Panchanga & Avakahada Chakra',
            'has_transition' => $hasTransition,
            'panchanga' => [
                'tithi' => array_merge($tithiData, ['name' => $tithiName]),
                'vara' => array_merge($vaaraData, ['name' => $vaaraName]),
                'nakshatra' => array_merge($nakData, ['name' => $nakDisplay]),
                'yoga' => array_merge($yogaData, ['name' => $yogaName]),
                'karana' => array_merge($karanaData, ['name' => $karanaName]),
                'sunrise' => $sunriseStr,
                'sunset' => $sunsetStr,
            ],
            'avakahada' => $avakahada,
            'nadi_disclaimer' => DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER[$locale],
            'transition_notes' => $hasTransition
                ? ($locale === 'hi'
                    ? 'जन्म समय में अनिश्चितता के कारण पंचांग के कुछ अंगों में संक्रांति (परिवर्तन) पाई गई है। संभावित अंग कोष्ठक में दर्शाए गए हैं।'
                    : 'Due to birth time uncertainty, one or more Panchanga limbs cross a boundary during the uncertainty interval. Transitioning limbs are explicitly noted.')
                : null,
        ];
    }

    /**
     * Section 3: Lagna Kundli (D-1) & Planetary Table.
     */
    protected function buildSection3LagnaKundli(array $kundli, array $stability, string $locale): array
    {
        $d1Asc = $stability['ascendant']['d1_ascendant'] ?? ($stability['d1_ascendant'] ?? null);
        $isInvariant = !empty($d1Asc['is_invariant']) && ($d1Asc['status'] ?? '') === 'confirmed';

        if (!$isInvariant) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'लग्न कुण्डली (D-1) एवं ग्रह स्थिति' : 'Lagna Kundli (D-1) & Planetary Table',
                'is_suppressed' => true,
                'svg' => null,
                'reason' => $locale === 'hi'
                    ? 'अनिश्चित अथवा अनुमानित जन्म समय के कारण लग्न राशि अस्थिर है। गणितीय मर्यादा की रक्षा हेतु लग्न कुण्डली को स्थगित किया गया है। विश्लेषण चंद्र कुंडली पर आधारित है।'
                    : 'The D-1 Ascendant crosses sign boundaries across the recorded uncertainty interval. To preserve mathematical integrity, the D-1 chart and house-dependent interpretations are suppressed; interpretations rely on the Chandra Kundli.',
                'planets' => [],
            ];
        }

        $lagnaRashiId = (int) ($kundli['ascendant']['id'] ?? ($d1Asc['id'] ?? 1));

        // Format planetary rows with dignity and Nakshatra
        $planets = [];
        foreach ($kundli['planets'] ?? [] as $key => $p) {
            $dignity = $p['dignity'] ?? 'neutral';
            $planetRashiId = (int) ($p['rashi']['id'] ?? 1);
            $houseNum = (($planetRashiId - $lagnaRashiId + 12) % 12) + 1;

            $planets[] = [
                'key' => $key,
                'name' => $locale === 'hi' ? ($p['name_hi'] ?? $p['name']) : $p['name'],
                'longitude' => $p['longitude_dms'] ?? round($p['longitude'] ?? 0.0, 2) . '°',
                'rashi' => $this->localizeZodiac($planetRashiId, $locale),
                'house' => $houseNum,
                'dignity' => $this->interpretationService->getDignityInterpretation($key, $dignity, $locale),
                'nakshatra' => $this->localizeNakshatra($p['nakshatra']['name'] ?? '', $locale),
                'pada' => $p['nakshatra']['pada'] ?? 1,
                'is_retrograde' => !empty($p['is_retrograde']),
                'is_combust' => !empty($p['is_combust']),
            ];
        }

        $ascDms = $d1Asc['degrees_dms'] ?? ($kundli['ascendant']['degrees_dms'] ?? null);
        $svg = $this->svgRenderer->renderD1($kundli['houses'] ?? [], $locale, $ascDms);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'लग्न कुण्डली (D-1) एवं ग्रह स्थिति' : 'Lagna Kundli (D-1) & Planetary Table',
            'is_suppressed' => false,
            'svg' => $svg,
            'ascendant' => [
                'name' => $this->localizeZodiac($d1Asc['name'] ?? ($kundli['ascendant']['name'] ?? $lagnaRashiId), $locale),
                'longitude' => $ascDms ?? '',
                'nakshatra' => $this->localizeNakshatra($d1Asc['nakshatra']['name'] ?? '', $locale),
                'pada' => $d1Asc['nakshatra']['pada'] ?? 1,
            ],
            'planets' => $planets,
        ];
    }

    /**
     * Section 4: Chandra Kundli (Moon Chart).
     */
    protected function buildSection4ChandraKundli(array $kundli, array $stability, string $locale): array
    {
        $panchangaLimb = $stability['panchanga']['moon_sign'] ?? null;
        $isMoonInvariant = $panchangaLimb === null || !empty($panchangaLimb['is_invariant']);

        if (!$isMoonInvariant) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'चन्द्र कुण्डली' : 'Chandra Kundli (Moon Chart)',
                'is_suppressed' => true,
                'svg' => null,
                'reason' => $locale === 'hi'
                    ? 'चंद्र राशि में जन्म समय की अनिश्चितता के कारण परिवर्तन संभव है, अतः चंद्र कुंडली को स्थगित किया गया है।'
                    : 'The natal Moon crosses sign boundaries across the uncertainty interval; Chandra Kundli is suppressed.',
            ];
        }

        $chandraHouses = $kundli['chandra_kundli']['houses'] ?? [];
        $svg = $this->svgRenderer->renderChandra($chandraHouses, $locale);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'चन्द्र कुण्डली (मन एवं चेतना)' : 'Chandra Kundli (Moon Chart — Mind & Consciousness)',
            'is_suppressed' => false,
            'svg' => $svg,
            'summary' => $locale === 'hi'
                ? 'चन्द्र कुण्डली मानसिक प्रवृत्तियों, मनोवैज्ञानिक संतुलन और भावनात्मक प्रतिक्रियाओं का सूक्ष्म दर्पण प्रस्तुत करती है।'
                : 'Chandra Kundli reflects psychological stability, emotional inclinations, and the mental lens through which life experiences are processed.',
        ];
    }

    /**
     * Section 5: D-9 Navamsha Chart & Vargottama.
     */
    protected function buildSection5Navamsha(array $kundli, array $stability, string $locale): array
    {
        $d9Planets = $stability['navamsha'] ?? ($stability['planetary_navamsha'] ?? []);
        $d9Asc = $stability['ascendant']['d9_ascendant'] ?? ($stability['d9_ascendant'] ?? null);

        $vargottamaList = [];
        foreach ($d9Planets as $key => $p) {
            if (!empty($p['is_vargottama'])) {
                $vargottamaList[] = [
                    'key' => $key,
                    'name' => $locale === 'hi' ? ($p['name_hi'] ?? $p['name']) : $p['name'],
                    'rashi' => $this->localizeZodiac($p['invariant_d9_sign'] ?? ($p['d1_sign']['name'] ?? ''), $locale),
                ];
            }
        }

        $svg = $this->svgRenderer->renderD9($d9Planets, $d9Asc, $locale);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'नवांश कुण्डली (D-9) एवं वर्गोत्तम ग्रह' : 'D-9 Navamsha Chart & Vargottama Status',
            'is_suppressed' => false,
            'svg' => $svg,
            'd9_ascendant_invariant' => !empty($d9Asc['is_invariant']),
            'vargottama_planets' => $vargottamaList,
            'note' => empty($d9Asc['is_invariant'])
                ? ($locale === 'hi'
                    ? 'नवांश लग्न जन्म समय की सूक्ष्म अनिश्चितता के कारण परिवर्तनशील है। अतः नवांश चक्र को राशि-वितरण (Sign Distribution) के रूप में बिना लग्न चिन्ह के प्रस्तुत किया गया है।'
                    : 'The D-9 Ascendant is volatile across the birth uncertainty window. The chart is rendered as an unrotated sign distribution highlighting verified planetary placements.')
                : null,
        ];
    }

    /**
     * Section 6: Parashari Graha Drishti Matrix.
     */
    protected function buildSection6Aspects(array $aspects, string $precision, string $locale): array
    {
        $matrix = $aspects['matrix'] ?? [];
        $hideHouseLabels = ($precision === UserAstrologyProfile::PRECISION_UNKNOWN);

        $aspectList = [];
        foreach ($matrix as $sourcePlanet => $targets) {
            // Strictly exclude Rahu/Ketu
            if (in_array(strtolower($sourcePlanet), ['rahu', 'ketu'], true)) {
                continue;
            }

            foreach ($targets as $targetPlanet => $info) {
                if (in_array(strtolower($targetPlanet), ['rahu', 'ketu'], true)) {
                    continue;
                }

                if (!empty($info['has_aspect'])) {
                    $srcName = $this->localizePlanet($sourcePlanet, $locale);
                    $tgtName = $this->localizePlanet($targetPlanet, $locale);
                    $aspectList[] = [
                        'source' => $srcName,
                        'target' => $tgtName,
                        'type' => $info['aspect_type'] ?? '7th_mutual',
                        'strength' => $info['aspect_strength_percent'] ?? 100,
                        'source_house' => $hideHouseLabels ? null : ($info['source_house'] ?? null),
                        'target_house' => $hideHouseLabels ? null : ($info['target_house'] ?? null),
                        'description' => $locale === 'hi'
                            ? $srcName . ' की ' . $tgtName . ' पर पूर्ण दृष्टि'
                            : $srcName . ' casts full Parashari aspect upon ' . $tgtName,
                    ];
                }
            }
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'पराशरी ग्रह दृष्टि मैट्रिक्स' : 'Parashari Graha Drishti Matrix',
            'is_suppressed' => false,
            'hide_house_labels' => $hideHouseLabels,
            'aspects' => $aspectList,
            'rule_clarification' => $locale === 'hi'
                ? 'शास्त्रीय पराशरी सिद्धांत के अनुसार दृष्टि केवल 7 स्थूल ग्रहों (सूर्य, चंद्र, मंगल, बुध, गुरु, शुक्र, शनि) पर लागू होती है। राहु-केतु छाया ग्रह होने के कारण दृष्टि मैट्रिक्स से बहिष्कृत हैं।'
                : 'Under Parashari principles, Graha Drishti applies strictly to the 7 visible planets. Rahu and Ketu are mathematical shadow nodes and are excluded from the aspect matrix.',
        ];
    }

    /**
     * Section 7: Janma Nakshatra Deep Dive.
     */
    protected function buildSection7JanmaNakshatra(array $kundli, array $stability, string $locale): array
    {
        $nakshatraLimb = $stability['panchanga']['nakshatra'] ?? null;
        $isInvariant = $nakshatraLimb === null || !empty($nakshatraLimb['is_invariant']);

        $nakName = (string) ($nakshatraLimb['name'] ?? ($kundli['planets']['moon']['nakshatra']['name'] ?? 'Ashwini'));
        $pada = (int) ($stability['panchanga']['pada']['pada'] ?? ($kundli['planets']['moon']['nakshatra']['pada'] ?? 1));

        $deepDive = $this->interpretationService->getNakshatraDeepDive($nakName, $pada, $locale);

        return [
            'gate_b_status' => $isInvariant ? 'certified' : 'qualified',
            'title' => $locale === 'hi' ? 'जन्म नक्षत्र सूक्ष्म विश्लेषण' : 'Janma Nakshatra In-Depth Profile',
            'is_suppressed' => false,
            'nakshatra_name' => $this->localizeNakshatra($nakName, $locale),
            'pada' => $pada,
            'is_invariant' => $isInvariant,
            'deity' => $deepDive['deity'],
            'symbol' => $deepDive['symbol'],
            'nature' => $deepDive['nature'],
            'guidance' => $deepDive['guidance'],
            'pada_note' => $deepDive['pada_note'],
        ];
    }

    /**
     * Section 8: Vimshottari Mahadasha Timeline.
     */
    protected function buildSection8VimshottariTimeline(array $dashaData, string $locale): array
    {
        $mahadashas = $dashaData['mahadashas'] ?? [];
        if (empty($mahadashas)) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'विंशोत्तरी महादशा कालचक्र' : 'Vimshottari Mahadasha Timeline (120 Years)',
                'is_suppressed' => true,
                'timeline' => [],
            ];
        }

        $timeline = [];
        foreach ($mahadashas as $m) {
            $theme = $this->interpretationService->getMahadashaTheme($m['planet'], $locale);
            $timeline[] = [
                'planet' => $m['planet'],
                'name' => $this->localizePlanet($m['planet'] ?? ($m['name'] ?? ''), $locale),
                'nominal_start' => $m['start_date'],
                'nominal_end' => $m['end_date'],
                'date_bounds' => [
                    'earliest_start' => $m['uncertainty_bounds']['earliest_start'] ?? $m['start_date'],
                    'latest_start' => $m['uncertainty_bounds']['latest_start'] ?? $m['start_date'],
                    'earliest_end' => $m['uncertainty_bounds']['earliest_end'] ?? $m['end_date'],
                    'latest_end' => $m['uncertainty_bounds']['latest_end'] ?? $m['end_date'],
                ],
                'is_current' => !empty($m['is_current']),
                'theme' => $theme['theme'],
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'विंशोत्तरी महादशा कालचक्र (120 वर्ष)' : 'Vimshottari Mahadasha Timeline (120-Year Cycle)',
            'is_suppressed' => false,
            'timeline' => $timeline,
        ];
    }

    /**
     * Section 9: Current Running Dasha & Pratyantardasha.
     */
    protected function buildSection9CurrentDasha(array $dashaData, string $locale): array
    {
        $current = $dashaData['current_dasha'] ?? [];
        $activeMaha = $current['mahadasha'] ?? null;
        $activeAntar = $current['antardasha'] ?? null;
        $activePrat = $current['pratyantardasha'] ?? null;

        if (!$activeMaha) {
            return [
                'gate_b_status' => 'suppressed',
                'title' => $locale === 'hi' ? 'वर्तमान दशा विवरण' : 'Current Running Dasha & Pratyantardasha',
                'is_suppressed' => true,
                'reason' => $locale === 'hi'
                    ? 'जन्म समय अनिश्चितता के कारण वर्तमान दशा अवधि का निर्धारण संभव नहीं है।'
                    : 'Current dasha period is unavailable due to birth uncertainty bounds.',
            ];
        }

        // Apply Gate B period-boundary reliability rule: W_period <= 0.25 * D_Prat
        $isPratReliable = !empty($activePrat['is_reliable']) && empty($activePrat['is_suppressed']);

        return [
            'gate_b_status' => $isPratReliable ? 'certified' : 'qualified',
            'title' => $locale === 'hi' ? 'वर्तमान सक्रिय दशा एवं अंतर्दशा' : 'Current Running Dasha & Sub-Periods',
            'is_suppressed' => false,
            'mahadasha' => [
                'name' => $this->localizePlanet($activeMaha['planet'] ?? ($activeMaha['name'] ?? ''), $locale),
                'start_date' => $activeMaha['start_date'],
                'end_date' => $activeMaha['end_date'],
            ],
            'antardasha' => $activeAntar ? [
                'name' => $this->localizePlanet($activeAntar['planet'] ?? ($activeAntar['name'] ?? ''), $locale),
                'start_date' => $activeAntar['start_date'],
                'end_date' => $activeAntar['end_date'],
            ] : null,
            'pratyantardasha' => $isPratReliable ? [
                'name' => $this->localizePlanet($activePrat['planet'] ?? ($activePrat['name'] ?? ''), $locale),
                'start_date' => $activePrat['start_date'],
                'end_date' => $activePrat['end_date'],
                'uncertainty_ratio' => $activePrat['uncertainty_ratio'] ?? null,
            ] : null,
            'pratyantardasha_suppressed' => !$isPratReliable,
            'suppression_note' => !$isPratReliable
                ? ($locale === 'hi'
                    ? 'प्रत्यंतर्दशा की अवधि-सीमा अनिश्चितता अनुपात (W_period / D_Prat) 25% सीमा से अधिक होने के कारण प्रत्यंतर्दशा को स्थगित रखा गया है।'
                    : 'Pratyantardasha is suppressed in accordance with Gate B mathematical reliability threshold (W_period <= 0.25 * D_Prat).')
                : null,
        ];
    }

    /**
     * Section 10: Classical Planetary Yogas.
     */
    protected function buildSection10ClassicalYogas(array $yogas, string $d1GateBStatus, string $locale): array
    {
        $isD1Suppressed = ($d1GateBStatus === 'suppressed');

        $activeYogas = [];

        // Panch Mahapurusha Yogas (Lagna-dependent)
        if (!$isD1Suppressed && !empty($yogas['panch_mahapurusha'])) {
            foreach ($yogas['panch_mahapurusha'] as $yogaKey => $yData) {
                if (!empty($yData['is_present'])) {
                    $interp = $this->interpretationService->getYogaInterpretation($yogaKey, $yData, $locale);
                    $activeYogas[] = [
                        'type' => 'panch_mahapurusha',
                        'name' => $interp['name'],
                        'definition' => $interp['definition'],
                        'qualities' => $interp['qualities'],
                        'ethical_advice' => $interp['ethical_advice'],
                    ];
                }
            }
        }

        // Gaja Kesari Yoga (Moon-dependent)
        if (!empty($yogas['gaja_kesari']['is_present'])) {
            $interp = $this->interpretationService->getYogaInterpretation('gajakesari', $yogas['gaja_kesari'], $locale);
            $activeYogas[] = [
                'type' => 'moon_kendra',
                'name' => $interp['name'],
                'definition' => $interp['definition'],
                'qualities' => $interp['qualities'],
                'ethical_advice' => $interp['ethical_advice'],
            ];
        }

        // Budhaditya Yoga (Conjunction)
        if (!empty($yogas['budhaditya']['is_present'])) {
            $interp = $this->interpretationService->getYogaInterpretation('budhaditya', $yogas['budhaditya'], $locale);
            $activeYogas[] = [
                'type' => 'conjunction',
                'name' => $interp['name'],
                'definition' => $interp['definition'],
                'qualities' => $interp['qualities'],
                'ethical_advice' => $interp['ethical_advice'],
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'शास्त्रीय ग्रहीय योग' : 'Classical Planetary Yogas',
            'is_suppressed' => false,
            'is_lagna_yogas_suppressed' => $isD1Suppressed,
            'active_yogas' => $activeYogas,
            'note' => $isD1Suppressed
                ? ($locale === 'hi'
                    ? 'लग्न की अनिश्चितता के कारण केंद्र-आधारित पंच महापुरुष योगों का परीक्षण स्थगित रखा गया है; केवल चंद्र-आधारित योग दर्शाए गए हैं।'
                    : 'Lagna-dependent Kendra yogas are suppressed due to Ascendant uncertainty; Moon-based configurations are displayed.')
                : null,
        ];
    }

    /**
     * Section 11: Manglik Dosha Audit.
     */
    protected function buildSection11ManglikDosha(array $manglikData, string $locale): array
    {
        $interp = $this->interpretationService->getManglikInterpretation($manglikData, $locale);

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'मांगलिक दोष शास्त्रीय परीक्षण' : 'Manglik Dosha Classical Audit',
            'is_suppressed' => false,
            'has_manglik_dosha' => !empty($manglikData['has_manglik_dosha']),
            'is_cancelled' => !empty($manglikData['is_cancelled']),
            'headline' => $interp['headline'],
            'summary' => $interp['summary'],
            'cancellation_note' => $interp['cancellation_note'],
            'ethical_guidance' => $interp['ethical_guidance'],
            'bhangas' => $manglikData['bhangas'] ?? [],
            'mitigations' => $manglikData['mitigations'] ?? [],
        ];
    }

    /**
     * Section 12: Saturn Sade Sati & Dhaiya.
     */
    protected function buildSection12SaturnTransit(array $transitData, string $locale): array
    {
        $status = $transitData['status'] ?? 'fallback';
        $isFallback = ($status === 'fallback');

        $currentSummary = $transitData['current_transit_summary'] ?? ($transitData['qualitative_fallback'] ?? []);

        $phaseKey = 'none';
        if (!empty($currentSummary['is_in_sade_sati'])) {
            $phaseKey = match ($currentSummary['sade_sati_phase'] ?? '') {
                'rising_phase_12th' => 'rising',
                'peak_phase_1st' => 'peak',
                'setting_phase_2nd' => 'setting',
                default => 'rising',
            };
        } elseif (!empty($currentSummary['is_in_dhaiya'])) {
            $phaseKey = match ($currentSummary['dhaiya_type'] ?? '') {
                'kantaka_dhaiya_4th' => 'kantaka',
                'ashtama_dhaiya_8th' => 'ashtama',
                default => 'kantaka',
            };
        }

        $isRetro = !empty($currentSummary['saturn_position']['is_retrograde'] ?? ($currentSummary['saturn_is_retrograde'] ?? false));

        $interp = $this->interpretationService->getSaturnTransitInterpretation($phaseKey, $isRetro, $locale);

        return [
            'gate_b_status' => $isFallback ? 'fallback' : 'certified',
            'title' => $locale === 'hi' ? 'शनि साढ़ेसाती एवं ढैय्या गोचर' : 'Saturn Sade Sati & Dhaiya Transits',
            'is_suppressed' => false,
            'is_fallback' => $isFallback,
            'current_phase_title' => $interp['title'],
            'current_phase_core' => $interp['core'],
            'current_phase_advice' => $interp['advice'],
            'sade_sati_cycles' => $transitData['sade_sati'] ?? [],
            'dhaiya_cycles' => $transitData['dhaiya'] ?? [],
            'fallback_note' => $isFallback
                ? ($locale === 'hi'
                    ? 'चंद्र राशि की अनिश्चितता के कारण काल-विभाजित सारणी के स्थान पर वर्तमान गोचर का स्वीकृत गुणात्मक विश्लेषण प्रस्तुत किया गया है।'
                    : 'Natal Moon uncertainty requires the approved qualitative current-phase fallback rather than dated multi-year timelines.')
                : null,
        ];
    }

    /**
     * Section 13: Classical Vedic Remedies (Upay).
     */
    protected function buildSection13Remedies(array $kundli, array $yogas, array $sec11, string $locale): array
    {
        // Select planetary focus: if active Manglik without cancellation, include Mars; otherwise Lagna/Moon lord
        $focusPlanets = ['jupiter'];
        if (!empty($sec11['has_manglik_dosha']) && empty($sec11['is_cancelled'])) {
            $focusPlanets[] = 'mars';
        }
        $focusPlanets[] = 'saturn';

        $remedies = [];
        foreach (array_unique($focusPlanets) as $fp) {
            $r = $this->interpretationService->getVedicRemedies($fp, $locale);
            $remedies[] = [
                'planet' => $this->localizePlanet($fp, $locale),
                'mantra' => $r['mantra'],
                'dana' => $r['dana'],
                'lifestyle' => $r['lifestyle'],
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'शास्त्रीय वैदिक उपाय एवं सात्विक जीवन' : 'Classical Vedic Remedies & Mindful Living',
            'is_suppressed' => false,
            'remedies' => $remedies,
            'gemstone_disclaimer' => DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER[$locale],
        ];
    }

    /**
     * Section 14: Astrologer Referral.
     */
    protected function buildSection14AstrologerReferral(array $astrologers, string $locale): array
    {
        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'प्रमाणित वैदिक ज्योतिषी परामर्श' : 'Verified Astrologer Consultation',
            'is_suppressed' => false,
            'summary' => $locale === 'hi'
                ? 'जटिल ग्रहीय योगों, जन्म समय सुधार (BTR) और व्यक्तिगत जीवन मार्गदर्शन हेतु हमारे अनुभवी वैदिक ज्योतिषियों से परामर्श लें।'
                : 'For in-depth horoscope analysis, birth-time rectification (BTR), or customized remedies, connect with our verified astrologers.',
            'astrologers' => $astrologers,
        ];
    }

    /**
     * Section 15: Precision Audit & Disclaimers.
     */
    protected function buildSection15PrecisionAudit(array $auditMatrix, string $precision, string $locale): array
    {
        $sectionNames = [
            'sec1_cover' => ['en' => '1. Cover & Coordinates', 'hi' => '१. मुखपृष्ठ एवं जन्म विवरण'],
            'sec2_panchanga' => ['en' => '2. Panchanga & Avakahada', 'hi' => '२. पंचांग एवं अवकहड़ा चक्र'],
            'sec3_lagna_d1' => ['en' => '3. Lagna Kundli (D-1)', 'hi' => '३. लग्न कुण्डली (D-1)'],
            'sec4_chandra' => ['en' => '4. Chandra Kundli (Moon)', 'hi' => '४. चन्द्र कुण्डली'],
            'sec5_navamsha_d9' => ['en' => '5. D-9 Navamsha Chart', 'hi' => '५. नवांश कुण्डली (D-9)'],
            'sec6_aspects' => ['en' => '6. Parashari Drishti Matrix', 'hi' => '६. पराशरी दृष्टि मैट्रिक्स'],
            'sec7_nakshatra' => ['en' => '7. Janma Nakshatra Profile', 'hi' => '७. जन्म नक्षत्र सूक्ष्म विश्लेषण'],
            'sec8_vimshottari_timeline' => ['en' => '8. Vimshottari Timeline', 'hi' => '८. विंशोत्तरी महादशा कालचक्र'],
            'sec9_current_dasha' => ['en' => '9. Current Running Dasha', 'hi' => '९. वर्तमान दशा एवं अंतर्दशा'],
            'sec10_classical_yogas' => ['en' => '10. Classical Planetary Yogas', 'hi' => '१०. शास्त्रीय ग्रहीय योग'],
            'sec11_manglik_dosha' => ['en' => '11. Manglik Dosha Audit', 'hi' => '११. मांगलिक दोष परीक्षण'],
            'sec12_saturn_transit' => ['en' => '12. Saturn Sade Sati & Dhaiya', 'hi' => '१२. शनि साढ़ेसाती एवं गोचर'],
            'sec13_remedies' => ['en' => '13. Classical Vedic Remedies', 'hi' => '१३. शास्त्रीय वैदिक उपाय'],
            'sec14_astrologer_referral' => ['en' => '14. Astrologer Referral', 'hi' => '१४. ज्योतिषी परामर्श'],
            'sec15_precision_audit' => ['en' => '15. Precision Audit & Disclaimers', 'hi' => '१५. परिशुद्धता ऑडिट एवं अस्वीकरण'],
        ];

        $statusLabels = [
            'certified' => ['en' => 'Certified', 'hi' => 'प्रमाणित'],
            'qualified' => ['en' => 'Qualified', 'hi' => 'सशर्त'],
            'suppressed' => ['en' => 'Suppressed', 'hi' => 'स्थगित'],
            'fallback' => ['en' => 'Qualitative Fallback', 'hi' => 'गुणात्मक विश्लेषण'],
        ];

        $table = [];
        foreach ($auditMatrix as $secKey => $status) {
            $table[] = [
                'section' => $sectionNames[$secKey][$locale] ?? $secKey,
                'status_key' => $status,
                'status_label' => $statusLabels[$status][$locale] ?? ucfirst($status),
            ];
        }

        return [
            'gate_b_status' => 'certified',
            'title' => $locale === 'hi' ? 'परिशुद्धता ऑडिट एवं विधिक अस्वीकरण' : 'Precision Audit & Legal Disclaimers',
            'precision_tier' => $precision,
            'audit_table' => $table,
            'purushartha_framing' => DetailedInterpretationService::PURUSHARTHA_FRAMING[$locale],
            'nadi_disclaimer' => DetailedInterpretationService::NADI_NON_MEDICAL_DISCLAIMER[$locale],
            'gemstone_disclaimer' => DetailedInterpretationService::GEMSTONE_ALGORITHMIC_DISCLAIMER[$locale],
            'general_disclaimer' => DetailedInterpretationService::GENERAL_DISCLAIMER[$locale],
        ];
    }
}
