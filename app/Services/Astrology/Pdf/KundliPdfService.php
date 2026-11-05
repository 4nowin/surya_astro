<?php

declare(strict_types=1);

namespace App\Services\Astrology\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Service for rendering high quality, branded Vedic Janam Kundli PDF reports.
 *
 * Implements M6 of the Astro Setu Product Roadmap with M9.2 typography and chart standards.
 * Supports primary rendering via headless Chromium with HarfBuzz complex-script shaping,
 * with controlled fallback to Dompdf with embedded Noto Sans Devanagari fonts.
 */
class KundliPdfService
{
    /**
     * Canonical 12 Zodiac signs in English and Hindi.
     */
    public const ZODIAC_MAP = [
        1  => ['en' => 'Aries',       'hi' => 'मेष'],
        2  => ['en' => 'Taurus',      'hi' => 'वृषभ'],
        3  => ['en' => 'Gemini',      'hi' => 'मिथुन'],
        4  => ['en' => 'Cancer',      'hi' => 'कर्क'],
        5  => ['en' => 'Leo',         'hi' => 'सिंह'],
        6  => ['en' => 'Virgo',       'hi' => 'कन्या'],
        7  => ['en' => 'Libra',       'hi' => 'तुला'],
        8  => ['en' => 'Scorpio',     'hi' => 'वृश्चिक'],
        9  => ['en' => 'Sagittarius', 'hi' => 'धनु'],
        10 => ['en' => 'Capricorn',   'hi' => 'मकर'],
        11 => ['en' => 'Aquarius',    'hi' => 'कुम्भ'],
        12 => ['en' => 'Pisces',      'hi' => 'मीन'],
    ];

    /**
     * Canonical 27 Nakshatras in English and Hindi.
     */
    public const NAKSHATRA_MAP = [
        1  => ['en' => 'Ashwini',           'hi' => 'अश्विनी'],
        2  => ['en' => 'Bharani',           'hi' => 'भरणी'],
        3  => ['en' => 'Krittika',          'hi' => 'कृत्तिका'],
        4  => ['en' => 'Rohini',            'hi' => 'रोहिणी'],
        5  => ['en' => 'Mrigashira',        'hi' => 'मृगशिरा'],
        6  => ['en' => 'Ardra',             'hi' => 'आर्द्रा'],
        7  => ['en' => 'Punarvasu',         'hi' => 'पुनर्वसु'],
        8  => ['en' => 'Pushya',            'hi' => 'पुष्य'],
        9  => ['en' => 'Ashlesha',          'hi' => 'आश्लेषा'],
        10 => ['en' => 'Magha',             'hi' => 'मघा'],
        11 => ['en' => 'Purva Phalguni',    'hi' => 'पूर्वा फाल्गुनी'],
        12 => ['en' => 'Uttara Phalguni',   'hi' => 'उत्तरा फाल्गुनी'],
        13 => ['en' => 'Hasta',             'hi' => 'हस्त'],
        14 => ['en' => 'Chitra',            'hi' => 'चित्रा'],
        15 => ['en' => 'Swati',             'hi' => 'स्वाति'],
        16 => ['en' => 'Vishakha',          'hi' => 'विशाखा'],
        17 => ['en' => 'Anuradha',          'hi' => 'अनुराधा'],
        18 => ['en' => 'Jyeshtha',          'hi' => 'ज्येष्ठा'],
        19 => ['en' => 'Mula',              'hi' => 'मूल'],
        20 => ['en' => 'Purva Ashadha',     'hi' => 'पूर्वाषाढ़ा'],
        21 => ['en' => 'Uttara Ashadha',    'hi' => 'उत्तराषाढ़ा'],
        22 => ['en' => 'Shravana',          'hi' => 'श्रवण'],
        23 => ['en' => 'Dhanishta',         'hi' => 'धनिष्ठा'],
        24 => ['en' => 'Shatabhisha',       'hi' => 'शतभिषा'],
        25 => ['en' => 'Purva Bhadrapada',  'hi' => 'पूर्वा भाद्रपद'],
        26 => ['en' => 'Uttara Bhadrapada', 'hi' => 'उत्तरा भाद्रपद'],
        27 => ['en' => 'Revati',            'hi' => 'रेवती'],
    ];

