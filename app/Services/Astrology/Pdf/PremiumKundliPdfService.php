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
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $fontRegular = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
        $fontBold = resource_path('fonts/NotoSansDevanagari-Bold.ttf');

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true); // Enables inline canvas page numbering
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontDir);
        $options->set('chroot', [base_path()]);
        $options->set('defaultFont', $locale === 'hi' ? 'Noto Sans Devanagari' : 'DejaVu Sans');

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
}
