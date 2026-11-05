<?php

declare(strict_types=1);

namespace App\Services\Astrology\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * Service for rendering high quality, branded Vedic Janam Kundli PDF reports.
 *
 * Implements M6 of the Astro Setu Product Roadmap.
 * Uses Dompdf with embedded vector SVG North Indian charts.
 */
class KundliPdfService
{
    /**
     * House text anchor coordinates on a 400x400 SVG canvas.
     */
    protected const HOUSE_COORDINATES = [
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
        protected ?PremiumKundliPdfService $premiumPdfService = null
    ) {
        $this->premiumPdfService ??= app(PremiumKundliPdfService::class);
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
        $lagnaSvg = null;
        if (($kundliData['ascendant']['status'] ?? null) === 'confirmed' && !empty($kundliData['houses'])) {
            $lagnaSvg = $this->renderNorthIndianChartSvg($kundliData['houses'], $locale, 'Lagna Kundli (D-1)');
        }

        $chandraHouses = $kundliData['chandra_kundli']['houses'] ?? [];
        $chandraSvg = !empty($chandraHouses)
            ? $this->renderNorthIndianChartSvg($chandraHouses, $locale, 'Chandra Kundli (Moon Chart)')
            : null;

        // 1. Try Chromium renderer for native HarfBuzz complex-script shaping
        $chromiumPdf = $this->tryRenderChromium($kundliData, $locale, $lagnaSvg, $chandraSvg);
        if ($chromiumPdf !== null && $chromiumPdf !== '') {
            return $chromiumPdf;
        }

        // 2. Deterministic zero-dependency fallback: Dompdf with embedded Noto fonts
        return $this->renderDompdf($kundliData, $locale, $lagnaSvg, $chandraSvg);
    }

    /**
     * Render PDF using headless Chromium with native HarfBuzz complex-script shaping.
     */
    protected function tryRenderChromium(array $kundliData, string $locale, ?string $lagnaSvg, ?string $chandraSvg): ?string
    {
        $fontRegular = 'file:///' . str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Regular.ttf'));
        $fontBold = 'file:///' . str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Bold.ttf'));

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
     * Generate an inline vector SVG string representing a traditional North Indian Diamond chart.
     *
     * @param array<int, array<string, mixed>> $houses
     * @param string $locale
     * @param string $chartTitle
     * @return string SVG XML markup
     */
    public function renderNorthIndianChartSvg(array $houses, string $locale, string $chartTitle): string
    {
        $houseMap = [];
        foreach ($houses as $h) {
            $hNum = (int) $h['house_number'];
            $houseMap[$hNum] = $h;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="100%" height="100%" style="max-width:360px;margin:0 auto;display:block;">';

        // Background styling
        $svg .= '<rect x="0" y="0" width="400" height="400" fill="#FFFDF8" stroke="#D4AF37" stroke-width="3" rx="4"/>';

        // Outer diamond and diagonal lines
        $svg .= '<line x1="0" y1="0" x2="400" y2="400" stroke="#C59B27" stroke-width="1.8"/>';
        $svg .= '<line x1="0" y1="400" x2="400" y2="0" stroke="#C59B27" stroke-width="1.8"/>';
        $svg .= '<polygon points="200,0 400,200 200,400 0,200" fill="none" stroke="#C59B27" stroke-width="1.8"/>';

        $fontFamily = $locale === 'hi' ? "'NotoDevanagari', 'Noto Sans Devanagari', 'DejaVu Sans', sans-serif" : "DejaVu Sans, Arial, sans-serif";

        // House labels and planet placements
        for ($hNum = 1; $hNum <= 12; $hNum++) {
            $coords = self::HOUSE_COORDINATES[$hNum];
            $hData = $houseMap[$hNum] ?? null;

            if ($hData) {
                // Rashi number in Roman/Devanagari digit
                $rashiId = (int) ($hData['sign_id'] ?? ($hData['rashi']['id'] ?? 1));
                $svg .= sprintf(
                    '<text x="%d" y="%d" font-family="%s" font-size="12" font-weight="bold" fill="#8C1D40" text-anchor="middle">%d</text>',
                    $coords['rashi_x'],
                    $coords['rashi_y'],
                    $fontFamily,
                    $rashiId
                );

                // Resident planets
                $residents = $hData['planets'] ?? ($hData['resident_planets'] ?? []);
                if (!empty($residents)) {
                    $abbrs = [];
                    foreach ($residents as $rp) {
                        $abbr = $locale === 'hi'
                            ? ($rp['abbr_hi'] ?? ($rp['abbr'] ?? ($rp['name'] ?? ($rp['key'] ?? ''))))
                            : ($rp['abbr'] ?? ($rp['name'] ?? ($rp['key'] ?? '')));
                        if (!empty($rp['is_retrograde'])) {
                            $abbr .= '(R)';
                        }
                        if (!empty($rp['is_combust'])) {
                            $abbr .= '*';
                        }
                        $abbrs[] = $abbr;
                    }

                    $planetText = implode(' ', $abbrs);
                    $svg .= sprintf(
                        '<text x="%d" y="%d" font-family="%s" font-size="11" font-weight="600" fill="#1A365D" text-anchor="middle">%s</text>',
                        $coords['planets_x'],
                        $coords['planets_y'],
                        $fontFamily,
                        htmlspecialchars($planetText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    );
                }
            }
        }

        $svg .= '</svg>';

        return $svg;
    }
}