    /**
     * Canonical Nine Grahas + Ascendant in English and Hindi.
     */
    public const PLANET_MAP = [
        'sun'       => ['en' => 'Sun',       'hi' => 'सूर्य'],
        'moon'      => ['en' => 'Moon',      'hi' => 'चन्द्र'],
        'mars'      => ['en' => 'Mars',      'hi' => 'मंगल'],
        'mercury'   => ['en' => 'Mercury',   'hi' => 'बुध'],
        'jupiter'   => ['en' => 'Jupiter',   'hi' => 'बृहस्पति'],
        'venus'     => ['en' => 'Venus',     'hi' => 'शुक्र'],
        'saturn'    => ['en' => 'Saturn',    'hi' => 'शनि'],
        'rahu'      => ['en' => 'Rahu',      'hi' => 'राहु'],
        'ketu'      => ['en' => 'Ketu',      'hi' => 'केतु'],
        'ascendant' => ['en' => 'Ascendant', 'hi' => 'लग्न'],
    ];

    /**
     * Planetary Dignity mapping.
     */
    public const DIGNITY_MAP = [
        'neutral'      => ['en' => 'Neutral',      'hi' => 'सम'],
        'own_sign'     => ['en' => 'Own Sign',     'hi' => 'स्वराशि'],
        'own'          => ['en' => 'Own Sign',     'hi' => 'स्वराशि'],
        'exalted'      => ['en' => 'Exalted',      'hi' => 'उच्च'],
        'debilitated'  => ['en' => 'Debilitated',  'hi' => 'नीच'],
        'friendly'     => ['en' => 'Friend',       'hi' => 'मित्र'],
        'friend'       => ['en' => 'Friend',       'hi' => 'मित्र'],
        'enemy'        => ['en' => 'Enemy',        'hi' => 'शत्रु'],
        'moolatrikona' => ['en' => 'Moolatrikona', 'hi' => 'मूलत्रिकोण'],
    ];

    /**
     * Nakshatra Gana mapping.
     */
    public const GANA_MAP = [
        'deva'     => ['en' => 'Deva',     'hi' => 'देव'],
        'manushya' => ['en' => 'Manushya', 'hi' => 'मनुष्य'],
        'rakshasa' => ['en' => 'Rakshasa', 'hi' => 'राक्षस'],
    ];

    /**
     * House text anchor coordinates on a 400x400 SVG canvas (legacy compatibility).
     */
    public const HOUSE_COORDINATES = [
        1  => ['rashi_x' => 200, 'rashi_y' => 60,  'planets_x' => 200, 'planets_y' => 110],
        2  => ['rashi_x' => 100, 'rashi_y' => 35,  'planets_x' => 90,  'planets_y' => 65],
        3  => ['rashi_x' => 45,  'rashi_y' => 90,  'planets_x' => 50,  'planets_y' => 125],
        4  => ['rashi_x' => 115, 'rashi_y' => 180, 'planets_x' => 100, 'planets_y' => 220],
        5  => ['rashi_x' => 45,  'rashi_y' => 280, 'planets_x' => 50,  'planets_y' => 315],
        6  => ['rashi_x' => 100, 'rashi_y' => 370, 'planets_x' => 90,  'planets_y' => 340],
        7  => ['rashi_x' => 200, 'rashi_y' => 340, 'planets_x' => 200, 'planets_y' => 280],
        8  => ['rashi_x' => 300, 'rashi_y' => 370, 'planets_x' => 310, 'planets_y' => 340],
        9  => ['rashi_x' => 355, 'rashi_y' => 280, 'planets_x' => 350, 'planets_y' => 315],
        10 => ['rashi_x' => 285, 'rashi_y' => 180, 'planets_x' => 300, 'planets_y' => 220],
        11 => ['rashi_x' => 355, 'rashi_y' => 90,  'planets_x' => 350, 'planets_y' => 125],
        12 => ['rashi_x' => 300, 'rashi_y' => 35,  'planets_x' => 310, 'planets_y' => 65],
    ];

    public function __construct(
        protected ?PremiumKundliPdfService $premiumPdfService = null,
        protected ?SvgVargaRenderer $svgRenderer = null
    ) {
        $this->premiumPdfService ??= app(PremiumKundliPdfService::class);
        $this->svgRenderer ??= app(SvgVargaRenderer::class);
    }

