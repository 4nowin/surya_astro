<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $locale === 'hi' ? 'दैनिक व्यक्तिगत भविष्यफल - एस्ट्रो सेतु' : 'Daily Personal Insights Report - Astro Setu' }}</title>
    <style>
        @font-face {
            font-family: 'NotoDevanagari';
            src: url('{{ $fontRegular }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'NotoDevanagari';
            src: url('{{ $fontBold }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url('{{ $fontRegular }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url('{{ $fontBold }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }

        @page {
            margin: 20px 26px 24px 26px;
            size: A4 portrait;
        }

        body {
            font-family: {!! $locale === 'hi' ? 'NotoDevanagari, "Noto Sans Devanagari", sans-serif' : "'DejaVu Sans', sans-serif" !!};
            color: #1A202C;
            font-size: 8px;
            line-height: 1.35;
        }

        body, table, td, th, div, span, p, b, strong, em {
            font-family: {!! $locale === 'hi' ? 'NotoDevanagari, "Noto Sans Devanagari", sans-serif' : "'DejaVu Sans', sans-serif" !!};
        }

        .header-bar {
            width: 100%;
            border-bottom: 2px solid #8C1D40;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .header-bar table {
            width: 100%;
            border-collapse: collapse;
        }
        .practitioner-name {
            font-size: 15px;
            font-weight: bold;
            color: #8C1D40;
            letter-spacing: 0.3px;
        }
        .practitioner-headline {
            font-size: 8.5px;
            color: #4A5568;
            margin-top: 1px;
        }
        .practitioner-contact {
            font-size: 7.5px;
            color: #718096;
            margin-top: 2px;
        }
        .report-badge {
            text-align: right;
            font-size: 13px;
            font-weight: bold;
            color: #2B6CB0;
        }
        .report-sub {
            text-align: right;
            font-size: 8px;
            color: #718096;
            margin-top: 2px;
        }

        .summary-banner {
            border-radius: 4px;
            padding: 7px 10px;
            margin-bottom: 8px;
            border: 1px solid #CBD5E0;
        }
        .banner-favourable {
            background-color: #F0FFF4;
            border-color: #9AE6B4;
            color: #22543D;
        }
        .banner-mixed {
            background-color: #FEFCBF;
            border-color: #F6E05E;
            color: #744210;
        }
        .banner-cautious {
            background-color: #FFF5F5;
            border-color: #FEB2B2;
            color: #742A2A;
        }

        .banner-title {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .banner-desc {
            font-size: 8.5px;
            line-height: 1.35;
        }

        .section-box {
            background-color: #FAFCFE;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 6px 9px;
            margin-bottom: 7px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #8C1D40;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 3px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .grid-table {
            width: 100%;
            border-collapse: collapse;
        }
        .grid-table td {
            padding: 2.5px 5px;
            vertical-align: top;
            font-size: 8.5px;
        }
        .lbl {
            color: #718096;
            width: 25%;
            font-weight: 500;
        }
        .val {
            color: #1A202C;
            font-weight: 600;
        }

        .cat-item {
            border: 1px solid #E2E8F0;
            border-radius: 3px;
            padding: 5px 8px;
            margin-bottom: 5px;
            background-color: #FFFFFF;
            page-break-inside: avoid;
        }
        .cat-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }
        .cat-title {
            font-size: 9px;
            font-weight: bold;
            color: #2D3748;
        }
        .cat-pill {
            text-align: right;
            font-size: 7.5px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
        }
        .pill-favourable {
            background-color: #C6F6D5;
            color: #22543D;
        }
        .pill-mixed {
            background-color: #FEFCBF;
            color: #744210;
        }
        .pill-cautious {
            background-color: #FED7D7;
            color: #742A2A;
        }

        .cat-headline {
            font-size: 8.5px;
            font-weight: 600;
            color: #2B6CB0;
            margin-bottom: 2px;
        }
        .cat-body {
            font-size: 8px;
            color: #4A5568;
            line-height: 1.35;
        }
        .cat-guidance {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px dashed #E2E8F0;
            font-size: 7.5px;
            color: #718096;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }
        .data-table th, .data-table td {
            border: 1px solid #CBD5E0;
            padding: 3px 5px;
            text-align: left;
            font-size: 7.5px;
        }
        .data-table th {
            background-color: #EDF2F7;
            color: #2D3748;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background-color: #F7FAFC;
        }

        .footer-bar {
            width: 100%;
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px solid #E2E8F0;
            font-size: 7.5px;
            color: #718096;
        }
        .footer-bar table {
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>
<body>

    <!-- Header / Astrologer Branding -->
    <div class="header-bar">
        <table>
            <tr>
                <td style="width: 58%; vertical-align: top;">
                    <div class="practitioner-name">{{ $branding['practitioner_name'] ?? 'Astro Setu' }}</div>
                    <div class="practitioner-headline">{{ $branding['headline'] ?? ($locale === 'hi' ? 'वैदिक ज्योतिष परामर्श' : 'Vedic Astrological Consultations') }}</div>
                    <div class="practitioner-contact">
                        @if(!empty($branding['city'])) {{ $branding['city'] }} @endif
                        @if(!empty($branding['phone'])) &bull; {{ $branding['phone'] }} @endif
                        @if(!empty($branding['email'])) &bull; {{ $branding['email'] }} @endif
                    </div>
                </td>
                <td style="width: 42%; vertical-align: top; text-align: right;">
                    <div class="report-badge">{{ $locale === 'hi' ? 'दैनिक व्यक्तिगत भविष्यफल' : 'Daily Personal Insights' }}</div>
                    <div class="report-sub">
                        {{ $locale === 'hi' ? 'जातक:' : 'Native:' }} <strong>{{ $insights['profile']['name'] ?? $native['name'] ?? 'Client' }}</strong>
                    </div>
                    <div class="report-sub">
                        {{ $locale === 'hi' ? 'लक्षित तिथि:' : 'Target Date:' }} {{ \Carbon\Carbon::parse($insights['target_date'] ?? now())->format('d M Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Native Summary & Astro Reference -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'जन्म पत्रिका संदर्भ एवं चन्द्र स्थिति' : 'Birth Chart Reference & Moon Position' }}</div>
        <table class="grid-table">
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'जातक का नाम' : 'Client Name' }}:</td>
                <td class="val">{{ $insights['profile']['name'] ?? $native['name'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'जन्म स्थान / नगर' : 'Birth City' }}:</td>
                <td class="val">{{ $insights['profile']['birth_city'] ?? $native['city'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'चन्द्र राशि' : 'Moon Sign (Rashi)' }}:</td>
                <td class="val">{{ $insights['birth_chart_reference']['moon_sign']['name'] ?? $native['moon_sign'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'जन्म नक्षत्र' : 'Birth Nakshatra' }}:</td>
                <td class="val">{{ $insights['birth_chart_reference']['nakshatra']['name'] ?? $native['nakshatra'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    @php
        $overallTone = $insights['summary']['overall_tone'] ?? 'mixed';
        $bannerClass = match($overallTone) {
            'favourable' => 'banner-favourable',
            'cautious' => 'banner-cautious',
            default => 'banner-mixed',
        };
        $toneLabel = $insights['summary']['overall_tone_label'] ?? ucfirst($overallTone);
    @endphp

    <!-- Overall Mood / Trajectory Banner -->
    <div class="summary-banner {{ $bannerClass }}">
        <div class="banner-title">
            {{ $locale === 'hi' ? 'आज का दिन कैसा रहेगा:' : "Today's Trajectory:" }} {{ $toneLabel }}
        </div>
        <div class="banner-desc">
            <strong>{{ $insights['summary']['headline'] ?? '' }}</strong>
            @if(!empty($insights['summary']['primary_influences']))
                <div style="margin-top: 3px; font-size: 8px;">
                    &bull; {{ implode(' &bull; ', $insights['summary']['primary_influences']) }}
                </div>
            @endif
        </div>
    </div>

    <!-- 7 Life Areas Detailed Guidance -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'दैनिक 7 जीवन क्षेत्र विश्लेषण' : '7 Life Areas Daily Guidance' }}</div>

        @php
            $categoryTitles = [
                'overall' => ['en' => 'Overall Energy & Daily Mindset', 'hi' => 'समग्र ऊर्जा एवं दिन का मिजाज'],
                'career' => ['en' => 'Career, Profession & Business', 'hi' => 'कार्यक्षेत्र, नौकरी एवं व्यापार'],
                'relationships' => ['en' => 'Relationships, Family & Marriage', 'hi' => 'संबंध, पारिवारिक एवं दाम्पत्य जीवन'],
                'finance' => ['en' => 'Finance, Gains & Expenditure', 'hi' => 'वित्त, धन लाभ एवं व्यय प्रबंधन'],
                'wellbeing' => ['en' => 'Wellbeing, Health & Vitality', 'hi' => 'स्वास्थ्य, मानसिक संतुलन एवं ऊर्जा'],
                'opportunities_cautions' => ['en' => 'Opportunities & Cautions', 'hi' => 'अवसर एवं सावधानियां'],
                'guidance' => ['en' => 'Daily Guidance & Remedial Action', 'hi' => 'दैनिक मार्गदर्शन एवं उपाय'],
            ];
        @endphp

        @foreach(($insights['categories'] ?? []) as $catKey => $catData)
            @php
                $catTone = $catData['tone'] ?? 'mixed';
                $pillClass = match($catTone) {
                    'favourable' => 'pill-favourable',
                    'cautious' => 'pill-cautious',
                    default => 'pill-mixed',
                };
                $titleEnHi = $categoryTitles[$catKey][$locale] ?? ucfirst(str_replace('_', ' ', $catKey));
            @endphp
            <div class="cat-item">
                <table class="cat-header">
                    <tr>
                        <td class="cat-title">{{ $titleEnHi }}</td>
                        <td style="text-align: right;">
                            <span class="cat-pill {{ $pillClass }}">{{ ucfirst($catTone) }}</span>
                        </td>
                    </tr>
                </table>
                @if(!empty($catData['headline']))
                    <div class="cat-headline">{{ $catData['headline'] }}</div>
                @endif
                <div class="cat-body">{{ $catData['description'] ?? ($catData['narrative'] ?? '') }}</div>
                @if(!empty($catData['guidance']))
                    <div class="cat-guidance">
                        <strong>{{ $locale === 'hi' ? 'सुझाव / उपाय:' : 'Actionable Tip:' }}</strong> {{ $catData['guidance'] }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Current Planetary Transits Summary -->
    @if(!empty($insights['transits_summary']) && is_array($insights['transits_summary']))
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'वर्तमान गोचर स्थिति (ग्रह प्रभाव)' : 'Current Planetary Transits (Gochara)' }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'ग्रह' : 'Planet' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'गोचर राशि' : 'Transit Sign' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'भाव' : 'House from Moon' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'प्रभाव' : 'Influence' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($insights['transits_summary'] as $tp)
                <tr>
                    <td>{{ $tp['planet'] ?? '-' }}</td>
                    <td>{{ $tp['transit_sign'] ?? '-' }}</td>
                    <td>{{ $tp['gochara_house'] ?? '-' }} {{ $locale === 'hi' ? 'भाव' : 'House' }}</td>
                    <td>{{ $tp['influence'] ?? ($locale === 'hi' ? 'सक्रिय' : 'Active') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Disclaimers & Footer -->
    <div style="font-size: 7.5px; color: #718096; margin-top: 6px; line-height: 1.3; background: #FFFDF7; border: 1px solid #ECC94B; padding: 4px 7px; border-radius: 3px;">
        <strong>{{ $locale === 'hi' ? 'वैदिक ज्योतिष अस्वीकरण:' : 'Astrological Disclaimer:' }}</strong>
        {{ $locale === 'hi' ? 'दैनिक भविष्यफल गोचर ग्रहों की चन्द्र राशि से स्थिति पर आधारित मार्गदर्शन है। इसे चिकित्सीय, विधिक या वित्तीय सलाह न समझें।' : 'Daily insights are based on transit (Gochara) alignments relative to natal moon placement for guidance and mindfulness purposes only.' }}
    </div>

    <div class="footer-bar">
        <table>
            <tr>
                <td style="text-align: left;">
                    {{ $branding['platform_footer'] ?? 'Powered by Astro Setu — Authentic Vedic Astrology Platform' }}
                </td>
                <td style="text-align: right;">
                    {{ $locale === 'hi' ? 'जनरेटेड:' : 'Generated:' }} {{ now()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST
                </td>
            </tr>
        </table>
    </div>

    {{-- Script for Dompdf dynamic page footer --}}
    <script type="text/php">
        if (isset($pdf)) {
            $isHi = ("{{ $locale }}" === 'hi');
            $font = $fontMetrics->getFont($isHi ? "NotoDevanagari" : "DejaVu Sans", "normal");
            $size = 7.5;
            $brandText = $isHi ? "एस्ट्रो सेतु — दैनिक व्यक्तिगत प्रतिवेदन" : "Powered by Astro Setu — Vedic Astrology Platform";
            $pdf->page_text(26, 818, $brandText, $font, $size, [0.45, 0.5, 0.58]);
            $pagePattern = $isHi ? "पृष्ठ {PAGE_NUM} / {PAGE_COUNT}" : "Page {PAGE_NUM} of {PAGE_COUNT}";
            $pdf->page_text(510, 818, $pagePattern, $font, $size, [0.45, 0.5, 0.58]);
        }
    </script>
</body>
</html>
