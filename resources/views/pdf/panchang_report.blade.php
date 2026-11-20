<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $locale === 'hi' ? 'दैनिक पञ्चाङ्ग - एस्ट्रो सेतु' : 'Daily Vedic Panchanga Report - Astro Setu' }}</title>
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
            font-size: 8.5px;
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

        .section-box {
            background-color: #FAFCFE;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 6px 9px;
            margin-bottom: 7px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #8C1D40;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 3px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
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

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }
        .data-table th, .data-table td {
            border: 1px solid #CBD5E0;
            padding: 3.5px 6px;
            text-align: left;
            font-size: 8px;
        }
        .data-table th {
            background-color: #EDF2F7;
            color: #2D3748;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background-color: #F7FAFC;
        }

        .timing-good {
            color: #22543D;
            background-color: #F0FFF4;
            font-weight: bold;
        }
        .timing-bad {
            color: #742A2A;
            background-color: #FFF5F5;
            font-weight: bold;
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
                <td style="width: 60%; vertical-align: top;">
                    <div class="practitioner-name">{{ $branding['practitioner_name'] ?? 'Astro Setu' }}</div>
                    <div class="practitioner-headline">{{ $branding['headline'] ?? ($locale === 'hi' ? 'वैदिक ज्योतिष परामर्श' : 'Vedic Astrological Consultations') }}</div>
                    <div class="practitioner-contact">
                        @if(!empty($branding['city'])) {{ $branding['city'] }} @endif
                        @if(!empty($branding['phone'])) &bull; {{ $branding['phone'] }} @endif
                        @if(!empty($branding['email'])) &bull; {{ $branding['email'] }} @endif
                    </div>
                </td>
                <td style="width: 40%; vertical-align: top; text-align: right;">
                    <div class="report-badge">{{ $locale === 'hi' ? 'दैनिक पञ्चाङ्ग' : 'Daily Vedic Panchanga' }}</div>
                    <div class="report-sub">
                        {{ $locale === 'hi' ? 'तिथि:' : 'Date:' }} {{ \Carbon\Carbon::parse($panchang['metadata']['target_date'] ?? $panchang['date'] ?? now())->format('d M Y') }}
                    </div>
                    <div class="report-sub">
                        {{ $locale === 'hi' ? 'स्थान:' : 'Location:' }} {{ $location['city'] ?? 'Varanasi' }} ({{ round((float)($location['latitude'] ?? 25.31), 2) }}°N, {{ round((float)($location['longitude'] ?? 82.97), 2) }}°E)
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Date & Sun/Moon Details -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'दिन एवं सूर्य-चन्द्र स्थिति' : 'Day, Solar & Lunar Timings' }}</div>
        <table class="grid-table">
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'वार (दिन)' : 'Vara (Day)' }}:</td>
                <td class="val">{{ $panchang['panchanga']['vara']['name'] ?? ($locale === 'hi' ? 'शुक्रवार' : 'Friday') }} ({{ $panchang['panchanga']['vara']['sanskrit'] ?? '' }})</td>
                <td class="lbl">{{ $locale === 'hi' ? 'सूर्योदय' : 'Sunrise' }}:</td>
                <td class="val">{{ $panchang['sun_moon']['sunrise'] ?? '06:15 AM' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'तिथि' : 'Tithi' }}:</td>
                <td class="val">{{ $panchang['panchanga']['tithi']['name'] ?? '-' }} ({{ $panchang['panchanga']['tithi']['paksha'] ?? '-' }} {{ $locale === 'hi' ? 'पक्ष' : 'Paksha' }})</td>
                <td class="lbl">{{ $locale === 'hi' ? 'सूर्यास्त' : 'Sunset' }}:</td>
                <td class="val">{{ $panchang['sun_moon']['sunset'] ?? '05:30 PM' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'नक्षत्र' : 'Nakshatra' }}:</td>
                <td class="val">{{ $panchang['panchanga']['nakshatra']['name'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'चन्द्रोदय' : 'Moonrise' }}:</td>
                <td class="val">{{ $panchang['sun_moon']['moonrise'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'योग' : 'Yoga' }}:</td>
                <td class="val">{{ $panchang['panchanga']['yoga']['name'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'चन्द्रास्त' : 'Moonset' }}:</td>
                <td class="val">{{ $panchang['sun_moon']['moonset'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'करण' : 'Karana' }}:</td>
                <td class="val">{{ $panchang['panchanga']['karana']['name'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'समय क्षेत्र' : 'Timezone' }}:</td>
                <td class="val">{{ $location['timezone'] ?? 'Asia/Kolkata' }}</td>
            </tr>
        </table>
    </div>

    <!-- Vedic Calendar (Samvat & Masa) -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'हिन्दू काल गणना एवं संवत' : 'Vedic Calendar & Samvat' }}</div>
        <table class="grid-table">
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'विक्रम संवत' : 'Vikram Samvat' }}:</td>
                <td class="val">{{ $panchang['hindu_calendar']['vikram_samvat'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'शक संवत' : 'Shaka Samvat' }}:</td>
                <td class="val">{{ $panchang['hindu_calendar']['shaka_samvat'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'पूर्णिमांत मास' : 'Purnimanta Masa' }}:</td>
                <td class="val">{{ $panchang['hindu_calendar']['purnimanta_masa'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'अमांत मास' : 'Amanta Masa' }}:</td>
                <td class="val">{{ $panchang['hindu_calendar']['amanta_masa'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ $locale === 'hi' ? 'ऋतु' : 'Ritu (Season)' }}:</td>
                <td class="val">{{ $panchang['hindu_calendar']['ritu'] ?? '-' }}</td>
                <td class="lbl">{{ $locale === 'hi' ? 'अयन' : 'Ayana' }}:</td>
                <td class="val">{{ $panchang['hindu_calendar']['ayana'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Auspicious Muhurats -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'शुभ मुहूर्त एवं अनुकूल समय' : 'Auspicious Muhurats (Shubh Timings)' }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 35%;">{{ $locale === 'hi' ? 'मुहूर्त' : 'Muhurta' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'आरंभ' : 'Start Time' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'समाप्ति' : 'End Time' }}</th>
                    <th style="width: 15%;">{{ $locale === 'hi' ? 'प्रकृति' : 'Nature' }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="timing-good">
                    <td>{{ $locale === 'hi' ? 'अभिजीत मुहूर्त' : 'Abhijit Muhurta' }}</td>
                    <td>{{ $panchang['auspicious_timings']['abhijit_muhurta']['start'] ?? '-' }}</td>
                    <td>{{ $panchang['auspicious_timings']['abhijit_muhurta']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'अति शुभ' : 'Auspicious' }}</td>
                </tr>
                <tr class="timing-good">
                    <td>{{ $locale === 'hi' ? 'ब्रह्म मुहूर्त' : 'Brahma Muhurta' }}</td>
                    <td>{{ $panchang['auspicious_timings']['brahma_muhurta']['start'] ?? '-' }}</td>
                    <td>{{ $panchang['auspicious_timings']['brahma_muhurta']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'शुभ' : 'Auspicious' }}</td>
                </tr>
                <tr class="timing-good">
                    <td>{{ $locale === 'hi' ? 'अमृत काल' : 'Amrita Kaal' }}</td>
                    <td>{{ $panchang['auspicious_timings']['amrita_kaal']['start'] ?? '-' }}</td>
                    <td>{{ $panchang['auspicious_timings']['amrita_kaal']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'शुभ' : 'Auspicious' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Inauspicious Timings -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'अशुभ समय एवं त्याज्य काल' : 'Inauspicious Timings (Ashubh Kaal)' }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 35%;">{{ $locale === 'hi' ? 'काल / मुहूर्त' : 'Period / Muhurta' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'आरंभ' : 'Start Time' }}</th>
                    <th style="width: 25%;">{{ $locale === 'hi' ? 'समाप्ति' : 'End Time' }}</th>
                    <th style="width: 15%;">{{ $locale === 'hi' ? 'सुझाव' : 'Guidance' }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="timing-bad">
                    <td>{{ $locale === 'hi' ? 'राहु काल' : 'Rahu Kaal' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['rahu_kaal']['start'] ?? '-' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['rahu_kaal']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'वर्जित' : 'Avoid New Work' }}</td>
                </tr>
                <tr class="timing-bad">
                    <td>{{ $locale === 'hi' ? 'यमगण्ड' : 'Yamaganda' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['yamaganda']['start'] ?? '-' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['yamaganda']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'वर्जित' : 'Inauspicious' }}</td>
                </tr>
                <tr class="timing-bad">
                    <td>{{ $locale === 'hi' ? 'गुलिक काल' : 'Gulika Kaal' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['gulika_kaal']['start'] ?? '-' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['gulika_kaal']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'मध्यम' : 'Neutral/Cautious' }}</td>
                </tr>
                @if(!empty($panchang['inauspicious_timings']['dur_muhurta']['start']))
                <tr class="timing-bad">
                    <td>{{ $locale === 'hi' ? 'दुर्मुहूर्त' : 'Dur Muhurta' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['dur_muhurta']['start'] }}</td>
                    <td>{{ $panchang['inauspicious_timings']['dur_muhurta']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'वर्जित' : 'Inauspicious' }}</td>
                </tr>
                @endif
                @if(!empty($panchang['inauspicious_timings']['varjyam']['start']))
                <tr class="timing-bad">
                    <td>{{ $locale === 'hi' ? 'वर्ज्यम्' : 'Varjyam' }}</td>
                    <td>{{ $panchang['inauspicious_timings']['varjyam']['start'] }}</td>
                    <td>{{ $panchang['inauspicious_timings']['varjyam']['end'] ?? '-' }}</td>
                    <td>{{ $locale === 'hi' ? 'त्याज्य' : 'Avoid' }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Educational Disclaimer & Astro Setu Footer -->
    <div style="font-size: 7.5px; color: #718096; margin-top: 8px; line-height: 1.3; background: #FFFDF7; border: 1px solid #ECC94B; padding: 4px 7px; border-radius: 3px;">
        <strong>{{ $locale === 'hi' ? 'महत्वपूर्ण सूचना:' : 'Educational Notice:' }}</strong>
        {{ $locale === 'hi' ? 'पंचांग गणना पारंपरिक सूर्य-सिद्धान्त व दृक-गणित आधारित है। स्थानीय सूर्योदय अनुसार समय में कुछ मिनटों का अन्तर हो सकता है।' : 'Panchang calculations are computed based on traditional Drik Ganita and high-precision ephemeris for the given geographical coordinates.' }}
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
            $brandText = $isHi ? "एस्ट्रो सेतु — वैदिक पञ्चाङ्ग प्रतिवेदन" : "Powered by Astro Setu — Vedic Astrology Platform";
            $pdf->page_text(26, 818, $brandText, $font, $size, [0.45, 0.5, 0.58]);
            $pagePattern = $isHi ? "पृष्ठ {PAGE_NUM} / {PAGE_COUNT}" : "Page {PAGE_NUM} of {PAGE_COUNT}";
            $pdf->page_text(510, 818, $pagePattern, $font, $size, [0.45, 0.5, 0.58]);
        }
    </script>
</body>
</html>