    /**
     * Localize zodiac sign names deterministically.
     */
    public function localizeZodiac(string|int|null $sign, string $locale = 'en'): string
    {
        if ($sign === null || $sign === '') {
            return '-';
        }

        if (is_int($sign) || (is_string($sign) && ctype_digit($sign))) {
            $id = (int) $sign;
            if ($id >= 1 && $id <= 12) {
                return $locale === 'hi' ? self::ZODIAC_MAP[$id]['hi'] : self::ZODIAC_MAP[$id]['en'];
            }
        }

        $str = trim((string) $sign);
        $lower = strtolower($str);

        foreach (self::ZODIAC_MAP as $item) {
            if (strtolower($item['en']) === $lower || strtolower($item['hi']) === $lower) {
                return $locale === 'hi' ? $item['hi'] : $item['en'];
            }
        }

        // Common Sanskrit/Vedic transliterations
        $vedicSignMap = [
            'mesha' => 'मेष', 'vrishabha' => 'वृषभ', 'mithuna' => 'मिथुन', 'karka' => 'कर्क',
            'simha' => 'सिंह', 'kanya' => 'कन्या', 'tula' => 'तुला', 'vrishchika' => 'वृश्चिक',
            'dhanu' => 'धनु', 'makara' => 'मकर', 'kumbha' => 'कुम्भ', 'meena' => 'मीन',
        ];

        if ($locale === 'hi' && isset($vedicSignMap[$lower])) {
            return $vedicSignMap[$lower];
        }

        return $str;
    }

    /**
     * Localize Nakshatra names deterministically across all 27 nakshatras.
     */
    public function localizeNakshatra(string|int|null $nak, string $locale = 'en'): string
    {
        if ($nak === null || $nak === '') {
            return '-';
        }

        if (is_int($nak) || (is_string($nak) && ctype_digit($nak))) {
            $id = (int) $nak;
            if ($id >= 1 && $id <= 27) {
                return $locale === 'hi' ? self::NAKSHATRA_MAP[$id]['hi'] : self::NAKSHATRA_MAP[$id]['en'];
            }
        }

        $str = trim((string) $nak);
        $clean = strtolower(str_replace([' ', '_', '-'], '', $str));

        foreach (self::NAKSHATRA_MAP as $item) {
            $itemCleanEn = strtolower(str_replace([' ', '_', '-'], '', $item['en']));
            $itemCleanHi = strtolower(str_replace([' ', '_', '-'], '', $item['hi']));

            if ($itemCleanEn === $clean || $itemCleanHi === $clean) {
                return $locale === 'hi' ? $item['hi'] : $item['en'];
            }
        }

        return $str;
    }

    /**
     * Localize Planet names deterministically across all 9 planets + ascendant.
     */
    public function localizePlanet(string|null $planet, string $locale = 'en'): string
    {
        if ($planet === null || $planet === '') {
            return '-';
        }

        $key = strtolower(trim($planet));

        // Alias mappings (e.g. guru, mangal, shukra)
        $aliases = [
            'guru' => 'jupiter',
            'brihaspati' => 'jupiter',
            'shukra' => 'venus',
            'shani' => 'saturn',
            'mangal' => 'mars',
            'kuja' => 'mars',
            'budh' => 'mercury',
            'budha' => 'mercury',
            'surya' => 'sun',
            'ravi' => 'sun',
            'chandra' => 'moon',
            'soma' => 'moon',
            'lagna' => 'ascendant',
        ];

        if (isset($aliases[$key])) {
            $key = $aliases[$key];
        }

        if (isset(self::PLANET_MAP[$key])) {
            return $locale === 'hi' ? self::PLANET_MAP[$key]['hi'] : self::PLANET_MAP[$key]['en'];
        }

        return ucfirst($planet);
    }

    /**
     * Localize Dignity status.
     */
    public function localizeDignity(string|null $dignity, string $locale = 'en'): string
    {
        if ($dignity === null || $dignity === '') {
            return $locale === 'hi' ? 'सम' : 'Neutral';
        }

        $key = strtolower(trim(str_replace([' ', '-'], '_', $dignity)));
        if (isset(self::DIGNITY_MAP[$key])) {
            return $locale === 'hi' ? self::DIGNITY_MAP[$key]['hi'] : self::DIGNITY_MAP[$key]['en'];
        }

        return $locale === 'hi' ? 'सम' : ucfirst($dignity);
    }

