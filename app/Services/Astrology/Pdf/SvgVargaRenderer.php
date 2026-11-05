<?php

declare(strict_types=1);

namespace App\Services\Astrology\Pdf;

/**
 * Dedicated vector SVG renderer for traditional North Indian Diamond Varga charts.
 *
 * Implements Work Package A5 of Astro Setu Milestone M9.2 (Detailed Kundli Report).
 *
 * Supports:
 *  - D-1 Lagna Kundli (with optional Ascendant degree annotation)
 *  - Chandra Kundli (rotated to Moon sign)
 *  - D-9 Navamsha Chart (with invariant D-9 Lagna glyph, or unrotated sign distribution when volatile)
 *  - Devanagari numerals and abbreviations in Hindi locale
 *  - Retrograde [R] and Combustion * indicators
 */
class SvgVargaRenderer
{
    /**
     * House text anchor coordinates on a 400x400 SVG canvas.
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

    /**
     * Devanagari numerals for 0-9.
     */
    protected const DEVANAGARI_DIGITS = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];

    /**
     * Standard planet abbreviations.
     */
    protected const PLANET_ABBR = [
        'sun'     => ['en' => 'Su', 'hi' => 'सू'],
        'moon'    => ['en' => 'Mo', 'hi' => 'चं'],
        'mars'    => ['en' => 'Ma', 'hi' => 'मं'],
        'mercury' => ['en' => 'Me', 'hi' => 'बु'],
        'jupiter' => ['en' => 'Ju', 'hi' => 'गु'],
        'venus'   => ['en' => 'Ve', 'hi' => 'शु'],
        'saturn'  => ['en' => 'Sa', 'hi' => 'श'],
        'rahu'    => ['en' => 'Ra', 'hi' => 'रा'],
        'ketu'    => ['en' => 'Ke', 'hi' => 'के'],
        'ascendant' => ['en' => 'As', 'hi' => 'ल'],
    ];

    /**
     * Cached base64 encoded NotoSansDevanagari font data for standalone SVG embedding.
     */
    protected static ?string $devanagariFontBase64 = null;

    /**
     * Render a standard North Indian diamond chart SVG.
     *
     * @param array<int, array<string, mixed>> $houses Keyed or indexed array of houses (1..12)
     * @param string $locale 'en' or 'hi'
     * @param string $chartTitle Optional header title inside or above chart
     * @param array<string, mixed> $options Additional styling / configuration options
     * @return string Valid SVG XML
     */
    public function renderChart(
        array $houses,
        string $locale = 'en',
        string $chartTitle = '',
        array $options = []
    ): string {
        $houseMap = [];
        foreach ($houses as $h) {
            $hNum = (int) ($h['house_number'] ?? $h['house'] ?? 1);
            $houseMap[$hNum] = $h;
        }

        $fontFamily = $locale === 'hi'
            ? 'NotoDevanagari'
            : 'DejaVu Sans';

        $useDevanagariNumerals = (bool) ($options['devanagari_numerals'] ?? ($locale === 'hi'));
        $showAscDegree = $options['ascendant_degree'] ?? null;
        $maxChartWidth = $options['max_width'] ?? '240px';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" ' .
            'style="max-width:' . htmlspecialchars($maxChartWidth) . ';margin:0 auto;display:block;">';

        // Embed Noto Sans Devanagari font in SVG defs for standalone image rendering across all environments
        if ($locale === 'hi') {
            if (self::$devanagariFontBase64 === null) {
                $fontPath = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
                if (file_exists($fontPath)) {
                    $fontData = @file_get_contents($fontPath);
                    if ($fontData !== false) {
                        self::$devanagariFontBase64 = base64_encode($fontData);
                    }
                }
            }

            if (self::$devanagariFontBase64 !== null) {
                $svg .= '<defs><style>' .
                    '@font-face {' .
                    'font-family: "NotoDevanagari";' .
                    'src: url("data:font/truetype;charset=utf-8;base64,' . self::$devanagariFontBase64 . '") format("truetype");' .
                    'font-weight: normal; font-style: normal;' .
                    '}' .
                    'text { font-family: "NotoDevanagari", "Noto Sans Devanagari", sans-serif; }' .
                    '</style></defs>';
            }
        }

        // Background styling
        $svg .= '<rect x="0" y="0" width="400" height="400" fill="#FFFDF8" stroke="#D4AF37" stroke-width="2.5" rx="3"/>';

        // Outer diamond and diagonal lines
        $svg .= '<line x1="0" y1="0" x2="400" y2="400" stroke="#C59B27" stroke-width="1.6"/>';
        $svg .= '<line x1="0" y1="400" x2="400" y2="0" stroke="#C59B27" stroke-width="1.6"/>';
        $svg .= '<polygon points="200,0 400,200 200,400 0,200" fill="none" stroke="#C59B27" stroke-width="1.6"/>';

        // Render each house
        for ($hNum = 1; $hNum <= 12; $hNum++) {
            $coords = self::HOUSE_COORDINATES[$hNum];
            $hData = $houseMap[$hNum] ?? null;

            if ($hData !== null) {
                // Rashi number
                $rashiId = (int) ($hData['sign_id'] ?? ($hData['rashi']['id'] ?? $hNum));
                $rashiDisplay = $useDevanagariNumerals
                    ? $this->toDevanagariDigits($rashiId)
                    : (string) $rashiId;

                $svg .= sprintf(
                    '<text x="%d" y="%d" font-family="%s" font-size="14" font-weight="bold" fill="#8C1D40" text-anchor="middle">%s</text>',
                    $coords['rashi_x'],
                    $coords['rashi_y'],
                    $fontFamily,
                    htmlspecialchars($rashiDisplay)
                );

                // Resident planets
                $residents = $hData['planets'] ?? ($hData['resident_planets'] ?? []);
                $planetPills = [];

                // House 1 optional Ascendant glyph
                if ($hNum === 1 && !empty($options['show_ascendant_glyph'])) {
                    $ascAbbr = $locale === 'hi' ? self::PLANET_ABBR['ascendant']['hi'] : self::PLANET_ABBR['ascendant']['en'];
                    $planetPills[] = $ascAbbr;
                }

                foreach ($residents as $rp) {
                    $planetKey = strtolower((string) ($rp['key'] ?? ($rp['planet'] ?? ($rp['name'] ?? ''))));
                    $abbr = $locale === 'hi'
                        ? ($rp['abbr_hi'] ?? (self::PLANET_ABBR[$planetKey]['hi'] ?? ($rp['abbr'] ?? substr($planetKey, 0, 2))))
                        : ($rp['abbr'] ?? (self::PLANET_ABBR[$planetKey]['en'] ?? ucfirst(substr($planetKey, 0, 2))));

                    if (!empty($rp['is_retrograde'])) {
                        $abbr .= '(R)';
                    }
                    if (!empty($rp['is_combust'])) {
                        $abbr .= '*';
                    }
                    $planetPills[] = $abbr;
                }

                if (!empty($planetPills)) {
                    // Split into multiple lines if more than 3 planets in a house
                    $chunks = array_chunk($planetPills, 3);
                    $startY = $coords['planets_y'] - ((count($chunks) - 1) * 7);

                    foreach ($chunks as $lineIdx => $chunk) {
                        $lineText = implode(' ', $chunk);
                        $lineY = $startY + ($lineIdx * 13);
                        $svg .= sprintf(
                            '<text x="%d" y="%d" font-family="%s" font-size="12" font-weight="bold" fill="#0F2942" text-anchor="middle">%s</text>',
                            $coords['planets_x'],
                            $lineY,
                            $fontFamily,
                            htmlspecialchars($lineText)
                        );
                    }
                }
            }
        }

        // Optional Ascendant degrees label in Lagna house (House 1)
        if ($showAscDegree !== null && $showAscDegree !== '') {
            $svg .= sprintf(
                '<text x="200" y="80" font-family="%s" font-size="9.5" font-weight="bold" fill="#4A5568" text-anchor="middle">%s</text>',
                $fontFamily,
                htmlspecialchars((string) $showAscDegree, ENT_NOQUOTES)
            );
        }

        // Optional chart title in corner
        if ($chartTitle !== '') {
            $svg .= sprintf(
                '<text x="10" y="392" font-family="%s" font-size="9" font-weight="bold" fill="#718096">%s</text>',
                $fontFamily,
                htmlspecialchars($chartTitle)
            );
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Render D-1 Lagna Kundli.
     *
     * @param array<int, array<string, mixed>> $houses
     * @param string $locale
     * @param string|null $ascendantDms
     * @return string
     */
    public function renderD1(array $houses, string $locale = 'en', ?string $ascendantDms = null): string
    {
        $title = $locale === 'hi' ? 'लग्न कुण्डली (D-1)' : 'Lagna Kundli (D-1)';

        return $this->renderChart($houses, $locale, $title, [
            'ascendant_degree' => $ascendantDms,
            'show_ascendant_glyph' => true,
        ]);
    }

    /**
     * Render Chandra Kundli (Moon as House 1).
     *
     * @param array<int, array<string, mixed>> $houses
     * @param string $locale
     * @return string
     */
    public function renderChandra(array $houses, string $locale = 'en'): string
    {
        $title = $locale === 'hi' ? 'चन्द्र कुण्डली' : 'Chandra Kundli';

        return $this->renderChart($houses, $locale, $title, [
            'show_ascendant_glyph' => false,
        ]);
    }

    /**
     * Render D-9 Navamsha Chart.
     *
     * In accordance with Gate B:
     * - Invariant D-9 planetary placements are always displayed.
     * - If D-9 Ascendant is invariant across birth uncertainty, House 1 is rotated to the D-9 Lagna
     *   sign and the Ascendant glyph is rendered.
     * - If D-9 Ascendant is volatile (suppressed), the chart is rendered as an unrotated sign distribution
     *   (Aries = 1, Taurus = 2, ..., Pisces = 12) with NO Ascendant glyph.
     *
     * @param array<string, array<string, mixed>> $planetaryD9 Output from PlanetaryNavamshaStabilityService
     * @param array<string, mixed>|null $d9Ascendant Output from AscendantStabilityService['d9_ascendant']
     * @param string $locale 'en' or 'hi'
     * @return string
     */
    public function renderD9(array $planetaryD9, ?array $d9Ascendant = null, string $locale = 'en'): string
    {
        $isLagnaInvariant = !empty($d9Ascendant['is_invariant']) && ($d9Ascendant['status'] ?? '') === 'confirmed';
        $d9LagnaSignId = $isLagnaInvariant ? (int) ($d9Ascendant['id'] ?? 1) : 1;

        // Construct 12 houses based on rotation
        $houses = [];
        for ($h = 1; $h <= 12; $h++) {
            $signId = $isLagnaInvariant
                ? (($d9LagnaSignId + $h - 2) % 12) + 1
                : $h; // Unrotated sign distribution (Aries = House 1)

            $houses[$h] = [
                'house_number' => $h,
                'sign_id' => $signId,
                'planets' => [],
            ];
        }

        // Map planets to houses based on their D-9 Navamsha sign
        foreach ($planetaryD9 as $key => $pData) {
            if (!empty($pData['is_suppressed']) || empty($pData['is_invariant'])) {
                continue; // Suppressed / non-invariant planetary placements omitted
            }

            $planetD9SignId = (int) ($pData['d9_navamsha']['id'] ?? 0);
            if ($planetD9SignId < 1 || $planetD9SignId > 12) {
                continue;
            }

            // Find which house corresponds to this signId
            $targetHouse = null;
            foreach ($houses as $hNum => $hInfo) {
                if ($hInfo['sign_id'] === $planetD9SignId) {
                    $targetHouse = $hNum;
                    break;
                }
            }

            if ($targetHouse !== null) {
                $houses[$targetHouse]['planets'][] = [
                    'key' => $key,
                    'name' => $pData['name'] ?? $key,
                    'abbr' => self::PLANET_ABBR[$key]['en'] ?? ucfirst(substr($key, 0, 2)),
                    'abbr_hi' => self::PLANET_ABBR[$key]['hi'] ?? $key,
                    'is_retrograde' => !empty($pData['is_retrograde']),
                    'is_combust' => !empty($pData['is_combust']),
                ];
            }
        }

        $title = $isLagnaInvariant
            ? ($locale === 'hi' ? 'नवांश कुण्डली (D-9)' : 'Navamsha Kundli (D-9)')
            : ($locale === 'hi' ? 'नवांश ग्रह स्थिति (D-9)' : 'D-9 Navamsha (Sign Distribution)');

        return $this->renderChart($houses, $locale, $title, [
            'show_ascendant_glyph' => $isLagnaInvariant,
            'ascendant_degree' => $isLagnaInvariant ? ($d9Ascendant['degrees_dms'] ?? null) : null,
        ]);
    }

    /**
     * Convert an integer into Devanagari numerals.
     */
    protected function toDevanagariDigits(int $number): string
    {
        $str = (string) $number;
        $out = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if (ctype_digit($char)) {
                $out .= self::DEVANAGARI_DIGITS[(int) $char];
            } else {
                $out .= $char;
            }
        }
        return $out;
    }
}
