<?php

declare(strict_types=1);

namespace App\Services\Astrology\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Service for rendering the 15-section Premium Vedic Kundli PDF report.
 *
 * Implements Work Package A5 of Astro Setu Milestone M9.2 (Detailed Kundli Report).
 *
 * Key Technical Capabilities:
 *  - Native Dompdf integration with UTF-8 Devanagari font embedding.
 *  - Table-based fixed layouts avoiding Dompdf flexbox/float bugs.
 *  - Inline canvas PHP script for dynamic "Page X of Y" pagination.
 *  - Strict Gate B conditional suppression and bilingual styling.
 */
class PremiumKundliPdfService
{
    /**
     * Generate binary PDF string from prepared report data payload.
     *
     * @param array<string, mixed> $reportData Output of DetailedKundliReportService::buildReportData
     * @param string $locale 'en' or 'hi'
     * @return string Raw PDF binary string
     */
    public function generatePdf(array $reportData, string $locale = 'en'): string
    {
        // 1. Try Chromium renderer for native HarfBuzz complex-script shaping
        $chromiumPdf = $this->tryRenderChromium($reportData, $locale);
        if ($chromiumPdf !== null && $chromiumPdf !== '') {
            return $chromiumPdf;
        }

        // 2. Deterministic zero-dependency fallback: Dompdf with embedded Noto fonts
        return $this->renderDompdf($reportData, $locale);
    }