    /**
     * Localize Gana classifications.
     */
    public function localizeGana(string|null $gana, string $locale = 'en'): string
    {
        if ($gana === null || $gana === '') {
            return '-';
        }

        $key = strtolower(trim($gana));
        if (isset(self::GANA_MAP[$key])) {
            return $locale === 'hi' ? self::GANA_MAP[$key]['hi'] : self::GANA_MAP[$key]['en'];
        }

        return $gana;
    }

    /**
     * Localize Birth Time Precision.
     */
    public function localizePrecision(string|null $precision, string $locale = 'en'): string
    {
        $key = strtolower(trim((string) $precision));
        if ($key === 'verified') {
            return $locale === 'hi' ? 'सत्यापित' : 'Verified';
        }
        if ($key === 'approximate') {
            return $locale === 'hi' ? 'अनुमानित' : 'Approximate';
        }
        if ($key === 'unknown') {
            return $locale === 'hi' ? 'अज्ञात' : 'Unknown';
        }

        return ucfirst($key !== '' ? $key : 'verified');
    }

    /**
     * Localize Motion descriptions (Vakri / Asta).
     */
    public function localizeMotion(bool $isRetrograde, bool $isCombust, string $locale = 'en'): string
    {
        $parts = [];
        if ($isRetrograde) {
            $parts[] = $locale === 'hi' ? 'वक्री' : 'Retro';
        }
        if ($isCombust) {
            $parts[] = $locale === 'hi' ? 'अस्त' : 'Combust';
        }

        return !empty($parts) ? implode(' / ', $parts) : '-';
    }

    /**
     * Normalize and comprehensively localize Kundli data structure for template rendering.
     *
     * @param array<string, mixed> $kundliData
     * @param string $locale
     * @return array<string, mixed>
     */
    public function normalizeKundliDataForView(array $kundliData, string $locale = 'en'): array
    {
        $normalized = $kundliData;

        // 1. Profile Normalization
        if (isset($normalized['profile'])) {
            $rawPrecision = $normalized['profile']['precision'] ?? 'verified';
            $normalized['profile']['localized_precision'] = $this->localizePrecision($rawPrecision, $locale);

            if (!empty($normalized['profile']['birth_time'])) {
                $timeStr = (string) $normalized['profile']['birth_time'];
                $normalized['profile']['formatted_birth_time'] = sprintf(
                    '%s (%s)',
                    $timeStr,
                    $normalized['profile']['localized_precision']
                );
            }
        }

        // 2. Ascendant Sign Normalization
        if (isset($normalized['ascendant'])) {
            $ascSign = $normalized['ascendant']['name'] ?? ($normalized['ascendant']['id'] ?? null);
            $normalized['ascendant']['localized_name'] = $this->localizeZodiac($ascSign, $locale);
        }

        // 3. Moon Sign Normalization
        if (isset($normalized['planets']['moon']['rashi'])) {
            $moonSign = $normalized['planets']['moon']['rashi']['name'] ?? ($normalized['planets']['moon']['rashi']['id'] ?? null);
            $normalized['planets']['moon']['rashi']['localized_name'] = $this->localizeZodiac($moonSign, $locale);
        }

        // 4. Planetary Positions Normalization
        if (isset($normalized['planets']) && is_array($normalized['planets'])) {
            foreach ($normalized['planets'] as $key => &$p) {
                $p['localized_name'] = $this->localizePlanet($p['name'] ?? $key, $locale);

                if (isset($p['rashi'])) {
                    $rashiVal = $p['rashi']['name'] ?? ($p['rashi']['id'] ?? null);
                    $p['rashi']['localized_name'] = $this->localizeZodiac($rashiVal, $locale);
                }

                if (isset($p['nakshatra'])) {
                    $nakVal = $p['nakshatra']['name'] ?? ($p['nakshatra']['id'] ?? null);
                    $p['nakshatra']['localized_name'] = $this->localizeNakshatra($nakVal, $locale);
                    $p['nakshatra']['localized_lord'] = $this->localizePlanet($p['nakshatra']['lord'] ?? '', $locale);
                }

                $p['localized_dignity'] = $this->localizeDignity($p['dignity'] ?? 'neutral', $locale);
                $p['localized_motion'] = $this->localizeMotion(
                    !empty($p['is_retrograde']),
                    !empty($p['is_combust']),
                    $locale
                );
            }
            unset($p);
        }

        // 5. 12 House Placements Normalization
        if (isset($normalized['houses']) && is_array($normalized['houses'])) {
            foreach ($normalized['houses'] as &$h) {
                $signVal = $h['sign_name'] ?? ($h['sign_id'] ?? null);
                $h['localized_sign_name'] = $this->localizeZodiac($signVal, $locale);
                $h['localized_sign_lord'] = $this->localizePlanet($h['sign_lord'] ?? '', $locale);

                $rawResidents = $h['planets'] ?? ($h['resident_planets'] ?? []);
                $localizedResidents = [];
                foreach ($rawResidents as $rp) {
                    $pName = $rp['name'] ?? ($rp['key'] ?? '');
                    $localizedResidents[] = $this->localizePlanet($pName, $locale);
                }
                $h['localized_residents'] = !empty($localizedResidents) ? implode(', ', $localizedResidents) : '-';
            }
            unset($h);
        }

        // 6. Janma Nakshatra Details Normalization
        $interpNak = $normalized['interpretations'][$locale]['nakshatra']
            ?? ($normalized['interpretations']['en']['nakshatra'] ?? null);

        if ($interpNak) {
            $nakName = $interpNak['name'] ?? ($interpNak['id'] ?? null);
            $interpNak['localized_name'] = $this->localizeNakshatra($nakName, $locale);
            $interpNak['localized_lord'] = $this->localizePlanet($interpNak['lord'] ?? '', $locale);
            $interpNak['localized_gana'] = $this->localizeGana($interpNak['gana'] ?? '', $locale);

            $normalized['janma_nakshatra_normalized'] = $interpNak;
        }

        // 7. Vimshottari Dasha Normalization
        if (isset($normalized['dasha'])) {
            if (!empty($normalized['dasha']['current_dasha'])) {
                $curr = &$normalized['dasha']['current_dasha'];
                if (isset($curr['mahadasha']['lord'])) {
                    $curr['mahadasha']['localized_lord'] = $this->localizePlanet($curr['mahadasha']['lord'], $locale);
                }
                if (isset($curr['antardasha']['lord'])) {
                    $curr['antardasha']['localized_lord'] = $this->localizePlanet($curr['antardasha']['lord'], $locale);
                }
                unset($curr);
            }

            if (!empty($normalized['dasha']['mahadashas']) && is_array($normalized['dasha']['mahadashas'])) {
                foreach ($normalized['dasha']['mahadashas'] as &$md) {
                    $md['localized_lord'] = $this->localizePlanet($md['lord'] ?? '', $locale);
                    $md['localized_status'] = !empty($md['is_current'])
                        ? ($locale === 'hi' ? 'सक्रिय' : 'Active')
                        : '-';
                }
                unset($md);
            }
        }

        return $normalized;
    }