    /**
     * Render PDF using headless Chromium for native HarfBuzz complex-script shaping.
     */
    protected function tryRenderChromium(array $reportData, string $locale): ?string
    {
        $fontRegular = 'file:///' . str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Regular.ttf'));
        $fontBold = 'file:///' . str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Bold.ttf'));

        $logoPath = public_path('images/header/logo.png');
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            if ($logoData !== false) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }
        }

        $html = View::make('pdf.detailed_kundli_report', [
            'report' => $reportData,
            'locale' => $locale,
            'fontRegular' => $fontRegular,
            'fontBold' => $fontBold,
            'logoBase64' => $logoBase64,
        ])->render();

        return $this->renderHtmlViaChromium($html, 'detailed_report_');
    }

    /**
     * Render an arbitrary HTML string into a PDF binary using headless Chromium.
     */
    public function renderHtmlViaChromium(string $html, string $tempPrefix = 'report_'): ?string
    {
        // 1. Check for external/remote Chromium rendering microservice (Option C for shared hosting)
        $remoteServiceUrl = env('PDF_RENDER_SERVICE_URL') ?: env('CHROME_RENDER_URL');
        if (!empty($remoteServiceUrl)) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->withHeaders([
                        'X-Render-Secret' => (string) env('PDF_RENDER_SERVICE_SECRET', ''),
                        'Accept' => 'application/pdf',
                    ])
                    ->post($remoteServiceUrl, [
                        'html' => $html,
                    ]);

                if ($response->successful() && strlen($response->body()) > 1000) {
                    return $response->body();
                }
            } catch (\Throwable $remoteEx) {
                \Illuminate\Support\Facades\Log::warning("Remote PDF render service request failed: {$remoteEx->getMessage()}");
            }
        }

        // 2. Resolve local Chromium executable binary
        $chromeBinary = $this->resolveChromiumBinary();
        if ($chromeBinary === null) {
            \Illuminate\Support\Facades\Log::warning('Chromium binary not found across system paths. Falling back to Dompdf engine.');
            return null;
        }

        $tempDir = storage_path('app/temp_pdf');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $id = uniqid($tempPrefix, true);
        $tempHtml = $tempDir . '/' . $id . '.html';
        $tempPdf = $tempDir . '/' . $id . '.pdf';

        try {
            file_put_contents($tempHtml, $html);
            $fullHtmlUrl = 'file:///' . str_replace('\\', '/', realpath($tempHtml));

            $cmd = sprintf(
                '"%s" --headless --disable-gpu --no-sandbox --disable-dev-shm-usage --allow-file-access-from-files --run-all-compositor-stages-before-draw --virtual-time-budget=2000 --no-pdf-header-footer --print-to-pdf="%s" "%s"',
                $chromeBinary,
                $tempPdf,
                $fullHtmlUrl
            );

            $output = [];
            $retCode = 0;
            exec($cmd, $output, $retCode);

            if ($retCode === 0 && file_exists($tempPdf) && filesize($tempPdf) > 1000) {
                $pdfBinary = file_get_contents($tempPdf);
                if ($pdfBinary !== false && $pdfBinary !== '') {
                    return $pdfBinary;
                }
            } else {
                \Illuminate\Support\Facades\Log::warning("Chromium PDF execution exited with code {$retCode}. Falling back to Dompdf.", [
                    'output' => implode("\n", $output),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Chromium PDF execution exception: {$e->getMessage()}. Falling back to Dompdf.");
        } finally {
            if (file_exists($tempHtml)) {
                @unlink($tempHtml);
            }
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }
        }

        return null;
    }

    /**
     * Resolve Chromium binary path across common environments.
     */
    public function resolveChromiumBinary(): ?string
    {
        $custom = env('CHROME_PATH') ?? env('PUPPETEER_EXECUTABLE_PATH');
        if ($custom && file_exists((string) $custom)) {
            return (string) $custom;
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
            '/usr/local/bin/google-chrome',
            '/usr/local/bin/chromium',
            '/usr/bin/google-chrome-unstable',
        ];

        foreach ($candidates as $c) {
            if (file_exists($c)) {
                return $c;
            }
        }

        if (DIRECTORY_SEPARATOR === '/') {
            foreach (['google-chrome', 'chromium-browser', 'chromium', 'google-chrome-stable'] as $bin) {
                $found = trim((string) @shell_exec("which {$bin} 2>/dev/null"));
                if ($found !== '' && file_exists($found)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Render PDF using Dompdf with embedded Noto Sans Devanagari fonts.
     */
    public function renderDompdf(array $reportData, string $locale = 'en'): string
    {
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $fontRegular = str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Regular.ttf'));
        $fontBold = str_replace('\\', '/', resource_path('fonts/NotoSansDevanagari-Bold.ttf'));

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true); // Enables inline canvas page numbering
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontDir);
        $options->set('chroot', [base_path()]);
        $options->set('defaultFont', $locale === 'hi' ? 'NotoDevanagari' : 'DejaVu Sans');

        $this->ensureSvgFontAssets($fontDir);

        $dompdf = new Dompdf($options);

        // Logo image base64 encoded for seamless embedding
        $logoPath = public_path('images/header/logo.png');
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            if ($logoData !== false) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }
        }

        $html = View::make('pdf.detailed_kundli_report', [
            'report' => $reportData,
            'locale' => $locale,
            'fontRegular' => $fontRegular,
            'fontBold' => $fontBold,
            'logoBase64' => $logoBase64,
        ])->render();

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if ($output === null || $output === '') {
            throw new RuntimeException('Dompdf failed to produce PDF output.');
        }

        return (string) $output;
    }

    /**
     * Extract exact page count from rendered PDF binary.
     */
    public function extractPageCount(string $pdfBinary): int
    {
        // Simple and robust regex counting /Type /Page (excluding /Pages)
        $count = preg_match_all('/\/Type\s*\/Page(?=[ \t\r\n\/>])/s', $pdfBinary);
        return max(1, (int) $count);
    }

    /**
     * Ensure Dompdf named font metrics and TTF files are in place for SVG font resolution.
     */
    public function ensureSvgFontAssets(string $fontDir): void
    {
        $regTtf = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
        $boldTtf = resource_path('fonts/NotoSansDevanagari-Bold.ttf');

        // Target aliases for Cpdf / php-svg-lib direct resolution
        $targets = [
            'NotoDevanagari' => $regTtf,
            'NotoDevanagari-Bold' => $boldTtf,
        ];

        foreach ($targets as $alias => $srcTtf) {
            $destStorageTtf = $fontDir . '/' . $alias . '.ttf';
            $destBaseTtf = base_path($alias . '.ttf');

            if (!file_exists($destStorageTtf) && file_exists($srcTtf)) {
                @copy($srcTtf, $destStorageTtf);
            }
            if (!file_exists($destBaseTtf) && file_exists($srcTtf)) {
                @copy($srcTtf, $destBaseTtf);
            }

            // Also check for generated UFM from Dompdf font registration
            $prefix = strtolower($alias);
            $matchingUfm = glob($fontDir . '/' . $prefix . '_*.ufm')[0] ?? null;
            if ($matchingUfm !== null) {
                $destUfm = $fontDir . '/' . $alias . '.ufm';
                $destBaseUfm = base_path($alias . '.ufm');
                if (!file_exists($destUfm)) {
                    @copy($matchingUfm, $destUfm);
                }
                if (!file_exists($destBaseUfm)) {
                    @copy($matchingUfm, $destBaseUfm);
                }

                $matchingJson = str_replace('.ufm', '.ufm.json', $matchingUfm);
                if (file_exists($matchingJson)) {
                    $destJson = $fontDir . '/' . $alias . '.ufm.json';
                    $destBaseJson = base_path($alias . '.ufm.json');
                    if (!file_exists($destJson)) {
                        @copy($matchingJson, $destJson);
                    }
                    if (!file_exists($destBaseJson)) {
                        @copy($matchingJson, $destBaseJson);
                    }
                }
            }
        }
    }
}