    /**
     * Generate PDF binary content for a Janam Kundli.
     *
     * @param array<string, mixed> $kundliData Complete Kundli output from KundliService
     * @param string $locale 'en' or 'hi'
     * @return string Raw PDF bytes
     */
    public function generatePdf(array $kundliData, string $locale = 'en'): string
    {
        $normalizedData = $this->normalizeKundliDataForView($kundliData, $locale);

        $lagnaSvg = null;
        if (($kundliData['ascendant']['status'] ?? null) === 'confirmed' && !empty($kundliData['houses'])) {
            $lagnaSvg = $this->renderNorthIndianChartSvg($kundliData['houses'], $locale, 'Lagna Kundli (D-1)');
        }

        $chandraHouses = $kundliData['chandra_kundli']['houses'] ?? [];
        $chandraSvg = !empty($chandraHouses)
            ? $this->renderNorthIndianChartSvg($chandraHouses, $locale, 'Chandra Kundli (Moon Chart)')
            : null;

        // 1. For Hindi locale: Headless Chromium with native HarfBuzz complex-script shaping is MANDATORY.
        // Controlled failure: If Chromium is not available, fail cleanly rather than silently producing an unshaped Dompdf document.
        if ($locale === 'hi') {
            $chromiumPdf = $this->tryRenderChromium($normalizedData, $locale, $lagnaSvg, $chandraSvg);
            if ($chromiumPdf !== null && strlen($chromiumPdf) > 1000) {
                return $chromiumPdf;
            }

            Log::error("Basic Kundli Hindi PDF generation failed: Headless Chromium with native HarfBuzz complex-script shaping is required for Hindi Devanagari typography, but was not found or failed to execute.");
            throw new \RuntimeException(
                'Hindi Kundli PDF generation requires headless Chromium for natural Devanagari script shaping. Chromium binary was not found or failed to render.'
            );
        }

        // 2. For English locale: Try Chromium first for optimal vector rendering, with safe fallback to Dompdf
        $chromiumPdf = $this->tryRenderChromium($normalizedData, $locale, $lagnaSvg, $chandraSvg);
        if ($chromiumPdf !== null && strlen($chromiumPdf) > 1000) {
            return $chromiumPdf;
        }

        Log::info("Basic Kundli PDF using Dompdf fallback engine for English locale");
        return $this->renderDompdf($normalizedData, $locale, $lagnaSvg, $chandraSvg);
    }

    /**
     * Render PDF using headless Chromium with native HarfBuzz complex-script shaping.
     */
    protected function tryRenderChromium(array $kundliData, string $locale, ?string $lagnaSvg, ?string $chandraSvg): ?string
    {
        $regPath = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
        $boldPath = resource_path('fonts/NotoSansDevanagari-Bold.ttf');

        // Pass base64 data URIs so Chromium loads fonts synchronously with zero file-access latency
        $fontRegular = file_exists($regPath)
            ? 'data:font/truetype;charset=utf-8;base64,' . base64_encode((string) file_get_contents($regPath))
            : 'file:///' . str_replace('\\', '/', $regPath);

        $fontBold = file_exists($boldPath)
            ? 'data:font/truetype;charset=utf-8;base64,' . base64_encode((string) file_get_contents($boldPath))
            : 'file:///' . str_replace('\\', '/', $boldPath);

        $html = View::make('pdf.kundli_report', [
            'kundli' => $kundliData,
            'locale' => $locale,
            'lagnaSvg' => $lagnaSvg,
            'chandraSvg' => $chandraSvg,
            'fontRegular' => $fontRegular,
            'fontBold' => $fontBold,
        ])->render();

        return $this->premiumPdfService->renderHtmlViaChromium($html, 'kundli_');
    }

    /**
     * Render PDF using Dompdf fallback with embedded Noto Sans Devanagari fonts.
     */
    public function renderDompdf(array $kundliData, string $locale, ?string $lagnaSvg, ?string $chandraSvg): string
    {
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $this->premiumPdfService->ensureSvgFontAssets($fontDir);

        $fontRegular = str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Regular.ttf'));
        $fontBold = str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Bold.ttf'));

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontDir);
        $options->set('chroot', [base_path()]);
        $options->set('defaultFont', $locale === 'hi' ? 'NotoDevanagari' : 'DejaVu Sans');

        $dompdf = new Dompdf($options);

        $html = View::make('pdf.kundli_report', [
            'kundli' => $kundliData,
            'locale' => $locale,
            'lagnaSvg' => $lagnaSvg,
            'chandraSvg' => $chandraSvg,
            'fontRegular' => $fontRegular,
            'fontBold' => $fontBold,
        ])->render();

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if ($output === null || $output === '') {
            throw new \RuntimeException('Dompdf failed to produce PDF output.');
        }

        return (string) $output;
    }

    /**
     * Generate vector SVG string representing a traditional North Indian Diamond chart.
     * Delegates to the battle-tested SvgVargaRenderer for authentic geometry and font embedding.
     *
     * @param array<int, array<string, mixed>> $houses
     * @param string $locale
     * @param string $chartTitle
     * @return string SVG XML markup
     */
    public function renderNorthIndianChartSvg(array $houses, string $locale, string $chartTitle = ''): string
    {
        return $this->svgRenderer->renderChart($houses, $locale, '', [
            'max_width' => '360px',
            'devanagari_numerals' => ($locale === 'hi'),
        ]);
    }
}
